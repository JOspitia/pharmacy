<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica que el administrador NO pueda saltarse los controles de segregacion.
 *
 * RN-05 exige que el control especial lo autorice un segundo usuario con rol
 * regente_farmacia. Si admin pudiera hacerlo, el doble control desaparece.
 *
 * Este test esta pensado para FALLAR con la implementacion actual, que tiene
 * Gate::before devolviendo true para admin.
 */
class AdminNoAutorizaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_no_debe_poder_autorizar_control_especial(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse(
            $admin->can('autorizar-control-especial'),
            'RN-05: solo un regente_farmacia puede autorizar control especial. '
            . 'Un admin autorizando deja el control sin segundo usuario.'
        );
    }

    public function test_admin_no_debe_poder_aprobar_traslados(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse(
            $admin->can('aprobar-traslado'),
            'RN-08: la aprobacion de traslados es exclusiva del regente_farmacia.'
        );
    }
}