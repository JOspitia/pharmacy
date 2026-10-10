<?php

namespace Tests\Feature;

use App\Models\User;
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

    // ------------------------------------------------------------------
    // Estructura del dato
    // ------------------------------------------------------------------

    public function test_el_rol_se_crea_por_defecto_como_auxiliar(): void
    {
        $usuario = User::factory()->create();

        $this->assertSame('auxiliar_farmacia', $usuario->role);
    }

    public function test_has_any_role_reconoce_solo_los_indicados(): void
    {
        $regente = User::factory()->regente()->create();

        $this->assertTrue($regente->hasAnyRole(['regente_farmacia']));
        $this->assertTrue($regente->hasAnyRole(['medico', 'regente_farmacia']));
        $this->assertFalse($regente->hasAnyRole(['medico', 'auditor']));
    }

    // ------------------------------------------------------------------
    // admin: satisface todo
    // ------------------------------------------------------------------

    public function test_admin_pasa_cualquier_comprobacion_de_rol(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Gate::forUser($admin)->allows('aprobar-traslado'));
        $this->assertTrue(Gate::forUser($admin)->allows('gestionar-usuarios'));
        // Capacidad no enumerada: admin debe pasar igual.
        $this->assertTrue(Gate::forUser($admin)->allows('capacidad-inexistente'));
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