<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Movimiento del Kardex (RN-06).
 *
 * INMUTABLE. Tres capas lo garantizan, y las tres hacen falta:
 *
 *   1. No tiene updated_at, asi que no hay donde dejar la hora de un cambio.
 *   2. No tiene deleted_at ni SoftDeletes, asi que el ORM no puede borrarlo.
 *   3. Un trigger en la base (trg_movements_inmutable) rechaza UPDATE y
 *      DELETE incluso si alguien escribe SQL a mano.
 *
 * La capa 3 es la que importa de verdad: las dos primeras son convenciones que
 * un UPDATE directo ignora sin enterarse.
 *
 * quantity es el delta aplicado a la existencia: positivo suma, negativo
 * resta. Guardar el signo permite releer el kardex sin consultar el estado
 * actual del inventario, que para cuando se consulta ya habra cambiado.
 *
 * @property string $id
 * @property string $inventory_id
 * @property string $movement_type_id
 * @property int $quantity
 * @property string|null $reason
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string $performed_by
 */
class Movement extends Model
{
    use HasFactory;

    /** UUID como clave primaria, no auto-increment. */
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'inventory_id',
        'movement_type_id',
        'quantity',
        'reason',
        'reference_type',
        'reference_id',
        'performed_by',
    ];

    /**
     * timestamps parcial: solo created_at.
     *
     * const UPDATED_AT en null le dice a Eloquent que no hay columna de
     * actualizacion. Sin esto Laravel intentaria escribir updated_at en cada
     * save() y la base lo rechazaria.
     */
    public const UPDATED_AT = null;

    /**
     * Un movimiento no se modifica ni se borra. Los overrides no son
     * decorativos: si alguien llama a update() esperando que funcione, que
     * falle ruidosamente en el modelo es mejor que fallar en la base con un
     * error de trigger que nadie entiende.
     *
     * @throws \LogicException
     */
    protected static function booted(): void
    {
        static::creating(function (Movement $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });

        static::updating(function (): void {
            throw new LogicException('RN-06: los movimientos del kardex son inmutables. Para corregir, registre un movimiento de ajuste con motivo.');
        });

        static::deleting(function (): void {
            throw new LogicException('RN-06: los movimientos del kardex no se borran. Para corregir, registre un movimiento de ajuste con motivo.');
        });
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(MovementType::class, 'movement_type_id');
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // Sin deleted_at ni SoftDeletes: RN-06 prohibe borrar. Los overrides de
    // updating/deleting son la capa 2 de 3; la 3 es el trigger en la base.

}