<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Modelo para las alertas generadas por lecturas de temperatura y humedad (inmutable).
 */
class Alert extends Model
{
    use HasFactory;

    // Deshabilitar timestamps automáticos
    public $timestamps = false;

    // Definir el tipo de dato de la clave primaria
    protected $keyType = 'string';

    // Deshabilitar el incremento automático
    public $incrementing = false;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'id',
        'reading_id',
        'type',
        'message',
        'created_at',
    ];

    // Definir el tipo de dato
    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relaciones
    public function reading(): BelongsTo
    {
        return $this->belongsTo(Reading::class);
    }

    /**
     * Generar UUID automáticamente al crear.
     */
    protected static function booted(): void
    {
        static::creating(function (Alert $alert): void {
            if (empty($alert->{$alert->getKeyName()})) {
                $alert->{$alert->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
