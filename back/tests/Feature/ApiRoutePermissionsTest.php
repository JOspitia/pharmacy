<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de acceso sobre las rutas reales de la API.
 *
 * EnsureModuleTest usa rutas definidas dentro del propio test para aislar el
 * middleware. Eso demuestra que el middleware lee bien la matriz, pero no
 * demuestra que cada ruta de routes/api.php este enchufada al modulo que le
 * corresponde: si el grupo de products quedara con module:warehouses, todos los
 * tests del middleware seguirian pasando y el bug no se veria.
 *
 * Este archivo cierra ese hueco. Usa las rutas reales tal como estan
 * declaradas y usuarios con los roles que carga el seeder, de modo que un
 * error de agrupacion o un permiso mal puesto en la matriz se manifieste
 * como un 403 inesperado.
 *
 * El criterio de estas pruebas es "que proceso puede hacer este rol sobre el
 * sistema real", no "que valor tiene una columna".
 *
 * La carpeta Models/ queda para el comportamiento de cada entidad por
 * separado (UUID, casts, borrado logico). Esto es otra cosa: es la unica capa
 * donde se ve el efecto combinado de matriz de permisos y rutas.
 */
class ApiRoutePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Catalogo y matriz reales, no unos hechos a mano: el comportamiento
        // que se comprueba es el que tendra el sistema en produccion.
        $this->seed(RoleSeeder::class);
        $this->seed(ModuleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Usuario con el rol indicado, resuelto contra los roles sembrados.
     */
    private function usuario(string $rol): User
    {
        return User::factory()->conRol($rol)->create();
    }

    // ------------------------------------------------------------------
    // admin: entra a todas partes
    // ------------------------------------------------------------------

    /**
 * El administrador administra bodegas y catalogos, pero no escribe en stock.
 *
 * La matriz del enunciado (PENDIENTE_auditoria.md) le da escritura sobre
 * bodegas, grupos y productos: son administracion. Le da solo lectura sobre
 * lotes, porque el stock es el flujo clinico que RN-05 y RN-08 protegen. Por
 * eso `gestionar-catalogos` no queda huerfana: coincide con lo que las rutas
 * le dejan hacer.
 */
public function test_admin_administra_catalogos_pero_no_escribe_en_stock(): void
    {
        $admin = $this->usuario('admin');
        $sufijo = bin2hex(random_bytes(4));

        // Administracion: entra.
        $this->actingAs($admin)
            ->postJson('/api/groups', ['name' => "Grupo Admin {$sufijo}"])
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson('/api/bodegas', ['name' => "Bodega Admin {$sufijo}"])
            ->assertCreated();

        // Stock: no entra. El control especial y los traslados se apoyan en
        // que el stock no se pueda mover sin un regente.
        $this->actingAs($admin)
            ->postJson('/api/lots', [])
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // regente: opera el inventario, solo consulta sensores y reportes
    // ------------------------------------------------------------------

    public function test_regente_consulta_los_modulos_de_su_dia_a_dia(): void
    {
        $regente = $this->usuario('regente_farmacia');

        $this->actingAs($regente)->getJson('/api/bodegas')->assertOk();
        $this->actingAs($regente)->getJson('/api/groups')->assertOk();
        $this->actingAs($regente)->getJson('/api/products')->assertOk();
        $this->actingAs($regente)->getJson('/api/lots')->assertOk();
    }

    public function test_regente_consulta_sensores_y_reportes_pero_no_los_administra(): void
    {
        $regente = $this->usuario('regente_farmacia');

        $this->actingAs($regente)->getJson('/api/sensors')->assertOk();
        $this->actingAs($regente)->getJson('/api/reportes/inventario')->assertOk();

        // Lecturas cuelgan de una bodega en la URL pero son del modulo
        // sensores: si estuvieran en module:warehouses, este POST pasaria y el
        // regente podria crear sensores sin permiso.
        $this->actingAs($regente)
            ->postJson('/api/sensors', [])
            ->assertForbidden();
    }

    public function test_regente_administra_grupos_pero_no_sensores(): void
    {
        $regente = $this->usuario('regente_farmacia');

        // Los cuatro modulos de inventario son CRUD para el regente. Es
        // facil suponer que tambien administra el catalogo de grupos y
        // escribir una expectativa equivocada en el test, asi que conviene
        // fijarlo explicitamente.
        $this->actingAs($regente)
            ->postJson('/api/groups', ['name' => 'Grupo del Regente'])
            ->assertCreated();

        // Sensores es distinto: lo consulta pero no lo administra.
        $this->actingAs($regente)
            ->postJson('/api/sensors', [])
            ->assertForbidden();

        // form-data es GET, asi que exige can_read, que el regente si tiene.
        $this->actingAs($regente)->getJson('/api/sensors/form-data')->assertOk();
    }

    // ------------------------------------------------------------------
    // auxiliar: consulta general, opera lotes sin borrarlos
    // ------------------------------------------------------------------

    public function test_auxiliar_consulta_bodegas_grupos_productos_y_reportes(): void
    {
        $auxiliar = $this->usuario('auxiliar_farmacia');

        $this->actingAs($auxiliar)->getJson('/api/bodegas')->assertOk();
        $this->actingAs($auxiliar)->getJson('/api/groups')->assertOk();
        $this->actingAs($auxiliar)->getJson('/api/products')->assertOk();
        $this->actingAs($auxiliar)->getJson('/api/reportes/inventario')->assertOk();
    }

    public function test_auxiliar_no_administra_bodegas_ni_productos(): void
    {
        $auxiliar = $this->usuario('auxiliar_farmacia');

        $this->actingAs($auxiliar)
            ->postJson('/api/bodegas', ['name' => 'Bodega Auxiliar'])
            ->assertForbidden();

        $this->actingAs($auxiliar)
            ->postJson('/api/products', [])
            ->assertForbidden();
    }

    public function test_auxiliar_opera_lotes_pero_no_los_borra(): void
    {
        $auxiliar = $this->usuario('auxiliar_farmacia');
        $bodega = Bodega::factory()->create();

        $this->actingAs($auxiliar)->getJson('/api/lots')->assertOk();

        // can_delete = false para auxiliar sobre lotes.
        $this->actingAs($auxiliar)
            ->deleteJson("/api/lots/{$this->loteDeEjemplo($bodega)->id}")
            ->assertForbidden();
    }

    public function test_auxiliar_no_ve_sensores(): void
    {
        $auxiliar = $this->usuario('auxiliar_farmacia');
        $bodega = Bodega::factory()->create();

        $this->actingAs($auxiliar)->getJson('/api/sensors')->assertForbidden();

        // Tampoco las lecturas ni las alertas, aunque la URL diga bodegas.
        $this->actingAs($auxiliar)
            ->getJson("/api/bodegas/{$bodega->id}/readings")
            ->assertForbidden();

        $this->actingAs($auxiliar)
            ->getJson("/api/bodegas/{$bodega->id}/alerts")
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // medico: consulta inventario, no da de alta nada
    // ------------------------------------------------------------------

    public function test_medico_consulta_inventario_y_reportes(): void
    {
        $medico = $this->usuario('medico');

        $this->actingAs($medico)->getJson('/api/bodegas')->assertOk();
        $this->actingAs($medico)->getJson('/api/products')->assertOk();
        $this->actingAs($medico)->getJson('/api/lots')->assertOk();
        $this->actingAs($medico)->getJson('/api/reportes/inventario')->assertOk();
    }

    public function test_medico_no_administra_grupos_ni_sensores(): void
    {
        $medico = $this->usuario('medico');

        $this->actingAs($medico)->getJson('/api/groups')->assertForbidden();
        $this->actingAs($medico)->getJson('/api/sensors')->assertForbidden();
    }

    public function test_medico_no_crea_registros(): void
    {
        $medico = $this->usuario('medico');

        $this->actingAs($medico)
            ->postJson('/api/bodegas', ['name' => 'Bodega Medico'])
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // auditor: solo lectura en todo
    // ------------------------------------------------------------------

    public function test_auditor_consulta_los_seis_modulos(): void
    {
        $auditor = $this->usuario('auditor');

        $this->actingAs($auditor)->getJson('/api/bodegas')->assertOk();
        $this->actingAs($auditor)->getJson('/api/groups')->assertOk();
        $this->actingAs($auditor)->getJson('/api/products')->assertOk();
        $this->actingAs($auditor)->getJson('/api/lots')->assertOk();
        $this->actingAs($auditor)->getJson('/api/sensors')->assertOk();
        $this->actingAs($auditor)->getJson('/api/reportes/inventario')->assertOk();
    }

    public function test_auditor_no_escribe_en_ningun_modulo(): void
    {
        $auditor = $this->usuario('auditor');
        $bodega = Bodega::factory()->create();

        $this->actingAs($auditor)
            ->postJson('/api/bodegas', ['name' => 'Bodega Auditor'])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->postJson('/api/groups', ['name' => 'Grupo Auditor'])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->deleteJson("/api/bodegas/{$bodega->id}")
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // El bypass de admin no se extiende a las rutas
    // ------------------------------------------------------------------

    public function test_sin_token_todas_las_rutas_devuelven_401(): void
    {
        foreach ([
            '/api/bodegas',
            '/api/groups',
            '/api/products',
            '/api/lots',
            '/api/sensors',
            '/api/reportes/inventario',
        ] as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
    }

    /**
     * Las rutas que alimentan formularios siguen existiendo.
     *
     * Reorganizar routes/api.php por modulo es el tipo de cambio en el que se
     * pierde una ruta sin que nadie lo note hasta que un formulario deja de
     * cargar. Estos dos select son los que consumen los formularios de
     * productos y de lotes.
     */
    public function test_los_select_de_grupos_y_unidades_siguen_disponibles(): void
    {
        $admin = $this->usuario('admin');

        $this->actingAs($admin)->getJson('/api/groups/select')->assertOk();
        $this->actingAs($admin)->getJson('/api/units/select')->assertOk();
    }

    public function test_los_form_data_de_productos_y_lotes_siguen_disponibles(): void
    {
        $admin = $this->usuario('admin');

        $this->actingAs($admin)->getJson('/api/products/form-data')->assertOk();
        $this->actingAs($admin)->getJson('/api/lots/form-data')->assertOk();
        $this->actingAs($admin)->getJson('/api/sensors/form-data')->assertOk();
    }

    public function test_un_usuario_sin_permisos_recibe_403_no_404(): void
    {
        // La diferencia importa: 401 seria un problema de autenticacion y 404
        // un problema de direccion. Un 403 dice que el usuario esta
        // identificado y la operacion no le corresponde.
        $this->actingAs($this->usuario('auditor'))
            ->deleteJson('/api/bodegas/1')
            ->assertForbidden();
    }

    /**
     * El permiso se comprueba antes de resolver el modelo.
     *
     * Por defecto Laravel ejecuta SubstituteBindings antes que los
     * middlewares de ruta, con lo que un id inexistente respondia 404 y uno
     * existente 403. Eso le permitsia a un usuario sin permiso deducir
     * cuales bodegas existen. Con EnsureModule por delante de
     * SubstituteBindings la respuesta es 403 en ambos casos.
     */
    public function test_el_permiso_se_comprueba_antes_de_resolver_el_modelo(): void
    {
        $auditor = $this->usuario('auditor');
        $bodega = Bodega::factory()->create();

        // Bodega que existe.
        $this->actingAs($auditor)
            ->deleteJson("/api/bodegas/{$bodega->id}")
            ->assertForbidden();

        // Bodega que no existe. Mismo codigo que el caso anterior.
        $this->actingAs($auditor)
            ->deleteJson('/api/bodegas/00000000-0000-0000-0000-000000000000')
            ->assertForbidden();

        // Y al reves: un rol CON permiso de borrado recibe 404, lo que
        // confirma que el 403 de arriba viene del permiso y no del binding.
        // Se usa el regente y no el admin porque el administrador no tiene
        // can_delete sobre stock: su escritura esta en bodegas y catalogos.
        $this->actingAs($this->usuario('regente_farmacia'))
            ->deleteJson('/api/bodegas/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    /**
     * Lote valido dentro de una bodega, para las pruebas que necesitan un id
     * real que exista (los 404 del controller confoundiran el 403 del
     * middleware si no).
     *
     * HU-08 (RN-01): el lote ya no tiene bodega. La pertenencia se establece
     * creando una existencia que apunte al lote en la bodega dada, que es lo
     * que el dominio considera "el lote esta en esta bodega".
     */
    private function loteDeEjemplo(Bodega $bodega): \App\Models\Lot
    {
        $lote = \App\Models\Lot::factory()->create();

        \App\Models\Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
        ]);

        return $lote;
    }
}
