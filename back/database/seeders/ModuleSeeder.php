<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Catalogo de modulos del sistema.
 *
 * LOS NOMBRES VAN EN INGLES por la convencion del proyecto (AI_USAGE.md 4.1):
 * tablas y columnas en ingles para que el ORM y el codigo esten alineados, y
 * la documentacion y los textos de interfaz en espanol. El nombre del modulo
 * es un identificador tecnico, asi que va en ingles.
 *
 * Cada nombre coincide con la tabla que representa el modulo. bodegas es la
 * excepcion: su tabla se llama asi desde el desarrollo original y renombrarla
 * excede el alcance de esta HU. Por eso el modulo es warehouses y la ruta
 * sigue siendo /api/bodegas.
 *
 * La descripcion SI va en espanol: es texto que ve una persona, no un
 * identificador.
 *
 * El nombre aparece en la ruta como Route::middleware('module:<nombre>'). Que
 * no coincida con el prefijo de la URL en el caso de warehouses es
 * deliberado: preferimos un identificador consistente en ingles antes que
 * copiar la nomenclatura mixta que arrastra el proyecto.
 */
class ModuleSeeder extends Seeder
{
    public function run()
    {
        $modules = [
            [
                'name' => 'warehouses',
                'description' => 'Gestion de bodegas',
            ],
            [
                'name' => 'groups',
                'description' => 'Gestion de grupos de productos',
            ],
            [
                'name' => 'products',
                'description' => 'Catalogo maestro de productos',
            ],
            [
                'name' => 'lots',
                'description' => 'Gestion de lotes e inventario',
            ],
            [
                'name' => 'sensors',
                'description' => 'Monitoreo de sensores y lecturas ambientales',
            ],
            [
                'name' => 'reports',
                'description' => 'Consulta de reportes de inventario',
            ],
            [
                'name' => 'users',
                'description' => 'Administracion de usuarios del sistema',
            ],
            [
                'name' => 'kardex',
                'description' => 'Consultas de kardex e inventario',
            ],
            [
                'name' => 'transfers',
                'description' => 'Traslados entre bodegas',
            ],
            [
                'name' => 'patients',
                'description' => 'Gestion de pacientes',
            ],
            [
                'name' => 'prescriptions',
                'description' => 'Gestion de prescripciones medicas',
            ],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['name' => $module['name']],
                ['description' => $module['description']]
            );
        }
    }
}