<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * La lista por defecto de Laravel trae SubstituteBindings antes que
     * cualquier middleware de ruta, y eso hace que el binding de modelo se
     * resuelva antes de la comprobacion de permisos. Un DELETE sobre un id
     * inexistente devolvia 404 en vez de 403, con lo cual un usuario sin
     * permiso distinguia "el registro existe" de "no existe" sondeando ids.
     *
     * Poner los middlewares de autorizacion delante de SubstituteBindings
     * invierte el orden: primero se decide si la operacion esta permitida y
     * despues se resuelve el modelo. Un usuario sin permiso recibe siempre 403,
     * exista o no el registro.
     *
     * La posicion importa por los dos lados. Antes de AuthenticatesRequests
     * devolveria 403 en vez de 401 cuando no hay token, porque todavia no se
     * sabe si es un fallo de autenticacion o de autorizacion. Despues de
     * SubstituteBindings no arreglaria nada, que es justo lo que viene a
     * resolver. Va entre los dos: autenticar, autorizar, y recien ahi resolver.
     *
     * La lista completa se replica a proposito: declararla reemplaza la del
     * framework en lugar de completarla, asi que omitir las entradas del
     * framework cambiaria el orden de EncryptCookies, StartSession y las
     * demas. Ver HU-07.
     *
     * @var string[]
     */
    protected $middlewarePriority = [
        \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
        \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
        \App\Http\Middleware\EnsureModule::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Auth\Middleware\Authorize::class,
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        // Control de acceso por modulo, con el permiso deducido del verbo HTTP.
        // Sustituye al middleware EnsureRole, que se elimino: ninguna ruta lo
        // usaba y su modelo de autorizacion (por rol, sin granularidad por
        // verbo) era incompatible con este. Ver HU-07.
        'module' => \App\Http\Middleware\EnsureModule::class,
    ];
}
