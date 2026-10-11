<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        StockInsuficienteException::class,
    ];

    /**
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Traduccion de excepciones de dominio a HTTP.
     *
     * Las excepciones de negocio (StockInsuficienteException y los
     * RuntimeException que KardexService / MovementService lanzan por
     * estado invalido del kardex) se traducen a 422 con un mensaje util.
     * El controller no atrapa nada: delega a este registro y queda con
     * unicamente logica HTTP.
     */
    public function register()
    {
        // Stock insuficiente (HU-08, KT-01/KT-10). 422 porque el request
        // es valido pero la operacion no se puede completar.
        //
        // Los demas RuntimeException que KardexService lanza (KE-03 bodega
        // inconsistente, tipo invalido) se traducen a 422 via
        // KardexStateException, una clase propia que no tiene relacion de
        // herencia con ModelNotFoundException. Asi Laravel puede hacer su
        // conversion automatica de ModelNotFoundException a 404 sin que
        // este catch-all lo intercepte.
        $this->renderable(function (StockInsuficienteException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        });

        $this->renderable(function (KardexStateException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
