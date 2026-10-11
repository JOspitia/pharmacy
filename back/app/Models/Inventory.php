<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Existencia de un lote en una bodega (RN-01).
 *
 * RN-01: "Cada existencia se identifica por bodega + producto + lote". Eso es
 * literalmente la constraint uq_inventories_existencia sobre la tabla.
 *
 * Antes el stock vivia en lots.stock. Eso hacia que un lote fuera una sola
 * existencia en una sola bodega, y por lo tanto no habia forma de representar
 * el mismo lote presente en dos bodegas a la vez, que es exactamente lo que un
 * traslado necesita.
 *
 * La terna se persiste completa (warehouse_id, product_id, lot_id) aunque
 * product_id se pueda derivar del lote. Es redundancia deliberada: la
 * consulta de la pantalla de inventario filtra y ordena por bodega y producto
 * en cada request, y derivarlo por join con lots en esa ruta seria el
 * cuello de botella. La base lo garantiza con la constraint unica.
 *
 * @property string $id
 * @property string $warehouse_id
 * @property string $product_id
 * @property string $lot_id
 * @property int $quantity
 * @property int|null $min_stock
 */
class Inventory extends Model
{
    use HasFactory, SoftDeletes;

    /** UUID como clave primaria, no auto-increment. */
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'warehouse_id',
        'product_id',
        'lot_id',
        'quantity',
        'min_stock',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'min_stock' => 'integer',
    ];

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relacion con el lote del que esta existencia es una foto.
     *
     * Antes se llamaba lotRel() con un alias lot() para evitar un conflicto
     * con HasFactory::setRelations(), que en algunos casos confundia el
     * nombre de la relacion con el del FK. La confusion venia de la
     * relacion inventory() del modelo Movement, no de esta: con la FK
     * explicita en el factory (Lot::factory()->create()) y el problema
     * resuelto alli, aca no hace falta un alias.
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    /**
     * Dias hasta el vencimiento del lote. Negativo significa vencido.
     *
     * diffInDays con false devuelve con signo, que es lo que se necesita:
     * true devolveria siempre el valor absoluto y un lote vencido hace 15 dias
     * se reportaria como 15 dias, que es lo contrario de lo que quiere ver
     * quien audita.
     */
    public function diasParaVencer(): ?int
    {
        if (!$this->lot?->expiration_date) {
            return null;
        }

        return (int) now()->startOfDay()
            ->diffInDays($this->lot->expiration_date->startOfDay(), false);
    }

    /**
     * Estado de vencimiento para el semÃ¡foro de la UI (RN-11).
     *
     * Los cortes son 90 y 30 dÃ­as, y 90 sale del brief: "El sistema alerta
     * lotes que vencen en 90 dÃ­as o menos".
     */
    public function estadoVencimiento(): string
    {
        $dias = $this->diasParaVencer();

        if ($dias === null) {
            return 'desconocido';
        }

        return match (true) {
            $dias < 0 => 'vencido',
            $dias <= 30 => 'critico',
            $dias <= 90 => 'proximo',
            default => 'ok',
        };
    }

    public function estaVencido(): bool
    {
        return ($this->diasParaVencer() ?? 0) < 0;
    }

    public function estaPorVencer(): bool
    {
        $dias = $this->diasParaVencer();

        return $dias !== null && $dias >= 0 && $dias <= 90;
    }

    /**
     * Debajo del minimo de esa bodega (RN-11).
     *
     * min_stock vive en la existencia y no en el producto porque el umbral es
     * por bodega: la Farmacia de Urgencias necesita 10 de un antipiretico y
     * la Central 200, del mismo producto.
     */
    public function estaBajoMinimo(): bool
    {
        return $this->min_stock !== null && $this->quantity <= $this->min_stock;
    }

    public function deficit(): ?int
    {
        if ($this->min_stock === null) {
            return null;
        }

        return $this->quantity - $this->min_stock;
    }

    protected static function booted(): void
    {
        static::creating(function (Inventory $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
