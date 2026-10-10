<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;


    /**
     * The attributes that are mass assignable.
     *
     * Ya no esta 'role': el rol es una relacion, se asigna por role_id. Un
     * 'role' en mass assignment permitiria escribir un nombre de rol que no
     * existe en la base.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Rol del usuario.
     *
     * Es una relacion, no un atributo: el valor vive en roles.name y el acceso
     * por id va por role_id.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Indica si el usuario tiene un permiso concreto.
     *
     * Unico metodo de autorizacion del modelo. Resuelve tanto los permisos CRUD
     * ("warehouses.can_create") como las capacidades globales
     * ("autorizar-control-especial"), porque para la aplicacion un permiso es
     * un nombre y no hay diferencia entre los dos al comprobarlo.
     *
     * MEMORIZACION: la lista de permisos del rol se lee una sola vez por
     * instancia y se guarda en memoria. Antes cada comprobacion era un EXISTS
     * contra la base, y una pantalla que evaluara diez permisos hacia diez
     * consultas para preguntar siempre lo mismo. Ahora hay una consulta por
     * peticion, no una por permiso.
     *
     * El costo es que un cambio de permisos no se ve dentro de la misma
     * instancia. Para una peticion HTTP eso es correcto: los permisos no
     * cambian mientras se procesa. Quien necesite releer usa fresh(), que
     * devuelve una instancia nueva sin la cache.
     *
     * @param  string  $nombre  Nombre completo del permiso.
     */
    public function hasPermission(string $nombre): bool
    {
        if ($this->role_id === null) {
            return false;
        }

        return in_array($nombre, $this->nombresPermiso(), true);
    }

    /**
     * Nombres de los permisos del rol, cargados una sola vez.
     *
     * Null es el estado "todavia no se consulto": un rol sin permisos devuelve
     * un arreglo vacio, que es un valor valido y no se puede usar de centinela.
     *
     * @return array<int, string>
     */
    protected function nombresPermiso(): array
    {
        if ($this->nombresPermisoCache === null) {
            $this->nombresPermisoCache = $this->role?->permissions()
                ->pluck('permissions.name')
                ->all() ?? [];
        }

        return $this->nombresPermisoCache;
    }

    /**
     * Cache de permisos por instancia. No se propaga al modelo serializado.
     *
     * @var array<int, string>|null
     */
    protected $nombresPermisoCache = null;
}
