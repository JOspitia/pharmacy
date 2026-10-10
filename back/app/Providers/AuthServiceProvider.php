<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * No hay Policies porque no hay un recurso propietario que proteger: las
     * preguntas son de capacidad (¿puede aprobar un traslado?), no de modelo
     * concreto (¿puede editar ESTE producto?). Eso lo resuelven los Gate.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Capacidades por rol, tomadas literalmente de la seccion 3 del enunciado.
     *
     * @var array<string, array<int, string>>
     */
    private const CAPACIDADES = [
        // auxiliar_farmacia y regente_farmacia comparten la base
        'dispensar'                  => ['auxiliar_farmacia', 'regente_farmacia'],
        'consultar-inventario'       => ['auxiliar_farmacia', 'regente_farmacia', 'medico', 'auditor'],
        'crear-traslado'             => ['auxiliar_farmacia', 'regente_farmacia'],
        'recibir-traslado'           => ['auxiliar_farmacia', 'regente_farmacia'],

        // exclusivas del regente
        'aprobar-traslado'           => ['regente_farmacia'],
        'autorizar-control-especial' => ['regente_farmacia'],
        'ajustar-inventario'         => ['regente_farmacia'],

        // medico
        'crear-prescripcion'         => ['medico'],
        'consultar-pacientes'        => ['medico'],

        // auditor: solo lectura y con los datos enmascarados (RN-10)
        'consultar-pacientes-enmascarados' => ['auditor'],

        // admin
        'gestionar-usuarios'         => ['admin'],
        'gestionar-catalogos'        => ['admin'],
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot()
    {
        $this->registerPolicies();

        foreach (self::CAPACIDADES as $capacidad => $roles) {
            Gate::define($capacidad, fn (User $user): bool => $user->hasAnyRole($roles));
        }

        // admin satisface cualquier capacidad, incluidas las que no esten
        // enumeradas arriba. Devolver null devuelve el control al Gate normal,
        // que es lo que permite que un no-admin siga evaluandose por rol.
        Gate::before(fn (User $user): ?bool => $user->isAdmin() ? true : null);
    }
}