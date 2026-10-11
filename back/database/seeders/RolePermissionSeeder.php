<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Que permisos tiene cada rol.
 *
 * Dos matrices, una sola tabla de destino:
 *
 *   - La de CRUD (Anexo A de la HU) dice, por rol y modulo, que verbos puede
 *     usar. Se traduce a nombres {modulo}.can_read, etc.
 *   - La de capacidades dice que roles tienen cada permiso global.
 *
 * Ambas terminan en role_permissions. Un CRUD y una capacidad son lo mismo
 * para la base: un nombre que un rol tiene o no tiene.
 *
 * LA MATRIZ SE APLICA CON sync() Y NO CON syncWithoutDetaching. Se descarto el
 * segundo a proposito: solo agrega y nunca quita, asi que cambiar la matriz no
 * surte efecto sobre una base ya sembrada. Cuando admin paso de CRUD a solo
 * lectura, los permisos de escritura que ya tenia seguida en la base: el seeder
 * decia una cosa y los datos otra. Y como las pruebas usan RefreshDatabase,
 * la suite no lo notaba.
 *
 * Con sync() la base queda exactamente como el seeder la declara, y un permiso
 * que el seeder saca de un rol tambien se lo saca de la base.
 */
class RolePermissionSeeder extends Seeder
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
     * El unico nombre de permiso que queda en la capa de aplicacion es el
     * mapeo verbo HTTP a sufijo de EnsureModule::SUFIJO_POR_VERBO, que es
     * protocolo de transporte y no catalogo.
     *
     * @var array<int, string>
     */
    public const ACCIONES = ['can_read', 'can_create', 'can_update', 'can_delete'];

    /**
     * Solo lectura: el perfil del auditor y, para el administrador, el de los
     * modulos que el administrador no escribe.
     *
     * @var array<int, string>
     */
    public const LECTURA = ['can_read'];

    /**
     * Lectura y escritura sin borrado: el auxiliar sobre lotes, que carga y
     * corrige stock pero no borra el historico.
     *
     * @var array<int, string>
     */
    public const LECTURA_Y_ESCRITURA = ['can_read', 'can_create', 'can_update'];

    public function run()
    {
        $modulos = Module::pluck('id', 'name');
        $roles = Role::pluck('id', 'name');
        $permisos = Permission::pluck('id', 'name');

        // Se acumula el conjunto COMPLETO de cada rol y se aplica una sola vez
        // al final, para que sync() pueda quitar lo que sobre.
        $porRol = [];

        foreach ($this->matrizCrud() as $nombreRol => $porModulo) {
            if (! isset($roles[$nombreRol])) {
                throw new RuntimeException(
                    "RolePermissionSeeder necesita el rol '{$nombreRol}'. "
                    . 'Corrige RoleSeeder antes que este seeder.'
                );
            }

            $ids = [];

            foreach ($porModulo as $nombreModulo => $acciones) {
                if (! isset($modulos[$nombreModulo])) {
                    throw new RuntimeException(
                        "RolePermissionSeeder necesita el modulo '{$nombreModulo}'. "
                        . 'Corrige ModuleSeeder antes que este seeder.'
                    );
                }

                foreach ($acciones as $accion) {
                    $nombre = Permission::crudName($nombreModulo, $accion);

                    if (! isset($permisos[$nombre])) {
                        throw new RuntimeException(
                            "RolePermissionSeeder necesita el permiso '{$nombre}'. "
                            . 'Corrige PermissionSeeder antes que este seeder.'
                        );
                    }

                    $ids[] = $permisos[$nombre];
                }
            }

            $porRol[$nombreRol] = $ids;
        }

        // Capacidades globales, sumadas a lo ya acumulado del mismo rol.
        foreach ($this->matrizCapacidades() as $nombreRol => $capacidades) {
            if (! isset($roles[$nombreRol])) {
                throw new RuntimeException(
                    "RolePermissionSeeder necesita el rol '{$nombreRol}'. "
                    . 'Corrige RoleSeeder antes que este seeder.'
                );
            }

            foreach ($capacidades as $nombreCapacidad) {
                if (! isset($permisos[$nombreCapacidad])) {
                    throw new RuntimeException(
                        "RolePermissionSeeder necesita la capacidad '{$nombreCapacidad}'. "
                        . 'Corrige PermissionSeeder antes que este seeder.'
                    );
                }

                $porRol[$nombreRol][] = $permisos[$nombreCapacidad];
            }
        }

        foreach ($porRol as $nombreRol => $ids) {
            Role::find($roles[$nombreRol])->permissions()->sync(array_values(array_unique($ids)));
        }
    }

    /**
     * Matriz CRUD. Cada valor es la lista de acciones que el rol puede hacer en
     * ese modulo; un modulo ausente significa que no entra.
     *
     * ADMIN. Sigue la matriz del enunciado que esta en PENDIENTE_auditoria.md
     * (seccion de matriz por modulo):
     *
     *   - Bodegas y catalogos (grupos, productos): escritura. Son
     *     administracion, no un flujo clinico.
     *   - Almacen (lotes, stock): solo lectura. El stock es el flujo clinico
     *     que RN-05 y RN-08 protegen.
     *   - Sensores y reportes: solo lectura.
     *   - Usuarios: completo. Administrar usuarios es lo que existe el rol.
     *
     * Una version anterior de esta matriz le daba a admin solo lectura en todas
     * partes. Eso era incorrecto: contradecía al enunciado y dejaba huerfana la
     * capacidad `gestionar-catalogos`, que declara que el administrador
     * administra el catalogo y sin embargo sus rutas le devolvian 403.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    private function matrizCrud(): array
    {
        return [
            'admin' => [
                'warehouses' => self::ACCIONES,
                'groups' => self::ACCIONES,
                'products' => self::ACCIONES,
                'lots' => self::LECTURA,
                'sensors' => self::LECTURA,
                'reports' => self::LECTURA,
                'users' => self::ACCIONES,
                // HU-08 §7: admin consulta e ingresa movimientos.
                'kardex' => ['can_read', 'can_create'],
                // HU-09: admin consulta (no crea/aprueba/despacha/recibe).
                'transfers' => self::LECTURA,
            ],

            // Opera el dia a dia. Sensores y reportes los consulta pero no los
            // administra: un regente no crea sensores ni borra lecturas.
            'regente_farmacia' => [
                'warehouses' => self::ACCIONES,
                'groups' => self::ACCIONES,
                'products' => self::ACCIONES,
                'lots' => self::ACCIONES,
                'sensors' => self::LECTURA,
                'reports' => self::LECTURA,
                // HU-08 §7: regente consulta y ajusta inventario.
                'kardex' => ['can_read', 'can_create'],
                // HU-09: regente hace todo el ciclo del traslado
                // (solicita, aprueba, despacha, recibe, anula).
                'transfers' => self::ACCIONES,
            ],

            // Carga y consulta stock. Sin delete porque el historico de lotes
            // no se borra desde la interfaz.
            'auxiliar_farmacia' => [
                'warehouses' => self::LECTURA,
                'groups' => self::LECTURA,
                'products' => self::LECTURA,
                'lots' => self::LECTURA_Y_ESCRITURA,
                'reports' => self::LECTURA,
                // HU-08 §7: auxiliar consulta inventario.
                'kardex' => self::LECTURA,
                // HU-09: auxiliar solicita y recibe traslados, pero
                // no aprueba, no despacha y no anula (decisiones
                // autoritativas del regente). Sin can_delete
                // porque el historial de traslados no se borra desde
                // la interfaz.
                'transfers' => ['can_read', 'can_create', 'can_update'],
            ],

            // Consulta inventario y reportes. Ni grupos ni creacion: el medico
            // no da de alta productos.
            'medico' => [
                'warehouses' => self::LECTURA,
                'products' => self::LECTURA,
                'lots' => self::LECTURA,
                'reports' => self::LECTURA,
                // HU-08 §7: medico consulta kardex.
                'kardex' => self::LECTURA,
                // HU-09: medico consulta traslados.
                'transfers' => self::LECTURA,
            ],

            // Solo lectura en todo, como corresponde a un auditor.
            'auditor' => [
                'warehouses' => self::LECTURA,
                'groups' => self::LECTURA,
                'products' => self::LECTURA,
                'lots' => self::LECTURA,
                'sensors' => self::LECTURA,
                'reports' => self::LECTURA,
                // HU-08 §7: auditor consulta kardex.
                'kardex' => self::LECTURA,
                // HU-09: auditor consulta traslados.
                'transfers' => self::LECTURA,
            ],
        ];
    }

    /**
     * Que roles tienen cada capacidad global.
     *
     * Traduce literalmente la seccion 3 del enunciado.
     *
     * ADMIN tiene las dos administrativas y ninguna clinica. Esa es la razon por
     * la que se puede quitar el bypass del Gate::before: el administrador no
     * puede autorizar control especial ni aprobar traslados, que es lo que
     * exigen RN-05 y RN-08.
     *
     * `gestionar-catalogos` no queda huerfana: el administrador tiene escritura
     * en groups y products, asi que la capacidad coincide con lo que las rutas
     * le dejan hacer.
     *
     * @return array<string, array<int, string>>
     */
    private function matrizCapacidades(): array
    {
        return [
            'admin' => [
                'gestionar-usuarios',
                'gestionar-catalogos',
                'consultar-traslados',
            ],
            'auxiliar_farmacia' => [
                'dispensar',
                'consultar-inventario',
                'crear-traslado',
                'recibir-traslado',
                // HU-09: sin anular-traslado. Anular es una decision
                // autoritativa y le corresponde al regente, igual que
                // aprobar y despachar. El auxiliar crea y recibe.
                'consultar-traslados',
            ],
            'regente_farmacia' => [
                'dispensar',
                'consultar-inventario',
                'crear-traslado',
                'recibir-traslado',
                'aprobar-traslado',
                'despachar-traslado',
                'anular-traslado',
                // HU-09 + RN-07: resolver discrepancias es decision
                // autoritativa (cerrar el traslado o asumir la perdida).
                'resolver-discrepancia',
                'autorizar-control-especial',
                'ajustar-inventario',
                'consultar-traslados',
            ],
            'medico' => [
                'consultar-inventario',
                'crear-prescripcion',
                'consultar-pacientes',
                'consultar-traslados',
            ],
            'auditor' => [
                'consultar-inventario',
                'consultar-pacientes-enmascarados',
                'consultar-traslados',
            ],
        ];
    }
}