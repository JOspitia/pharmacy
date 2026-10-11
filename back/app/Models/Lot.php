<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Modelo para el manejo de los lotes.
 */
class Lot extends Model
{

    // Trait para el manejo de la tabla
    use HasFactory, SoftDeletes;

    // Definir el tipo de clave primaria
    protected $keyType = 'string';

    //Indicar que el id no es autoincremental
    public $incrementing = false;

    // Campos que se pueden asignar masivamente
    //
    // stock y warehouse_id ya no viven aca (HU-08, RN-01). El stock pertenece
    // a la existencia, que es la terna bodega x producto x lote; el lote es un
    // atributo del producto (numero de lote del fabricante y fecha de
    // vencimiento) y no un lugar. Mantenerlos en el fillable haria que un
    // INSERT intentara escribir columnas que ya no existen.
    protected $fillable = [
        'name',
        'product_id',
        'expiration_date',
        'description',
        'created_by',
        'updated_by',
    ];

    // Relaciones
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Existencias de este lote, una por bodega (RN-01).
     *
     * Reemplaza a la relacion directa con bodega: el mismo lote puede estar
     * en varias bodegas a la vez, que es lo que un traslado hace posible.
     */
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Stock total del lote sumando todas las bodegas donde esta.
     */
    public function stockTotal(): int
    {
        return (int) $this->inventories()->sum('quantity');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }


    // Cambiar tipo de dato a los campos
    protected $casts = [
        'expiration_date' => 'date',
        'status' => 'boolean',
        'product_id' => 'string',
    ];

    // Valores por defecto del modelo antes de guardar en base de datos
    protected $attributes = [
        'status' => true,
    ];

    // Definir qué pasa con el grupo al crear o actualizar
    protected static function booted(): void
    {        
        // Al crear un grupo, generar UUID y obtener el usuario autenticado
        static::creating(function (Lot $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        // Obtener el usuario autenticado y actualizar el campo updated_by
        static::updating(function (Lot $model): void {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
