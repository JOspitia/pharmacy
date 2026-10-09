<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Modelo para el manejo de los productos.
 */
class Product extends Model
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
        'cum',
        'barcode',
        'invima_registration',
        'description',
        'unit_id',
        'group_id',
        'created_by',
        'updated_by',
        'status',
    ];

    // Relaciones
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Definir el tipo de dato
    protected $casts = [
        'unit_id' => 'string',
        'group_id' => 'string',
        'status' => 'boolean',
    ];

    // Valores por defecto del modelo antes de guardar en base de datos
    protected $attributes = [
        'status' => true,
    ];

    // Definir qué pasa con el grupo al crear o actualizar
    protected static function booted(): void
    {        
        // Al crear un grupo, generar UUID y obtener el usuario autenticado
        static::creating(function (Product $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        // Obtener el usuario autenticado y actualizar el campo updated_by
        static::updating(function (Product $model): void {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }


}
