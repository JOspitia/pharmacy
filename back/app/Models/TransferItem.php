<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Linea de detalle de un traslado (RN-07).
 *
 * Representa que existencia (inventario) se mueve y en que cantidad. El encabezado
 * del traslado vive en Transfer.
 *
 * @property string $id
 * @property string $transfer_id
 * @property string $inventory_id
 * @property int $quantity
 * @property int $received_quantity
 */
class TransferItem extends Model
{
    use HasFactory, SoftDeletes;

    /** UUID como clave primaria, no auto-increment. */
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'transfer_id',
        'inventory_id',
        'quantity',
        'received_quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'received_quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (TransferItem $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }
}
