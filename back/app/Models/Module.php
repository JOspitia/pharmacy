<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modulo del sistema, sobre el que se aplican los permisos.
 *
 * El nombre coincide con el prefijo de las rutas en routes/api.php. Eso es lo
 * que permite que una ruta proteja su grupo escribiendo el nombre del modulo
 * una sola vez:
 *
 *     Route::middleware('module:warehouses')->group(function () {
 *         Route::apiResource('bodegas', BodegaController::class);
 *     });
 *
 * El nombre del modulo NO coincide con el prefijo de la URL en el caso de
 * warehouses: la ruta sigue siendo /api/bodegas porque la tabla se llama asi
 * desde el desarrollo original. Ver ModuleSeeder.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
class Module extends Model
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
     * Permisos que existen para este modulo.
     *
     * Son los cuatro CRUD ({modulo}.can_read, etc.). Las capacidades globales
     * no aparecen aca porque no pertenecen a ningun modulo: se consultan por
     * nombre desde Gate.
     *
     * HASMANY Y NO BELONGSTOMANY a proposito. La relacion va por
     * permissions.module_id, que apunta a modules.id: un modulo tiene muchos
     * permisos y un permiso pertenece como mucho a un modulo. role_permissions
     * es la tabla entre ROLES y permisos, no tiene columna module_id, y usarla
     * aqui generaba SQL del tipo "where role_permissions.module_id = ?", que
     * revienta con Invalid column name en cuanto se invoca.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }
}