<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Modelo de bodega
 */
class Bodega extends Model
{
    // Trait para el manejo de la tabla
    use HasFactory, SoftDeletes;

    // Definir el tipo de dato de la clave primaria
    protected $keyType = 'string';

    // Deshabilitar el incremento automático
    public $incrementing = false;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'name',
        'description',
        'status',
        'stock_minimo',
        'created_by',
        'updated_by',
    ];

    // Definir el tipo de dato
    protected $casts = [
        'status' => 'boolean',
        'stock_minimo' => 'integer',
    ];

    // Valores por defecto del modelo antes de guardar en base de datos
    protected $attributes = [
        'status' => true,
        'stock_minimo' => 0,
    ];
    
    // Relaciones
    /**
     * Lotes con existencia en esta bodega.
     *
     * HU-08 (RN-01): el lote ya no tiene warehouse_id. La relacion directa
     *dbo se_expression quedó sin sentido porque un lote puede estar en varias
     * bodegas a la vez. Ahora la bodega tiene existencias, y cada existencia
     * apunta a un lote, asi que la relacion va a través de inventories.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'warehouse_id');
    }

    /**
     * Lotes presentes en esta bodega, a traves de sus existencias.
     *
     * HU-08 (RN-01): un lote ya no tiene bodega propia. La unica forma de
     * saber que lotes hay en una bodega es a traves de las existencias.
     *
     * Se usa belongsToMany con la tabla intermedia inventories porque tiene las
     * dos FK que necesita: warehouse_id (a Bodega) y lot_id (a Lot). El
     * hasManyThrough estandar no sirve aqui porque Inventory no tiene una FK
     * a Lot con el nombre que Laravel espera (Lot no tiene columna lot_id).
     *
     * La consulta genera:
     *   SELECT lots.* FROM lots
     *   INNER JOIN inventories ON lots.id = inventories.lot_id
     *   WHERE inventories.warehouse_id = :bodega_id
     */
    public function lots(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Lot::class,
            'inventories',
            'warehouse_id',
            'lot_id',
            'id',
            'id'
        )->wherePivot('warehouse_id', $this->id);
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class, 'warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Definir qué pasa con la bodega al crear o actualizar
    protected static function booted(): void
    {
        // Al crear una bodega, generar UUID y obtener el usuario autenticado
        static::creating(function (Bodega $bodega): void
        {
            if (empty($bodega->{$bodega->getKeyName()})) {
                $bodega->{$bodega->getKeyName()} = (string) Str::uuid();
            }
            if (auth()->check()) {
                $bodega->created_by = auth()->id();
            }
        });

        // Obtener el usuario autenticado y actualizar el campo updated_by
        static::updating(function (Bodega $bodega): void
        {
            if (auth()->check()) {
                $bodega->updated_by = auth()->id();
            }
        });
    }

}
