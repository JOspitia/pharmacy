<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando un movimiento dejaria el stock de una existencia en negativo.
 *
 * Excepcion propia y no un RuntimeException generico porque el controller la
 * traduce a un 422 con un mensaje util ("stock insuficiente: hay N
 * disponibles"). Un error generico se traducía a un 500 opaco, que es
 * justamente la respuesta que el brief califica de incomprensible.
 */
class StockInsuficienteException extends RuntimeException
{
}