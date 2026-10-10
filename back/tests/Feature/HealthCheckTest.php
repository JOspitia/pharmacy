<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_devuelve_200_sin_autenticacion(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_ready_devuelve_200_cuando_la_base_responde(): void
    {
        $response = $this->getJson('/api/ready');

        $response->assertOk()
            ->assertJson(['status' => 'ok'])
            ->assertJson(['database' => 'connected']);
    }

    public function test_health_no_requiere_token(): void
    {
        // Sin header de Authorization, sin nada. El balanceador llama directo.
        $response = $this->getJson('/api/health');

        $response->assertOk();
    }

    public function test_ready_no_requiere_token(): void
    {
        $response = $this->getJson('/api/ready');

        $response->assertOk();
    }
}
