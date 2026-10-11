<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Linea de detalle de un traslado (HU-09, RN-07).
 *
 * Cada fila representa una existencia (inventario) que se mueve y en
 * que cantidad. El encabezado del traslado vive en Transfer.
 *
 * HU-09 introduce tres quantities con semantica distinta:
 *
 *   - requested_quantity: lo que el auxiliar pidio al solicitar.
 *   - approved_quantity:  lo que el regente aprobo (puede ser menor).
 *                       Nullable hasta que se aprueba.
 *   - delivered_quantity: lo que efectivamente se entrego en destino.
 *                        Default 0; se llena al ejecutar receive().
 *
 * RN-01 (HU-08): la cantidad solicitada no puede superar el quantity
 * del inventario en origen. Esa validacion vive en TransferService,
 * dentro de DB::transaction con SELECT FOR UPDATE.
 *
 * RN-07: si delivered < approved, la diferencia queda como discrepancia
 * (tabla transfer_discrepancies) y el traslado pasa a RECIBIDO_PARCIAL.
 * El resolver de la discrepancia es una operacion separada y NO devuelve
 * stock automaticamente: la correccion se hace por ajuste en Kardex.
 *
 * @property string $id
 * @property string $transfer_id
 * @property string $inventory_id
 * @property int $requested_quantity
 * @property int|null $approved_quantity
 * @property int $delivered_quantity
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
        'requested_quantity',
        'approved_quantity',
        'delivered_quantity',
    ];

    protected $casts = [
        'requested_quantity' => 'integer',
        'approved_quantity' => 'integer',
        'delivered_quantity' => 'integer',
    ];

    protected $attributes = [
        'delivered_quantity' => 0,
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

    /**
     * Cantidad pendiente de recibir.
     *
     * approved_quantity - delivered_quantity. Cero cuando el item se
     * recibio completo, > 0 cuando esta parcial, = approved cuando
     * esta pendiente de recibir. Si aun no se aprobo (approved_quantity
     * es null), se considera pendiente contra el requested.
     */
    public function pendiente(): int
    {
        $aprobado = $this->approved_quantity ?? $this->requested_quantity;

        return $aprobado - $this->delivered_quantity;
    }

    /**
     * True si el item fue recibido completo:
     * delivered_quantity >= approved_quantity.
     */
    public function recibidoCompleto(): bool
    {
        if ($this->approved_quantity === null) {
            return false;
        }

        return $this->delivered_quantity >= $this->approved_quantity;
    }

    /**
     * True si delivered < approved, lo cual genera una discrepancia.
     */
    public function tieneDiscrepancia(): bool
    {
        if ($this->approved_quantity === null) {
            return false;
        }

        return $this->delivered_quantity < $this->approved_quantity;
    }
}
