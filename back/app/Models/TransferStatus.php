<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estado de un traslado (RN-07, decision D3).
 *
 * Catalogo de datos, no enum en codigo. Deliberadamente NO hay constantes como
 * TransferStatus::APPROVED ni una lista ESTADOS: el catalogo vive unicamente en
 * la tabla transfer_statuses, que es la fuente de verdad.
 *
 * Por que importa: si el catalogo estuviera tambien en PHP, habria dos listas
 * que pueden divergir. El estado que existe en el codigo pero no en la base
 * rompe el traslado en runtime con un error de FK, y el que existe en la base
 * pero no en el codigo es inalcanzable desde el ORM. Con una sola fuente, el
 * error aparece al sembrar, no a mitad de una operacion.
 *
 * Para comparar contra un estado puntual se consulta por name:
 *
 *     $transfer->status->where('name', 'APPROVED')->exists()
 *
 * o, mejor, se usan los metodos de instancia que leen los flags:
 *
 *     $transfer->status->sePuedeAnular()
 *
 * is_cancellable e is_terminal no son decoration. Anular es valido desde
 * DRAFT y REQUESTED porque antes de despachar la mercancia no salio de la
 * bodega de origen y no hay stock que revertir. Despues del despacho, anular
 * ya no es un cambio de estado: es una devolucion, que es otro proceso.
 *
 * @property string $name      identificador tecnico, en ingles
 * @property string $label     texto de interfaz, en espanol
 * @property bool $is_cancellable
 * @property bool $is_terminal
 */
class TransferStatus extends Model
{
    /** UUID como clave primaria, no auto-increment. */
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['name', 'label', 'is_cancellable', 'is_terminal'];

    protected $casts = [
        'is_cancellable' => 'boolean',
        'is_terminal' => 'boolean',
    ];

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    /**
     * Busca un estado por su identificador tecnico.
     *
     * Es la forma de obtener un estado sin constantes. Devuelve null si no
     * existe, y el llamador decide que hacer: casi siempre es un dato
     * corrupto y corresponde un 500 explicito, no un silencio.
     */
    public static function porNombre(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    public function sePuedeAnular(): bool
    {
        return $this->is_cancellable;
    }

    public function esTerminal(): bool
    {
        return $this->is_terminal;
    }
}