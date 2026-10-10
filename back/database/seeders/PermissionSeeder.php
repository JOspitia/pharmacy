<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Catalogo de permisos: los CRUD de cada modulo y las capacidades globales.
 *
 * Dos formas de permiso, y la diferencia esta en module_id:
 *
 *   - CRUD: name = "{modulo}.can_read", module_id = el modulo. Se llega por
 *     ruta, y EnsureModule deduce cual de los cuatro pide el verbo HTTP.
 *   - Capacidad: name = "autorizar-control-especial", module_id = NULL. No
 *     pertenece a un verbo ni a una ruta; se consulta con Gate::allows().
 *
 * Las capacidades vienen de la seccion 3 del enunciado. Antes vivian en un
 * array constante de AuthServiceProvider; moverlas aqui es lo que hace que
 * agregar una sea un INSERT y no un deploy.
 *
 * updateOrCreate para que el seeder sea idempotente y se pueda correr mas de
 * una vez sobre la misma base.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Los cuatro permisos CRUD de un modulo, en el orden en que EnsureModule
     * los recorre.
     *
     * VIVEN ACA Y NO EN EL MODELO Permission a proposito. Los permisos son
     * datos: su catalogo y la matriz de quien los tiene se siembran, y despues
 * se consultan. Si la lista estuviera en un modelo, seria una segunda
 * fuente de verdad junto a la tabla, que es exactamente el problema que
 * esta HU elimino al sacar el ENUM de users.role.
     *
     * Los sufijos CRUD viven en RolePermissionSeeder, que es quien los declara
     * una sola vez. Este seeder los usa para crear el catalogo y ese los usa
     * para armar la matriz: una definicion, dos consumidores.
     */

    public function run()
    {
        // Se cargan los modelos completos, no solo id y name, porque la
        // descripcion del permiso se arma con la descripcion del modulo, que ya
        // esta en espanol en la propia tabla modules.
        $modulos = Module::orderBy('id')->get();

        // ---------------------------------------------------------------
        // 1) Los cuatro CRUD de cada modulo.
        // ---------------------------------------------------------------
        foreach ($modulos as $modulo) {
            foreach (RolePermissionSeeder::ACCIONES as $accion) {
                Permission::updateOrCreate(
                    ['name' => Permission::crudName($modulo->name, $accion)],
                    [
                        'module_id' => $modulo->id,
                        'description' => $this->descripcionCrud($modulo, $accion),
                    ]
                );
            }
        }

        // ---------------------------------------------------------------
        // 2) Las capacidades globales (module_id NULL).
        // ---------------------------------------------------------------
        foreach (self::CAPACIDADES as $nombre => $descripcion) {
            Permission::updateOrCreate(
                ['name' => $nombre],
                ['module_id' => null, 'description' => $descripcion]
            );
        }
    }

    /**
     * Capacidades de negocio de la seccion 3 del enunciado.
     *
     * Que rol tiene cada una lo define RolePermissionSeeder, no este archivo.
     * Aqui solo vive el catalogo: nombre y descripcion.
     *
     * @var array<string, string>
     */
    public const CAPACIDADES = [
        'dispensar' => 'Dispensar medicamentos',
        'consultar-inventario' => 'Consultar el inventario',
        'crear-traslado' => 'Crear una solicitud de traslado',
        'recibir-traslado' => 'Recibir un traslado en destino',
        'aprobar-traslado' => 'Aprobar un traslado (exclusivo del regente, RN-08)',
        'autorizar-control-especial' => 'Autorizar dispensacion de control especial (exclusivo del regente, RN-05)',
        'ajustar-inventario' => 'Ajustar el inventario (exclusivo del regente)',
        'crear-prescripcion' => 'Crear una prescripcion (exclusivo del medico)',
        'consultar-pacientes' => 'Consultar datos de pacientes',
        'consultar-pacientes-enmascarados' => 'Consultar datos de pacientes enmascarados (auditor, RN-10)',
        'gestionar-usuarios' => 'Administrar usuarios del sistema',
        'gestionar-catalogos' => 'Administrar catalogos de productos y grupos',
    ];

    /**
     * Descripcion legible de un permiso CRUD, en espanol.
     *
     * Se arma con la descripcion del modulo, que ya vive en español en la tabla
     * modules. Antes habia un array ETIQUETAS paralelo que traducía cada nombre
     * de modulo, y era una segunda fuente: si manana se agrega un modulo con su
     * descripcion en ModuleSeeder, el array no lo tendria y la descripcion del
     * permiso caia en un fallback crudo como "Consultar newmodule".
     *
     * Derivar de la descripcion del modulo evita ese problema: hay una sola
     * fuente del texto en español y agregar un modulo no requiere tocar nada
     * aca.
     */
    private function descripcionCrud(Module $modulo, string $accion): string
    {
        $etiqueta = $modulo->description ?: $modulo->name;

        return match ($accion) {
            'can_read' => "Consultar {$etiqueta}",
            'can_create' => "Crear registros en {$etiqueta}",
            'can_update' => "Editar registros en {$etiqueta}",
            'can_delete' => "Eliminar registros en {$etiqueta}",
            default => $accion,
        };
    }
}