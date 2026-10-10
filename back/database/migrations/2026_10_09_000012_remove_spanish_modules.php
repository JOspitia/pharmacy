<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retira los modulos con nombre en espanol.
 *
 * CONTEXTO: la primera version de HU-07 creo los modulos con nombre en
 * espanol (bodegas, grupos, productos, lotes, sensores, reportes, usuarios) y
 * despues se corrigieron a ingles por la convencion del proyecto
 * (AI_USAGE.md seccion 4.1): tablas y columnas en ingles.
 *
 * ModuleSeeder usa updateOrCreate, asi que al cambiar los nombres creo los
 * siete nuevos SIN borrar los siete viejos. Ambos conjuntos quedaron en la
 * tabla: 14 modulos en vez de 7, con sus permisos y sus asignaciones. Los
 * viejos quedaron huerfanos porque ninguna ruta ni ningun test los referencia.
 *
 * Esta migracion los elimina. El borrado en cascada se encarga del resto:
 * permissions.module_id tiene ON DELETE CASCADE y role_permissions.permission_id
 * tambien, asi que al caer un modulo caen sus permisos y sus asignaciones.
 *
 * Por que una migracion y no un simple script: porque la base de desarrollo
 * arranco con migraciones incrementales durante el renombre. Quien haga
 * migrate:fresh desde cero nunca vera los duplicados, pero quien確 herede esta
 * base si los tiene. El repositorio debe converger al mismo estado en los
 * dos casos.
 */
return new class extends Migration
{
    /**
     * Nombres retirados. Lista explicita y no un filtro por "los que no tienen
     * permisos": un filtro asi borraria datos validos en cuanto un modulo se
     * creara sin sembrar.
     *
     * @var array<int, string>
     */
    private const NOMBRES_RETIRADOS = [
        'bodegas',
        'grupos',
        'productos',
        'lotes',
        'sensores',
        'reportes',
        'usuarios',
    ];

    public function up(): void
    {
        $ids = DB::table('modules')
            ->whereIn('name', self::NOMBRES_RETIRADOS)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // Explicitado para que quede registrado en el log que se borro y por
        // que. El CASCADE de permissions.module_id se encarga del resto.
        DB::table('modules')->whereIn('id', $ids)->delete();
    }

    /**
     * Reverse the migrations.
     *
     * No se reconstruyen los modulos: sus permisos se borraron por cascada y no
     * hay copia de la que volver. Un rollback que los recreara vacios dejaria
     * modulos sin permisos, que es peor que no tenerlos.
     */
    public function down(): void
    {
        //
    }
};