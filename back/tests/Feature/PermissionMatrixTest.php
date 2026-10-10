<?php

namespace Tests\Feature;

use Database\Seeders\ModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pruebas del catalogo de permisos y de la matriz de asignaciones.
 *
 * La matriz se carga con los seeders y se lee con consultas directas a la
 * tabla, sin pasar por el modelo. La razon es separar las dos cosas que pueden
 * fallar: que el seeder cargue lo que dice, y que el middleware y los Gates lo
 * lean bien. Eso segundo lo cubren EnsureModuleTest y
 * PermissionCapabilitiesTest.
 *
 * Se consulta con SQL crudo a proposito: consultar con el modelo comprobaria
 * que el modelo funciona, que ya probaron otros tests. Con SQL se verifica
 * contra la tabla, que es donde vive la verdad.
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Catalogo y matriz reales, no unos hechos a mano: el comportamiento que
        // se comprueba es el que tendra el sistema en produccion.
        $this->seed(RoleSeeder::class);
        $this->seed(ModuleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * ¿El rol tiene este permiso? Leido de role_permissions.
     */
    private function tiene(string $rol, string $permiso): bool
    {
        return DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('roles.name', $rol)
            ->where('permissions.name', $permiso)
            ->exists();
    }

    // ------------------------------------------------------------------
    // Catalogo
    // ------------------------------------------------------------------

    public function test_se_cargan_los_cinco_roles(): void
    {
        $this->assertSame(
            ['admin', 'auditor', 'auxiliar_farmacia', 'medico', 'regente_farmacia'],
            DB::table('roles')->orderBy('name')->pluck('name')->all()
        );
    }

    public function test_se_cargan_los_siete_modulos(): void
    {
        $this->assertSame(
            // Orden alfabetico por name: groups, lots, products, reports,
            // sensors, users, warehouses. La posicion importa porque la
            // consulta trae orderBy('name') y assertSame compara el orden.
            ['groups', 'lots', 'products', 'reports', 'sensors', 'users', 'warehouses'],
            DB::table('modules')->orderBy('name')->pluck('name')->all()
        );
    }

    public function test_los_crud_son_filas_y_no_columnas(): void
    {
        // El cambio central de esta HU: los cuatro permisos de un modulo son
        // cuatro filas en permissions, no cuatro booleanos en una tabla.
        $deBodegas = DB::table('permissions')
            ->join('modules', 'modules.id', '=', 'permissions.module_id')
            ->where('modules.name', 'warehouses')
            ->orderBy('permissions.name')
            // pluck debe ser explicito: permissions y modules tienen las dos
            // una columna llamada name, y sin prefijar el nombre SQL Server no
            // sabe cual de las dos se quiere.
            ->pluck('permissions.name')
            ->all();

        $this->assertSame([
            'warehouses.can_create',
            'warehouses.can_delete',
            'warehouses.can_read',
            'warehouses.can_update',
        ], $deBodegas);
    }

    public function test_cada_modulo_tiene_los_cuatro_crud(): void
    {
        $modulos = DB::table('modules')->count();

        $crud = DB::table('permissions')
            ->whereNotNull('module_id')
            ->count();

        $this->assertSame($modulos * 4, $crud);
    }

    public function test_las_capacidades_no_tienen_modulo(): void
    {
        $globales = DB::table('permissions')
            ->whereNull('module_id')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $this->assertContains('autorizar-control-especial', $globales);
        $this->assertContains('aprobar-traslado', $globales);
        $this->assertContains('dispensar', $globales);

        // Ninguna capacidad lleva el prefijo de un modulo.
        foreach ($globales as $nombre) {
            $this->assertStringNotContainsString('.', $nombre);
        }
    }

    public function test_todo_permiso_tiene_descripcion(): void
    {
        $sinDescripcion = DB::table('permissions')
            ->whereNull('description')
            ->orWhere('description', '')
            ->count();

        $this->assertSame(0, $sinDescripcion);
    }

    public function test_no_se_puede_crear_un_permiso_duplicado(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('permissions')->insert([
            'name' => 'warehouses.can_read',
            'module_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }


    public function test_las_capacidades_no_aparecen_en_la_relacion_del_modulo(): void
    {
        $modulo = Module::where('name', 'warehouses')->first();

        foreach ($modulo->permissions as $permiso) {
            $this->assertNotNull($permiso->module_id);
        }

        // Y del lado global: module_id null, que es lo que distingue una
        // capacidad de un permiso de modulo.
        $this->assertSame(
            0,
            Permission::whereNull('module_id')->where('name', 'warehouses.can_read')->count()
        );
    }

    // ------------------------------------------------------------------
    // Matriz CRUD (Anexo A de la HU)
    // ------------------------------------------------------------------

    /**
     * El administrador lee todos los modulos clinicos pero no los escribe.
     *
     * Es lo que hace posible que no exista un bypass en Gate::before: el admin
     * tiene lo que la matriz le da, y la matriz no le da escritura sobre los
     * flujos clinicos. Ver RN-05 y RN-08.
     */
    /**
 * El administrador consulta todo y escribe donde la matriz del enunciado dice.
 *
 * Es lo que separa a admin de los demas roles: la diferencia no esta en que no
 * pueda escribir, esta en WHERE escribe. Administracion (bodegas, catalogos,
 * usuarios) si; stock (lotes) no, porque es el flujo clinico que RN-05 y RN-08
 * protegen. Ver PENDIENTE_auditoria.md, matriz por modulo.
 */
public function test_admin_consulta_todos_los_modulos_y_escribe_solo_where_corresponde(): void
    {
        foreach (['warehouses', 'groups', 'products', 'lots', 'sensors', 'reports', 'users'] as $modulo) {
            $this->assertTrue(
                $this->tiene('admin', "{$modulo}.can_read"),
                "admin deberia consultar {$modulo}"
            );
        }

        // Administracion: escritura completa.
        foreach (['warehouses', 'groups', 'products', 'users'] as $modulo) {
            foreach (['can_create', 'can_update', 'can_delete'] as $accion) {
                $this->assertTrue(
                    $this->tiene('admin', "{$modulo}.{$accion}"),
                    "admin deberia escribir en {$modulo}"
                );
            }
        }

        // Stock y consulta: solo lectura. Un admin que pudiera mover stock
        // anularia el doble control que exige RN-05.
        foreach (['lots', 'sensors', 'reports'] as $modulo) {
            foreach (['can_create', 'can_update', 'can_delete'] as $accion) {
                $this->assertFalse(
                    $this->tiene('admin', "{$modulo}.{$accion}"),
                    "admin no deberia escribir en {$modulo}"
                );
            }
        }
    }

    public function test_admin_administra_usuarios(): void
    {
        // users no es un flujo clinico: administrar usuarios es justamente lo
        // que existe el rol admin para hacer.
        foreach (['can_read', 'can_create', 'can_update', 'can_delete'] as $accion) {
            $this->assertTrue(
                $this->tiene('admin', "users.{$accion}"),
                "admin deberia administrar usuarios"
            );
        }
    }

    public function test_regente_administra_el_inventario_y_consulta_lo_demas(): void
    {
        foreach (['warehouses', 'groups', 'products', 'lots'] as $modulo) {
            foreach (['can_read', 'can_create', 'can_update', 'can_delete'] as $accion) {
                $this->assertTrue(
                    $this->tiene('regente_farmacia', "{$modulo}.{$accion}"),
                    "regente deberia administrar {$modulo}"
                );
            }
        }

        // Sensores y reportes: solo lectura.
        foreach (['sensors', 'reports'] as $modulo) {
            $this->assertTrue($this->tiene('regente_farmacia', "{$modulo}.can_read"));
            $this->assertFalse($this->tiene('regente_farmacia', "{$modulo}.can_create"));
            $this->assertFalse($this->tiene('regente_farmacia', "{$modulo}.can_update"));
            $this->assertFalse($this->tiene('regente_farmacia', "{$modulo}.can_delete"));
        }

        // Usuarios: nada.
        foreach (['can_read', 'can_create', 'can_update', 'can_delete'] as $accion) {
            $this->assertFalse($this->tiene('regente_farmacia', "usuarios.{$accion}"));
        }
    }

    public function test_auxiliar_consulta_y_opera_lotes_sin_borrarlos(): void
    {
        foreach (['warehouses', 'groups', 'products', 'reports'] as $modulo) {
            $this->assertTrue($this->tiene('auxiliar_farmacia', "{$modulo}.can_read"));
            $this->assertFalse($this->tiene('auxiliar_farmacia', "{$modulo}.can_create"));
            $this->assertFalse($this->tiene('auxiliar_farmacia', "{$modulo}.can_update"));
            $this->assertFalse($this->tiene('auxiliar_farmacia', "{$modulo}.can_delete"));
        }

        $this->assertTrue($this->tiene('auxiliar_farmacia', 'lots.can_create'));
        $this->assertTrue($this->tiene('auxiliar_farmacia', 'lots.can_update'));
        $this->assertFalse($this->tiene('auxiliar_farmacia', 'lots.can_delete'));
    }

    public function test_medico_consulta_inventario_y_no_administra_grupos(): void
    {
        foreach (['warehouses', 'products', 'lots', 'reports'] as $modulo) {
            $this->assertTrue($this->tiene('medico', "{$modulo}.can_read"));
        }

        $this->assertFalse($this->tiene('medico', 'groups.can_read'));
        $this->assertFalse($this->tiene('medico', 'sensors.can_read'));
        $this->assertFalse($this->tiene('medico', 'warehouses.can_create'));
    }

    public function test_auditor_es_solo_lectura_en_los_seis_modulos(): void
    {
        $modulos = DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('roles.name', 'auditor')
            ->whereNotNull('permissions.module_id')
            ->distinct()
            ->count('permissions.module_id');

        $this->assertSame(6, $modulos);

        foreach (['warehouses', 'groups', 'products', 'lots', 'sensors', 'reports'] as $modulo) {
            $this->assertTrue($this->tiene('auditor', "{$modulo}.can_read"), "auditor deberia leer {$modulo}");
            $this->assertFalse($this->tiene('auditor', "{$modulo}.can_create"));
            $this->assertFalse($this->tiene('auditor', "{$modulo}.can_update"));
            $this->assertFalse($this->tiene('auditor', "{$modulo}.can_delete"));
        }
    }

    public function test_ningun_rol_menos_admin_tiene_permiso_sobre_usuarios(): void
    {
        $conAcceso = DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('roles.name', '!=', 'admin')
            ->where('permissions.name', 'like', 'usuarios.%')
            ->count();

        $this->assertSame(0, $conAcceso);
    }

    // ------------------------------------------------------------------
    // Capacidades (Anexo B de la HU)
    // ------------------------------------------------------------------

    public function test_autorizar_control_especial_es_exclusivo_del_regente(): void
    {
        $this->assertTrue($this->tiene('regente_farmacia', 'autorizar-control-especial'));

        foreach (['auxiliar_farmacia', 'medico', 'auditor'] as $rol) {
            $this->assertFalse(
                $this->tiene($rol, 'autorizar-control-especial'),
                "RN-05: {$rol} no deberia autorizar control especial"
            );
        }
    }

    public function test_aprobar_traslado_es_exclusivo_del_regente(): void
    {
        $this->assertTrue($this->tiene('regente_farmacia', 'aprobar-traslado'));

        foreach (['auxiliar_farmacia', 'medico', 'auditor'] as $rol) {
            $this->assertFalse($this->tiene($rol, 'aprobar-traslado'), "RN-08: {$rol} no deberia aprobar traslados");
        }
    }

    public function test_las_capacidades_compartidas_siguen_la_seccion_del_enunciado(): void
    {
        // auxiliar y regente comparten la base operativa.
        $compartidas = ['dispensar', 'crear-traslado', 'recibir-traslado'];

        foreach ($compartidas as $capacidad) {
            $this->assertTrue($this->tiene('auxiliar_farmacia', $capacidad), "auxiliar deberia tener {$capacidad}");
            $this->assertTrue($this->tiene('regente_farmacia', $capacidad), "regente deberia tener {$capacidad}");
            $this->assertFalse($this->tiene('auditor', $capacidad), "auditor no deberia tener {$capacidad}");
        }

        // consultar-inventario la comparten los cuatro, incluido el auditor.
        foreach (['auxiliar_farmacia', 'regente_farmacia', 'medico', 'auditor'] as $rol) {
            $this->assertTrue($this->tiene($rol, 'consultar-inventario'), "{$rol} deberia consultar inventario");
        }

        // Exclusivas del regente.
        $this->assertTrue($this->tiene('regente_farmacia', 'ajustar-inventario'));
        $this->assertFalse($this->tiene('auxiliar_farmacia', 'ajustar-inventario'));

        // Exclusivas del medico.
        $this->assertTrue($this->tiene('medico', 'crear-prescripcion'));
        $this->assertTrue($this->tiene('medico', 'consultar-pacientes'));
        $this->assertFalse($this->tiene('auditor', 'crear-prescripcion'));

        // Exclusiva del auditor.
        $this->assertTrue($this->tiene('auditor', 'consultar-pacientes-enmascarados'));
        $this->assertFalse($this->tiene('medico', 'consultar-pacientes-enmascarados'));
    }

    public function test_admin_no_tiene_las_capacidades_clinicas(): void
    {
        // Sin bypass en Gate::before, lo que el admin no tiene en la matriz no
        // lo puede hacer. Estas dos son las que exigen segregacion de
        // funciones: RN-05 (doble control del regente) y RN-08 (quien solicita
        // no aprueba).
        $this->assertFalse($this->tiene('admin', 'autorizar-control-especial'));
        $this->assertFalse($this->tiene('admin', 'aprobar-traslado'));

        // Las administrativas si las tiene.
        $this->assertTrue($this->tiene('admin', 'gestionar-usuarios'));
        $this->assertTrue($this->tiene('admin', 'gestionar-catalogos'));
    }

    // ------------------------------------------------------------------
    // Idempotencia y unicidad
    // ------------------------------------------------------------------

    /**
 * Quitar un permiso de un rol se refleja en la base.
 *
 * Este test existe por un bug concreto. El seeder usaba
 * syncWithoutDetaching, que solo agrega y nunca quita: cuando la matriz de
 * admin paso de CRUD a solo lectura, los permisos de escritura que ya tenia
 * siguieron en la base. El seeder decia una cosa y los datos otra.
 *
 * RefreshDatabase hacia que la suite no lo notara, porque las pruebas siempre
 * partian de una base recien creada. La deriva solo aparecia al correr el
 * seeder sobre una base ya sembrada, que es lo que pasa en un despliegue.
 *
 * sync() en vez de syncWithoutDetaching() corrige la raiz: la matriz queda
 * exactamente como el seeder la declara, y un permiso que el seeder saca de
 * un rol tambien se lo saca de la base.
 */
public function test_quitar_un_permiso_lo_refleja_en_la_base(): void
    {
        $roleId = Role::where('name', 'auditor')->value('id');
        $escrituraId = Permission::where('name', 'lots.can_create')->value('id');

        // El auditor arranca sin escritura sobre stock.
        $this->assertFalse($this->tiene('auditor', 'lots.can_create'));

        // Se le agrega a mano y se comprueba que la matriz lo refleja.
        DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $escrituraId]);
        $this->assertTrue($this->tiene('auditor', 'lots.can_create'));

        // Y al volver a correr el seeder, vuelve a la matriz declarada: el
        // permiso rogue desaparece.
        $this->seed(RolePermissionSeeder::class);

        $this->assertFalse($this->tiene('auditor', 'lots.can_create'));
        $this->assertTrue($this->tiene('auditor', 'lots.can_read'));

    }

    public function test_correr_los_seeders_de_nuevo_no_rompe_nada(): void
    {
        $antes = DB::table('role_permissions')->orderBy('role_id')->orderBy('permission_id')->get();

        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $despues = DB::table('role_permissions')->orderBy('role_id')->orderBy('permission_id')->get();

        $this->assertCount(count($antes), $despues);
        $this->assertEquals($antes, $despues);
    }

    public function test_un_rol_no_puede_tener_dos_filas_para_el_mismo_permiso(): void
    {
        $duplicados = DB::table('role_permissions')
            ->select('role_id', 'permission_id')
            ->groupBy('role_id', 'permission_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $duplicados);
    }

    public function test_la_tabla_role_modules_ya_no_existe(): void
    {
        $existe = DB::select("SELECT OBJECT_ID('role_modules', 'U') AS id");

        $this->assertNull($existe[0]->id);
    }
}
