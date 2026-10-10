<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Pruebas del control de acceso por rol.
 *
 * El enunciado define las capacidades de cada rol en la seccion 3 y exige
 * "autorizacion por rol con Policies o Gates" en la parte A. Estas pruebas
 * fijan el contrato de esa seccion: si alguien cambia un rol, el test falla.
 */
class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los Gates de capacidad se resuelven contra la tabla permissions.
        // Con RefreshDatabase la base queda vacia, asi que sin sembrar el
        // catalogo y la matriz ningun permiso existiria y todas las
        // capacidades se denegarian.
        $this->seed(RoleSeeder::class);
        $this->seed(ModuleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    // ------------------------------------------------------------------
    // Estructura del dato
    // ------------------------------------------------------------------

    public function test_el_rol_se_crea_por_defecto_como_auxiliar(): void
    {
        $usuario = User::factory()->create();

        $this->assertSame('auxiliar_farmacia', $usuario->role->name);
    }

    // ------------------------------------------------------------------
    // admin: solo lo que la matriz le da
    // ------------------------------------------------------------------

    /**
     * El administrador ya no pasa todo.
     *
     * Antes Gate::before devolvia true para cualquier admin. Eso rompia RN-05
     * y RN-08: el administrador terminaba siendo el segundo control que el
     * enunciado exige que sea un regente, y podia aprobar su propio traslado.
     * Ahora la matriz es la unica fuente.
     */
    public function test_admin_solo_pasa_las_capacidades_de_su_matriz(): void
    {
        $admin = User::factory()->admin()->create();

        // Administrativas: las tiene, es para eso que existe el rol.
        $this->assertTrue(Gate::forUser($admin)->allows('gestionar-usuarios'));
        $this->assertTrue(Gate::forUser($admin)->allows('gestionar-catalogos'));

        // Clinicas: no las tiene, y por eso el bypass no hace falta. RN-05 y
        // RN-08 exigen que el segundo control sea un regente, no un admin.
        $this->assertFalse(Gate::forUser($admin)->allows('aprobar-traslado'));
        $this->assertFalse(Gate::forUser($admin)->allows('autorizar-control-especial'));
    }

    // ------------------------------------------------------------------
    // regente_farmacia: aprueba, autoriza y ajusta
    // ------------------------------------------------------------------

    public function test_regente_puede_aprobar_traslados_y_autorizar_control_especial(): void
    {
        $regente = User::factory()->regente()->create();

        $this->assertTrue(Gate::forUser($regente)->allows('aprobar-traslado'));
        $this->assertTrue(Gate::forUser($regente)->allows('autorizar-control-especial'));
        $this->assertTrue(Gate::forUser($regente)->allows('ajustar-inventario'));
    }

    public function test_regente_no_gestiona_usuarios_ni_catalogos(): void
    {
        $regente = User::factory()->regente()->create();

        $this->assertFalse(Gate::forUser($regente)->allows('gestionar-usuarios'));
        $this->assertFalse(Gate::forUser($regente)->allows('gestionar-catalogos'));
    }

    // ------------------------------------------------------------------
    // auxiliar_farmacia: opera pero no aprueba
    // ------------------------------------------------------------------

    public function test_auxiliar_puede_dispensar_y_gestionar_traslados_pero_no_aprobarlos(): void
    {
        $auxiliar = User::factory()->auxiliar()->create();

        $this->assertTrue(Gate::forUser($auxiliar)->allows('dispensar'));
        $this->assertTrue(Gate::forUser($auxiliar)->allows('crear-traslado'));
        $this->assertTrue(Gate::forUser($auxiliar)->allows('recibir-traslado'));

        // RN-07 y RN-08: el auxiliar no aprueba traslados.
        $this->assertFalse(Gate::forUser($auxiliar)->allows('aprobar-traslado'));
    }

    // ------------------------------------------------------------------
    // medico
    // ------------------------------------------------------------------

    public function test_medico_crea_prescripciones_pero_no_dispensa(): void
    {
        $medico = User::factory()->medico()->create();

        $this->assertTrue(Gate::forUser($medico)->allows('crear-prescripcion'));
        $this->assertTrue(Gate::forUser($medico)->allows('consultar-pacientes'));
        $this->assertFalse(Gate::forUser($medico)->allows('dispensar'));
    }

    // ------------------------------------------------------------------
    // auditor: solo lectura y enmascarado (RN-10)
    // ------------------------------------------------------------------

    public function test_auditor_solo_lectura_con_datos_enmascarados(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->assertTrue(Gate::forUser($auditor)->allows('consultar-inventario'));
        $this->assertTrue(Gate::forUser($auditor)->allows('consultar-pacientes-enmascarados'));

        // No puede modificar nada.
        $this->assertFalse(Gate::forUser($auditor)->allows('dispensar'));
        $this->assertFalse(Gate::forUser($auditor)->allows('crear-traslado'));
        $this->assertFalse(Gate::forUser($auditor)->allows('crear-prescripcion'));
    }
}
