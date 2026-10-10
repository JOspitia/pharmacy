<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rol que recibe un usuario cuyo ENUM no se pudo resolver, y ademas el
     * valor por defecto de la columna role_id. Coincide con el default que
     * tenia la columna ENUM, de modo que un usuario sin rol explicito sigue
     * cayendo en el mismo rol que antes de esta migracion.
     */
    private const ROL_POR_DEFECTO = 'auxiliar_farmacia';

    /**
     * Valores del ENUM que esta migracion tiene que saber resolver.
     *
     * Solo los nombres, sin descripcion. La descripcion no hace falta para
     * migrar datos y es dato de catalogo, que pertenece a RoleSeeder: repetirla
     * aqui crearia una segunda fuente que se desincroniza en cuanto alguien
     * edite una de las dos.
     *
     * Los roles se insertan desde esta migracion y no desde el seeder porque
     * en una base nueva `migrate` corre antes que `migrate --seed`. Si esta
     * migracion esperara al seeder, la tabla roles estaria vacia y los
     * usuarios no tendrian a que apuntar.
     *
     * insertOrIgnore mas updateOrCreate del seeder: los dos pueden correr, en
     * cualquier orden, y el resultado es el mismo.
     *
     * @var array<int, string>
     */
    private const ROLES = [
        'admin',
        'regente_farmacia',
        'auxiliar_farmacia',
        'medico',
        'auditor',
    ];

    /**
     * Run the migrations.
     *
     * Sustituye users.role (ENUM) por users.role_id (FK a roles).
     *
     * La razon de fondo: el ENUM y la tabla roles eran dos fuentes de verdad
     * para los mismos cinco nombres. Agregar un rol obligaba a tocar el
     * esquema y el codigo a la vez, y era facil que dejaran de coincidir.
     * Con la FK hay una sola fuente y el UNIQUE de roles.name hace de
     * validador, igual que hacia el ENUM.
     *
     * Dos decisiones de implementacion que conviene dejar escritas:
     *
     * 1) Se toma una foto de los valores ENUM antes de dropear la columna, y
     *    despues se agrega role_id con DEFAULT y se corrige fila por fila. La
     *    alternativa natural seria renameColumn + change, pero en Laravel 9
     *    ambos exigen doctrine/dbal, que no esta en composer.json. Agregar la
     *    columna ya con default hace que MySQL rellene las filas existentes sin
     *    pedir valor, y el UPDATE posterior pisa ese relleno con el rol real.
     *
     * 2) down() falla a proposito. Una vez dropeada la columna ENUM los valores
     *    originales no se pueden reconstruir, y un rollback que "funciona" a
     *    medias dejaria usuarios con un rol que nunca tuvieron. Es preferible
     *    que migrate:rollback se queje a que se crea la ilusion de que el
     *    cambio se puede deshacer.
     */
    public function up(): void
    {
        // Solo los nombres: la migracion necesita que existan para resolver
        // los usuarios, no saber como se describen. RoleSeeder completa las
        // descripciones despues.
        //
        // MERGE y no insertOrIgnore: el atajo de Laravel solo existe en MySQL y
        // SQLite. En SQL Server hay que escribir un SQL especifico del motor,
        // que es justamente lo que este bloque pretendia evitar.
        $sql = "MERGE roles AS destino
USING (SELECT ? AS name) AS origen
    ON destino.name = origen.name
WHEN NOT MATCHED THEN
    INSERT (name, created_at, updated_at) VALUES (?, ?, ?);";

        foreach (self::ROLES as $nombre) {
            DB::statement($sql, [$nombre, $nombre, now(), now()]);
        }

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $defaultRoleId = $roleIds[self::ROL_POR_DEFECTO];

        // Foto del rol. Sin esto, dropear la columna tiraria a la basura el dato que
        // hay que preservar.
        $usuarios = DB::table('users')->select('id', 'role')->get();

        // El CHECK de la migracion 000004 referencia la columna role, y SQL
        // Server se niega a borrar una columna de la que dependa una
        // constraint. Por eso la constraint cae antes que la columna, en ese
        // orden. En MySQL el orden no importaba porque no habia constraint.
        DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_role');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) use ($defaultRoleId) {
            $table->unsignedBigInteger('role_id')
                ->default($defaultRoleId)
                ->after('email')
                ->comment('Rol del usuario. Define que modulos puede ver y con que permisos.');
        });

        foreach ($usuarios as $usuario) {
            DB::table('users')
                ->where('id', $usuario->id)
                ->update([
                    'role_id' => $roleIds[$usuario->role ?? ''] ?? $defaultRoleId,
                ]);
        }

        // NO ACTION y no CASCADE: no se puede eliminar un rol que alguien usa,
        // porque dejaria al usuario sin rol. El CATALOGO de roles es decir "este
        // rol esta en uso, reasigna primero".
        //
        // NO ACTION y no RESTRICT porque T-SQL no tiene la palabra RESTRICT: el
        // juego valido es NO ACTION, CASCADE, SET NULL y SET DEFAULT. En MySQL
        // RESTRICT y NO ACTION se comportan igual, asi que la migracion queda
        // portable escribiendo el termino que existe en los dos motores. Ademas
        // es el que ya usan las demas migraciones del proyecto.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Irreversible. Ver la nota en up().
     */
    public function down(): void
    {
        throw new RuntimeException(
            'La migracion 2026_10_09_000008 es irreversible: dropea la columna '
            . 'users.role (ENUM) y los valores originales no se pueden recuperar. '
            . 'Para volver atras hay que restaurar la base desde un backup previo.'
        );
    }
};