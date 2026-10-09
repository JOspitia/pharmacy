<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Modelo para el manejo de los sensores de monitoreo.
 */
class Sensor extends Model
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
        'warehouse_id',
        'created_by',
        'updated_by',
    ];

    // Definir el tipo de dato
    protected $casts = [
        'status' => 'boolean',
        'warehouse_id' => 'string',
    ];

    // Valores por defecto del modelo antes de guardar en base de datos
    protected $attributes = [
        'status' => true,
    ];

    // Relaciones
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'warehouse_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }    

    // Definir qué pasa con el sensor al crear o actualizar
    protected static function booted(): void
    {
        // Al crear un sensor, generar UUID y obtener el usuario autenticado
        static::creating(function (Sensor $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        // Obtener el usuario autenticado y actualizar el campo updated_by
        static::updating(function (Sensor $model): void {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

}
