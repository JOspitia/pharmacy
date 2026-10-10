<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pruebas del middleware EnsureModule.
 *
 * EnsureModule decide el permiso a partir del verbo HTTP, asi que la
 * combinacion que importa es (rol, modulo, verbo). Este archivo cubre la
 * tabla de verbos y los bordes del middleware; la matriz completa de
 * permisos rol x modulo se verifica en PermissionMatrixTest.
 *
 * Para las rutas se definen a proposito, dentro del propio test, en vez de
 * usar las de api.php: asi el middleware se prueba aislado y un cambio en las
 * rutas reales no rompe (ni falsea) estas pruebas.
 */
class EnsureModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Module $bodegas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bodegas = Module::create([
            'name' => 'warehouses',
            'description' => 'Gestion de bodegas',
        ]);

        // Rutas de prueba que replican la forma de las reales: el grupo de
        // auth:sanctum por fuera, como en api.php, y el de module:<modulo>
        // por dentro. El orden importa para la prueba de 401: si el
        // middleware de modulo corriera sin Sanctum, un usuario sin token
        // recibiria 403 en lugar de 401.
        Route::middleware('auth:sanctum')->group(function () {
            Route::middleware('module:warehouses')->group(function () {
                Route::get('/prueba/bodegas', fn () => response()->json(['ok' => true]));
                Route::post('/prueba/bodegas', fn () => response()->json(['ok' => true]));
                Route::put('/prueba/bodegas/{id}', fn () => response()->json(['ok' => true]));
                Route::patch('/prueba/bodegas/{id}', fn () => response()->json(['ok' => true]));
                Route::delete('/prueba/bodegas/{id}', fn () => response()->json(['ok' => true]));
            });
        });
    }

    /**
     * Crea un rol con los permisos indicados sobre bodegas y un usuario que lo
     * tenga.
     *
     * @param  array<string, bool>  $permisos
     */
    private function rolCon(array $permisos, string $nombre = 'rol_prueba'): User
    {
        $rol = Role::create(['name' => $nombre, 'description' => 'Rol de prueba']);

        // Los permisos se crean fila a fila porque este test no corre los
        // seeders: necesita una matriz minima y controlada, no la del
        // enunciado. Solo se adjuntan los que estan en true, porque la
        // ausencia de fila ES el permiso denegado.
        $ids = [];

        foreach (RolePermissionSeeder::ACCIONES as $accion) {
            if (! ($permisos[$accion] ?? false)) {
                continue;
            }

            $permiso = Permission::firstOrCreate(
                ['name' => Permission::crudName('warehouses', $accion)],
                ['module_id' => $this->bodegas->id, 'description' => 'Permiso de prueba']
            );

            $ids[] = $permiso->id;
        }

        $rol->permissions()->syncWithoutDetaching($ids);

        return User::factory()->conRol($nombre)->create();
    }

    // ------------------------------------------------------------------
    // Correspondencia verbo -> permiso
    // ------------------------------------------------------------------

    public function test_get_exige_can_read(): void
    {
        $this->actingAs($this->rolCon(['can_read' => true]))
            ->getJson('/prueba/bodegas')
            ->assertOk();
    }

    public function test_get_se_deniega_sin_can_read(): void
    {
        $this->actingAs($this->rolCon(['can_create' => true, 'can_update' => true]))
            ->getJson('/prueba/bodegas')
            ->assertForbidden();
    }

    public function test_post_exige_can_create(): void
    {
        $this->actingAs($this->rolCon(['can_create' => true]))
            ->postJson('/prueba/bodegas')
            ->assertOk();
    }

    public function test_post_se_deniega_sin_can_create_aunque_pueda_leer(): void
    {
        // El caso que un middleware por rol no podia expresar: el mismo grupo de rutas
        // con lectura permitida y escritura prohibida.
        $this->actingAs($this->rolCon(['can_read' => true]))
            ->postJson('/prueba/bodegas')
            ->assertForbidden();
    }

    public function test_put_exige_can_update(): void
    {
        $this->actingAs($this->rolCon(['can_update' => true]))
            ->putJson('/prueba/bodegas/1')
            ->assertOk();
    }

    public function test_patch_exige_can_update(): void
    {
        $this->actingAs($this->rolCon(['can_update' => true]))
            ->patchJson('/prueba/bodegas/1')
            ->assertOk();
    }

    public function test_put_se_deniega_sin_can_update(): void
    {
        $this->actingAs($this->rolCon(['can_read' => true, 'can_create' => true]))
            ->putJson('/prueba/bodegas/1')
            ->assertForbidden();
    }

    public function test_delete_exige_can_delete(): void
    {
        $this->actingAs($this->rolCon(['can_delete' => true]))
            ->deleteJson('/prueba/bodegas/1')
            ->assertOk();
    }

    public function test_delete_se_deniega_sin_can_delete_aunque_tenga_los_demas(): void
    {
        $this->actingAs($this->rolCon([
            'can_read' => true,
            'can_create' => true,
            'can_update' => true,
        ]))
            ->deleteJson('/prueba/bodegas/1')
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Bordes del middleware
    // ------------------------------------------------------------------

    public function test_responde_401_sin_token(): void
    {
        $this->getJson('/prueba/bodegas')->assertUnauthorized();
    }

    public function test_el_403_informa_modulo_y_permiso_requerido(): void
    {
        $this->actingAs($this->rolCon([]))
            ->deleteJson('/prueba/bodegas/1')
            ->assertForbidden()
            ->assertJson([
                'message' => 'No tiene permisos para realizar esta accion.',
                'module' => 'warehouses',
                // El nombre completo del permiso, ya no el sufijo suelto: el
                // 403 dice exactamente que sepidio para poder diagnosticar.
                'required_permission' => 'warehouses.can_delete',
            ]);
    }

    public function test_rol_sin_fila_en_la_matriz_no_accede_a_nada(): void
    {
        // El rol existe pero no tiene ninguna fila en role_permissions. No es
        // lo mismo que tener los permisos en false: aqui directamente no hay
        // relacion.
        Role::create(['name' => 'sin_permisos', 'description' => 'Sin permisos']);
        $usuario = User::factory()->conRol('sin_permisos')->create();

        $this->actingAs($usuario)->getJson('/prueba/bodegas')->assertForbidden();
        $this->actingAs($usuario)->postJson('/prueba/bodegas')->assertForbidden();
    }

    public function test_permiso_de_un_modulo_no_sirve_para_otro(): void
    {
        $lotes = Module::create(['name' => 'lots', 'description' => 'Gestion de lotes']);

        $permisoLotes = Permission::firstOrCreate(
            ['name' => Permission::crudName('lots', 'can_read')],
            ['module_id' => $lotes->id, 'description' => 'Leer lotes']
        );

        Role::create(['name' => 'solo_lotes', 'description' => 'Solo lotes'])
            ->permissions()
            ->attach($permisoLotes->id);

        $usuario = User::factory()->conRol('solo_lotes')->create();

        // Puede leer lotes, pero no entra a bodegas: el permiso es del modulo
        // que dice, no un permiso global disfrazado.
        $this->actingAs($usuario)->getJson('/prueba/bodegas')->assertForbidden();
    }
}
