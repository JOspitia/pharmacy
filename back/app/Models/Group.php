<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Product;

/**
 * Modelo para el manejo de los grupos.
 */
class Group extends Model
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
        'created_by',
        'updated_by',
    ];

    // Definir el tipo de dato
    protected $casts = [
        'status' => 'boolean',
    ];

    // Valores por defecto del modelo antes de guardar en base de datos
    protected $attributes = [
        'status' => true,
    ];

    // Definir qué pasa con el grupo al crear o actualizar
    protected static function booted(): void
    {
        parent::boot();
        
        // Al crear un grupo, generar UUID y obtener el usuario autenticado
        static::creating(function (Group $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        // Obtener el usuario autenticado y actualizar el campo updated_by
        static::updating(function (Group $model): void {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    // Relación con productos
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
