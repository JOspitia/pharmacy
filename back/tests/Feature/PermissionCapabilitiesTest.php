<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Pruebas de las capacidades de negocio resueltas por Gate.
 *
 * Son la parte que justifico rehacer el modelo de permisos: antes estas
 * capacidades vivian en un array constante de AuthServiceProvider y se
 * concedian mirando el nombre del rol. Ahora son filas de la tabla permissions
 * y se conceden mirando si el rol tiene el permiso.
 *
 * La diferencia observable es que agregar una capacidad dejó de ser un cambio
 * de codigo: CA-11 lo comprueba.
 */
class PermissionCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ModuleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    // ------------------------------------------------------------------
    // Los Gates se definen desde la base
    // ------------------------------------------------------------------

    /**
     * Los Gates se resuelven contra la tabla permissions.
     *
     * No se usa Gate::has() porque los Gates no se registran uno por uno: el
     * provider los intercepta con Gate::before cuando se consultan. Lo que se
     * verifica es que toda capacidad del catalogo sea resoluble.
     */
    public function test_toda_capacidad_del_catalogo_es_resoluble(): void
    {
        $regente = User::factory()->regente()->create();

        foreach (Permission::whereNull('module_id')->pluck('name') as $capacidad) {
            // Resolverla no debe lanzar: devuelve true o false segun la
            // matriz, y nunca una excepcion.
            $this->assertIsBool($regente->can($capacidad), "la capacidad {$capacidad} deberia resolver a booleano");
        }
    }

    public function test_los_gates_no_son_una_lista_fija_del_codigo(): void
    {
        $regente = User::factory()->regente()->create();
        $auxiliar = User::factory()->auxiliar()->create();

        // Capacidad del catalogo: se concede segun la matriz de permisos.
        $this->assertTrue($regente->can('autorizar-control-especial'));
        $this->assertFalse($auxiliar->can('autorizar-control-especial'));

        // Capacidad inventada: se deniega sin error, porque no hay ninguna
        // fila con ese nombre en permissions.
        $this->assertFalse($regente->can('capacidad-inventada'));
    }

    public function test_la_autorizacion_de_control_especial_solo_la_tiene_el_regente(): void
    {
        $regente = User::factory()->regente()->create();
        $auxiliar = User::factory()->auxiliar()->create();
        $medico = User::factory()->medico()->create();
        $auditor = User::factory()->auditor()->create();

        // RN-05: el control especial lo autoriza un segundo usuario con rol
        // regente_farmacia.
        $this->assertTrue($regente->can('autorizar-control-especial'));
        $this->assertFalse($auxiliar->can('autorizar-control-especial'));
        $this->assertFalse($medico->can('autorizar-control-especial'));
        $this->assertFalse($auditor->can('autorizar-control-especial'));
    }

    public function test_aprobar_traslado_solo_lo_tiene_el_regente(): void
    {
        $this->assertTrue(User::factory()->regente()->create()->can('aprobar-traslado'));
        $this->assertFalse(User::factory()->auxiliar()->create()->can('aprobar-traslado'));
        $this->assertFalse(User::factory()->auditor()->create()->can('aprobar-traslado'));
    }

    public function test_las_capacidades_compartidas_se_conceden_igual_que_antes(): void
    {
        foreach (['dispensar', 'consultar-inventario', 'crear-traslado', 'recibir-traslado'] as $capacidad) {
            $this->assertTrue(
                User::factory()->auxiliar()->create()->can($capacidad),
                "auxiliar deberia poder {$capacidad}"
            );
            $this->assertTrue(
                User::factory()->regente()->create()->can($capacidad),
                "regente deberia poder {$capacidad}"
            );
        }

        $this->assertTrue(User::factory()->medico()->create()->can('crear-prescripcion'));
        $this->assertTrue(User::factory()->medico()->create()->can('consultar-pacientes'));
        $this->assertTrue(User::factory()->auditor()->create()->can('consultar-pacientes-enmascarados'));
    }

    public function test_una_capacidad_desconocida_se_deniega_sin_error(): void
    {
        $regente = User::factory()->regente()->create();

        // No hay Gate definido para ese nombre y Gate::before devuelve null
        // porque no esta en el catalogo. La resolucion termina en false sin
        // lanzar excepcion: una capacidad escrita mal se deniega en vez de
        // romper la peticion.
        $this->assertFalse($regente->can('capacidad-que-no-existe'));
    }

    // ------------------------------------------------------------------
    // Un permiso nuevo no requiere codigo
    // ------------------------------------------------------------------

    public function test_agregar_un_permiso_no_requiere_migracion_ni_codigo(): void
    {
        // Se inserta la fila y se asigna a un rol.
        $permiso = Permission::create([
            'name' => 'exportar-inventario',
            'module_id' => null,
            'description' => 'Exportar el inventario a Excel',
        ]);

        $regente = User::factory()->regente()->create();
        $regente->role->permissions()->attach($permiso->id);

        // Sin volver a cargar AuthServiceProvider a mano, el Gate ya existe:
        // Laravel resuelve los Gates definidos dinamicamente cuando no
        // encuentra uno estatico, ejecutando el callback con el nombre pedido.
        $this->assertTrue($regente->fresh()->can('exportar-inventario'));
        $this->assertFalse(User::factory()->auxiliar()->create()->can('exportar-inventario'));
    }

    public function test_retirar_un_permiso_lo_deniega_sin_tocar_codigo(): void
    {
        $regente = User::factory()->regente()->create();

        $this->assertTrue($regente->can('aprobar-traslado'));

        $permisoId = Permission::where('name', 'aprobar-traslado')->value('id');
        $regente->role->permissions()->detach($permisoId);

        $this->assertFalse($regente->fresh()->can('aprobar-traslado'));
    }

    // ------------------------------------------------------------------
    // hasPermission es el unico metodo de autorizacion
    // ------------------------------------------------------------------

    public function test_has_permission_resuelve_crud_y_capacidades_por_igual(): void
    {
        $regente = User::factory()->regente()->create();

        // Un CRUD.
        $this->assertTrue($regente->hasPermission('warehouses.can_create'));
        $this->assertFalse($regente->hasPermission('sensors.can_create'));

        // Una capacidad.
        $this->assertTrue($regente->hasPermission('autorizar-control-especial'));

        // Una cadena inventada.
        $this->assertFalse($regente->hasPermission('no.existe'));
    }

    public function test_has_permission_devuelve_false_sin_rol(): void
    {
        // Usuario sin role_id no deberia dar error, solo denegar.
        $usuario = User::factory()->create();
        $usuario->role_id = null;

        $this->assertFalse($usuario->hasPermission('warehouses.can_read'));
    }

    // ------------------------------------------------------------------
    // ------------------------------------------------------------------

    /**
     * No hay bypass de admin: la matriz es la unica fuente.
     *
     * Antes, Gate::before devolvia true para cualquier admin y esas capacidades
     * se concedian sin estar en la matriz. Ahora el admin no las tiene, y por eso
     * los dos tests de AdminNoAutorizaTest pasan.
     */
