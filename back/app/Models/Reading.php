<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Modelo para las lecturas de temperatura y humedad (inmutable).
 * Cada lectura pertenece directamente a un sensor (1:N).
 */
class Reading extends Model
{
    use HasFactory;

    // Deshabilitar timestamps automáticos (solo se usa recorded_at manual)
    public $timestamps = false;

    // Definir el tipo de dato de la clave primaria
    protected $keyType = 'string';

    // Deshabilitar el incremento automático
    public $incrementing = false;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'id',
        'sensor_id',
        'temperature',
        'humidity',
        'recorded_at',
    ];

    // Definir el tipo de dato
    protected $casts = [
        'temperature' => 'decimal:2',
        'humidity' => 'decimal:2',
        'recorded_at' => 'datetime',
        'sensor_id' => 'string',
    ];

    // Relaciones
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    public function alert(): HasOne
    {
        return $this->hasOne(Alert::class);
    }

    // Definir qué pasa con la lectura al crear o actualizar
    protected static function booted(): void
    {
        // Al crear una lectura, generar UUID
        static::creating(function (Reading $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

}
