<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de movimiento del Kardex (RN-06).
 *
 * Catalogo de datos, no enum en codigo. Deliberadamente NO hay constantes como
 * MovementType::ENTRY ni una lista TIPOS: el catalogo vive unicamente en la
 * tabla movement_types, que es la fuente de verdad.
 *
 * Por que importa: dos listas del mismo catalogo divergen en silencio, y el
 * tipo que existe en el codigo pero no en la base rompe el registro del
 * movimiento en runtime con un error de FK.
 *
 * Para obtener un tipo puntual se consulta por name:
 *
 *     $tipo = MovementType::porNombre('TRANSFER_OUT');
 *
 * name y direction son identificadores tecnicos y van en ingles; description
 * es texto de interfaz y va en espanol (AI_USAGE.md 4.1).
 *
 * @property string $name
 * @property string $description
 * @property string $direction  IN | OUT | IN_OUT
 */
class MovementType extends Model
{
    use HasFactory;

    /** UUID como clave primaria, no auto-increment. */
    public $incrementing = false;
    protected $keyType = 'string';

    public const DIRECCION_ENTRADA = 'IN';
    public const DIRECCION_SALIDA = 'OUT';
    public const DIRECCION_AMBAS = 'IN_OUT';

    protected $fillable = ['name', 'description', 'direction'];

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    /**
     * Busca un tipo por su identificador tecnico.
     *
     * Devuelve null si no existe. El llamador decide que hacer: un tipo
     * desconocido es un dato corrupto y corresponde un 500 explicito.
     */
    public static function porNombre(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    public function esSalida(): bool
    {
        return $this->direction === self::DIRECCION_SALIDA;
    }

    public function esEntrada(): bool
    {
        return $this->direction === self::DIRECCION_ENTRADA;
    }

    public function admiteAmbosSignos(): bool
    {
        return $this->direction === self::DIRECCION_AMBAS;
    }
}