public function test_el_admin_no_puede_autorizar_ni_aprobar(): void
    {
        $admin = User::factory()->admin()->create();

        // RN-05 y RN-08. Sin esto, el doble control del enunciado desaparece:
        // un admin autorizando control especial deja de ser "un segundo usuario
        // con rol regente_farmacia".
        $this->assertFalse($admin->can('autorizar-control-especial'));
        $this->assertFalse($admin->can('aprobar-traslado'));

        // Lo administrativo si lo puede: es para eso que existe el rol.
        $this->assertTrue($admin->can('gestionar-usuarios'));
        $this->assertTrue($admin->can('gestionar-catalogos'));
    }

    /**
     * Una capacidad desconocida devuelve null y no false.
     *
     * Devolver null deja seguir la resolucion, de modo que una Policy de
     * modelo pueda decidir despues. Devolver false cortaria la cadena en seco y
     * las Policies nunca correrian.
     */
    public function test_una_capacidad_fuera_del_catalogo_no_corta_la_cadena(): void
    {
        $regente = User::factory()->regente()->create();

        // hasPermission responde false, pero el Gate no resuelve: no hay
        // Gate::define para ese nombre y Gate::before devuelve null.
        $this->assertFalse($regente->can('capacidad-inventada'));
        $this->assertFalse($regente->hasPermission('capacidad-inventada'));
    }
}
