<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Estado invalido del Kardex (HU-08).
 *
 * Se lanza cuando un movimiento es semanticamente valido pero el estado
 * actual de la base no lo permite: bodega inconsistente con la existencia
 * resuelta (KE-03), tipo de movimiento desconocido, o cualquier otra
 * inconsistencia que no sea "no hay stock" (eso tiene su propia
 * StockInsuficienteException).
 *
 * Hereda de RuntimeException para mantener la firma, pero NO comparte
 * herencia con ModelNotFoundException: el Handler puede traducir esta a
 * 422 sin tocar la conversion automatica de ModelNotFoundException a 404.
 */
class KardexStateException extends RuntimeException
{
}
