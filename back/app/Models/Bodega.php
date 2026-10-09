<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Lot;

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

    /** 
     * Definir qué pasa con la bodega al crear o actualizar
     */
    protected static function booted(): void
    {
        // Al crear una bodega, generar UUID y obtener el usuario autenticado
        static::creating(function (Bodega $bodega): void {
            if (empty($bodega->{$bodega->getKeyName()})) {
                $bodega->{$bodega->getKeyName()} = (string) Str::uuid();
            }
            if (auth()->check()) {
                $bodega->created_by = auth()->id();
            }
        });

        // Obtener el usuario autenticado y actualizar el campo updated_by
        static::updating(function (Bodega $bodega): void {
            if (auth()->check()) {
                $bodega->updated_by = auth()->id();
            }
        });
    }

    /**
     * Relación con lotes
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }
}
