<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permiso del sistema.
 *
 * Un permiso es una fila con nombre, no una columna. Eso es lo que permite que
 * existan permisos que no son CRUD: "autorizar-control-especial" no es leer,
 * crear, editar ni borrar, y en el modelo descartado de role_modules no
 * habia donde ponerlo.
 *
 * Hay dos formas de permiso:
 *
 *   - Con modulo: "warehouses.can_read". Se resuelve el nombre armando
 *     {modulo}.{sufijo} y lo consulta EnsureModule segun el verbo HTTP.
 *   - Sin modulo (module_id null): "autorizar-control-especial". Es una
 *     capacidad global que no depende de una ruta y se consulta con
 *     Gate::allows().
 *
 * @property int $id
 * @property string $name
 * @property int|null $module_id
 * @property string|null $description
 */
class Permission extends Model
{
    use HasFactory;

    // Aca no vive el catalogo de permisos. Ese es el punto de esta HU: los
    // permisos son filas de la tabla permissions y se consultan, no se leen de
    // una constante del codigo. Si volvemos a poner aqui una lista de
    // nombres de permiso, volvemos a tener dos fuentes de verdad: la base y el
    // modelo, que es justo lo que esta HU vino a eliminar del ENUM de
    // users.role.
    //
    // En la capa de aplicacion no queda ningun catalogo de permisos. Solo
    // Permission::crudName(), que compone {modulo}.{sufijo}: es aritmetica de
    // nombres, no un dato.
    //
    // El unico diccionario de nombres que existe esta en la capa web
    // (EnsureModule::SUFIJO_POR_VERBO) porque traduce verbos HTTP, y el modelo
    // de usuario no tiene por que saber de verbos.

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'module_id',
        'description',
    ];

    /**
     * Modigo al que pertenece el permiso, o null si es una capacidad global.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Roles que tienen este permiso.
     *
     * El nombre de la pivot va explicito porque Laravel lo deduce ordenando
     * alfabeticamente los nombres de los modelos.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    /**
     * Nombre completo del permiso CRUD de un modulo.
     *
     * El middleware no arma la concatenacion a mano: la clave se construye en
     * un solo lugar para que el seeder y el middleware no puedan separarse.
     *
     * @param  string  $module  Nombre del modulo, por ejemplo 'warehouses'.
     * @param  string  $action  Sufijo CRUD: can_read, can_create, can_update
     *                                o can_delete.
     */
    public static function crudName(string $module, string $action): string
    {
        return $module.'.'.$action;
    }

    /**
 * Indica si el permiso pertenece a un modulo (CRUD) o es una capacidad
     * global.
     */
    public function isGlobal(): bool
    {
        return $this->module_id === null;
    }

    /**
     * Nombres de todos los permisos del sistema.
     *
     * Se guarda en el contenedor y no en una propiedad estatica a proposito:
     * el contenedor se reconstruye en cada peticion y en cada prueba, asi que
     * la cache expira sola. Una propiedad estatica sobrevive entre pruebas y
     * devolveria un catalogo viejo si el seeder cambio.
     *
     * Es global, no por usuario: todos los usuarios de una peticion ven el
     * mismo catalogo, asi que alcanza con leerlo una vez.
     *
     * @return array<int, string>
     */
    public static function catalogo(): array
    {
        if (! app()->bound(self::CACHE_KEY)) {
            app()->instance(self::CACHE_KEY, self::query()->pluck('name')->all());
        }

        return app(self::CACHE_KEY);
    }

    /**
     * Descarta la cache del catalogo.
     *
     * Hace falta cuando los permisos cambian dentro del mismo ciclo de vida de
     * una peticion, por ejemplo en una prueba que asigna un permiso nuevo y
     * espera que el Gate lo vea de inmediato.
     */
    public static function olvidarCatalogo(): void
    {
        app()->forgetInstance(self::CACHE_KEY);
    }

    /**
     * @var string
     */
    protected const CACHE_KEY = 'catalogo-permisos';
}
