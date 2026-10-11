<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Discrepancia de recepcion de un traslado (HU-09, RN-07).
 *
 * RN-07: "Lo no recibido queda como discrepancia pendiente de
 * resolucion". Se crea una fila por cada item donde
 * delivered_quantity < approved_quantity al ejecutar receive().
 *
 * Resolver una discrepancia es una operacion separada y NO devuelve
 * stock automaticamente: la correccion se hace por ajuste en Kardex
 * (RN-06). Esta tabla solo REGISTRA que hay una diferencia, no la
 * corrige.
 *
 * El campo difference se persiste y se valida en la base con un CHECK
 * >= 0. Es la diferencia entre approved y delivered: cuando llega
 * delivered=0 y approved=50, difference=50; cuando llega delivered=30
 * y approved=50, difference=20. La diferencia puede ser 0 solo si
 * delivered == approved, lo cual NO genera una discrepancia.
 *
 * Status: PENDING (recien creada), RESOLVED (se decidio que hacer
 * con la diferencia), WRITTEN_OFF (se asumio la perdida sin reponer
 * stock). Las transiciones entre estados las gestiona el
 * TransferService::resolverDiscrepancia(), no este modelo.
 *
 * @property string $id
 * @property string $transfer_id
 * @property string $transfer_item_id
 * @property int $approved_quantity
 * @property int $delivered_quantity
 * @property int $difference
 * @property string $status
 * @property string|null $resolution_notes
 * @property int|null $resolved_by
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class TransferDiscrepancy extends Model
{
    use HasFactory;

    /** UUID como clave primaria, no auto-increment. */
    public $incrementing = false;
    protected $keyType = 'string';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_WRITTEN_OFF = 'WRITTEN_OFF';

    protected $fillable = [
        'transfer_id',
        'transfer_item_id',
        'approved_quantity',
        'delivered_quantity',
        'difference',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'approved_quantity' => 'integer',
        'delivered_quantity' => 'integer',
        'difference' => 'integer',
        'resolved_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected static function booted(): void
    {
        static::creating(function (TransferDiscrepancy $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }

            if (empty($model->status)) {
                $model->status = self::STATUS_PENDING;
            }
        });
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(TransferItem::class, 'transfer_item_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }
}
