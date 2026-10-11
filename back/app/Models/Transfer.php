<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Solicitud de traslado de mercancia entre bodegas (RN-07).
 *
 * El encabezado. El detalle de que productos y en que cantidades viaja esta
 * en TransferItem.
 *
 * El estado no es un string: es una FK a transfer_statuses. Es lo que permite
 * que la maquina de estados consulte is_cancellable e is_terminal en vez de
 * codificarlos en un switch (decision D3).
 *
 * @property string $id
 * @property string $transfer_number
 * @property string $from_warehouse_id
 * @property string $to_warehouse_id
 * @property string $status_id
 * @property string|null $notes
 */
class Transfer extends Model
{
    use HasFactory, SoftDeletes;

    /** UUID como clave primaria, no auto-increment. */
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'status_id',
        'notes',
    ];

    public function fromBodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'from_warehouse_id');
    }

    public function toBodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'to_warehouse_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TransferStatus::class, 'status_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    /**
     * Genera el correlativo del dia: TRF-YYYYMMDD-0001.
     *
     * RACE CONDITION CONOCIDA: leer el ultimo numero y sumarle uno no es
     * atomico. Dos solicitudes simultaneas pueden leer el mismo maximo y
     * generar el mismo numero; en ese caso la constraint UNIQUE de
     * transfer_number rechaza la segunda. El usuario ve un error 422 en vez de
     * un correlativo duplicado, que es el fallo correcto: no se crea un
     * traslado invisible.
     *
     * La alternativa robusta es un contador en base con una fila por dia y
     * UPDATE ... OUTPUT, que serializa las escrituras. Se dejo fuera porque
     * los traslados no son una operacion de alta frecuencia y agregar la tabla
     * de contador es mas superficie de la que el problema real justifica.
     *
     * @return string
     */
    public static function generarNumero(): string
    {
        $hoy = now()->format('Ymd');
        $prefijo = "TRF-{$hoy}-";

        $ultimo = static::where('transfer_number', 'like', "{$prefijo}%")
            ->orderByDesc('transfer_number')
            ->value('transfer_number');

        $siguiente = $ultimo
            ? ((int) substr($ultimo, strlen($prefijo))) + 1
            : 1;

        return $prefijo . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        static::creating(function (Transfer $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }

            if (empty($model->transfer_number)) {
                $model->transfer_number = static::generarNumero();
            }
        });
    }

    /**
     * Al soft-delete un traslado, se borran logicamente sus lineas.
     */
    public function delete(): ?bool
    {
        $this->items()->delete();

        return parent::delete();
    }
}