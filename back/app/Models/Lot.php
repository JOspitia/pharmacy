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
    protected $fillable = [
        'name',
        'product_id',
        'warehouse_id',
        'stock',
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

    public function warehouse()
    {
        return $this->belongsTo(Bodega::class, 'warehouse_id');
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
        'warehouse_id' => 'string',
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
