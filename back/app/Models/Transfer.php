<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Traslado de mercancia entre bodegas (HU-09, RN-07).
 *
 * El encabezado del documento. El detalle de que existencias se mueven
 * y en que cantidades vive en TransferItem. La maquina de estados
 * (DRAFT, REQUESTED, APPROVED, IN_TRANSIT, RECEIVED,
 * PARTIALLY_RECEIVED, CANCELLED) se valida en TransferService: el
 * modelo es solo el contenedor de la fila.
 *
 * Decisiones de modelado:
 *
 *   - El estado no es un string: es una FK a transfer_statuses (decision
 *     D3 del proyecto). Los flags is_cancellable e is_terminal viven
 *     en la base, no en codigo.
 *
 *   - Una columna por actor (requested_by, approved_by, dispatched_by,
 *     received_by, cancelled_by). Cada transicion registra QUIEN la
 *     hizo y CUANDO (dispatched_at, received_at, cancelled_at). Para
 *     una auditoria cinco anos despues, responder "quien aprobo el
 *     traslado del 12 de marzo" es un SELECT directo.
 *
 *   - transfer_number (TRF-YYYYMMDD-XXXX) es UNIQUE y se genera en
 *     el creating() del modelo. RACE CONDITION CONOCIDA: el calculo
 *     del siguiente numero es MAX(correlativo_hoy) + 1, no atomico. La
 *     UNIQUE constraint rechaza la segunda solicitud concurrente con
 *     un error 500 transitorio, que es el comportamiento aceptable:
 *     no se crea un traslado invisible con correlativo duplicado.
 *
 *   - SoftDeletes: el borrado logico es para errores de operador. La
 *     cancelacion del flujo (estado CANCELLED) NO es un soft delete.
 *
 *   - requested_by se llena en el creating() del modelo, con el
 *     usuario autenticado. Los demas actores se llenan desde
 *     TransferService al ejecutar cada transicion.
 *
 * @property string $id
 * @property string $transfer_number
 * @property string $from_warehouse_id
 * @property string $to_warehouse_id
 * @property string $status_id
 * @property int $requested_by
 * @property int|null $approved_by
 * @property int|null $dispatched_by
 * @property int|null $received_by
 * @property int|null $cancelled_by
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
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
        'requested_by',
        'approved_by',
        'dispatched_by',
        'received_by',
        'cancelled_by',
        'dispatched_at',
        'received_at',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'from_warehouse_id');
    }

    public function bodegaDestino(): BelongsTo
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

    public function discrepancies(): HasMany
    {
        return $this->hasMany(TransferDiscrepancy::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Genera el correlativo del dia: TRF-YYYYMMDD-0001.
     *
     * RACE CONDITION CONOCIDA: leer el ultimo numero y sumarle uno no es
     * atomico. Dos solicitudes simultaneas pueden leer el mismo maximo y
     * generar el mismo numero; en ese caso la UNIQUE constraint de
     * transfer_number rechaza la segunda. El usuario ve un error 500 en
     * vez de un correlativo duplicado, que es el fallo correcto: no se
     * crea un traslado invisible.
     *
     * La alternativa robusta es un contador en base con UPDATE...OUTPUT
     * que serializa las escrituras. Se dejo fuera porque los traslados
     * no son una operacion de alta frecuencia.
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

            if (empty($model->requested_by) && auth()->check()) {
                $model->requested_by = auth()->id();
            }

            // Estado inicial: si llega sin status_id, le asignamos
            // DRAFT. Una fila sin estado no es un traslado valido.
            if (empty($model->status_id)) {
                $draft = TransferStatus::porNombre('DRAFT');
                if ($draft !== null) {
                    $model->status_id = $draft->id;
                }
            }
        });
    }

    /**
     * Al soft-delete un traslado, se borran logicamente sus lineas y
     * sus discrepancias (las dos tienen onDelete CASCADE en la BD; en
     * el modelo lo reflejamos para que el borrado en cascada sea
     * simetrico, y para que un listado de TransferItem que use whereTrashed
     * no muestre items de un traslado soft-deleted).
     */
    public function delete(): ?bool
    {
        $this->items()->delete();
        $this->discrepancies()->delete();

        return parent::delete();
    }
}
