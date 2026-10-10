<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Roles definidos en la seccion 3 del enunciado.
     *
     * La lista esta duplicada como dato constante y como ENUM en la migracion
     * de la columna role. Si se agrega un rol hay que tocar ambos lugares.
     *
     * @var array<int, string>
     */
    public const ROLES = [
        'auxiliar_farmacia',
        'regente_farmacia',
        'medico',
        'auditor',
        'admin',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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
     * Indica si el usuario tiene al menos uno de los roles indicados.
     *
     * Se usa en dos lugares: en los Gate definidos en AuthServiceProvider y en
     * el middleware EnsureRole.
     *
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * El administrador pasa cualquier comprobacion de rol sin necesidad de
     * aparecer en la lista de cada capacidad.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}