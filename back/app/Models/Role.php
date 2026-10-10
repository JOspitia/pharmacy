<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rol del sistema.
 *
 * El nombre del rol es un dato guardado en la base, no una constante de PHP.
 * Antes de esta tabla el mismo nombre vivia en un ENUM y tambien en
 * User::ROLES, y agregar un rol obligaba a tocar los dos.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
class Role extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Permisos que este rol tiene.
     *
     * Incluye los CRUD ({modulo}.can_read, etc.) y las capacidades globales
     * (autorizar-control-especial, etc.). No hay que distinguirlos aqui: para
     * la aplicacion un permiso es un nombre, y el modulo solo importa cuando
     * se quiere listar "que puede hacer en bodegas".
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Usuarios con este rol.
     *
     * La FK es users.role_id con onDelete RESTRICT: mientras haya usuarios,
     * el rol no se puede eliminar.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}