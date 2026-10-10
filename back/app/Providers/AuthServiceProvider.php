<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * No hay Policies porque no hay un recurso propietario que proteger: las
     * preguntas son de capacidad (¿puede aprobar un traslado?) y de modulo
     * (¿puede crear bodegas?), no de modelo concreto (¿puede editar ESTE
     * producto?). Eso lo resuelven Gate y EnsureModule.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * ANTES: las capacidades vivian en CAPACIDADES, un array constante de PHP
     * con la lista de roles de cada una. Eso era una segunda fuente de verdad
     * junto a la matriz de la base, y obligaba a agregar una capacidad
     * editando codigo y desplegando. Ahora cada capacidad es una fila en
     * permissions.
     *
     * POR QUE Gate::before Y NO Gate::define EN UN BUCLE: la alternativa
     * obvia es recorrer Permission::pluck('name') en boot() y definir un Gate
     * por permiso. No funciona: boot() corre en CADA arranque, incluidos los
     * comandos artisan y el package:discover que dispara composer dump-autoload
     * durante el build de la imagen, donde no hay base de datos. El provider
     * reventaba el build entero.
     *
     * Gate::before resuelve lo mismo de forma perezosa: intercepta la
     * capacidad cuando se consulta y la busca en ese momento.
     *
     * POR QUE NO HAY BYPASS DE ADMIN (el cambio mas importante de aqui):
     * Habia Gate::before con $user->isAdmin() ? true : null, que hacia que el
     * administrador pasara CUALQUIER capacidad sin enumerarla. Eso rompe dos
     * reglas del enunciado:
     *
     *   - RN-05 exige que el control especial lo autorice "un segundo usuario
     *     con rol regente_farmacia". Con el bypass, un admin autorizaba sin
     *     ser regente y el doble control se convertia en un mono control.
     *   - RN-08 exige segregacion de funciones: quien solicita no aprueba.
     *     Con el bypass, el admin podia aprobar su propio traslado.
     *
     * Se podia haber conservado el bypass con una lista negra de capacidades
     * sensibles. Se descarto porque esa lista se queda corta sola: la proxima
     * capacidad sensible nace sin proteccion y nadie se acuerda de agregarla.
     * Sin bypass, admin no tiene lo que la matriz no le da, y lo que si tiene
     * esta enumerado a la vista en RolePermissionSeeder.
     *
     * Ver PENDIENTE_auditoria.md, seccion "Gate::before de admin rompe RN-05".
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function (User $user, string $ability): ?bool {
            // Si la capacidad no existe en el catalogo NO se responde: se
            // devuelve null para que la resolucion siga su curso y una Policy
            // de modelo, si alguna vez se agrega, pueda decidir. Devolver false
            // cortaria la cadena en seco y las Policies nunca correrian.
            if (! in_array($ability, Permission::catalogo(), true)) {
                return null;
            }

            return $user->hasPermission($ability);
        });
    }
}