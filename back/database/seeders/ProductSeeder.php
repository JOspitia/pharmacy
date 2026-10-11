<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seis medicamentos del enunciado (seccion 6: "datos semilla").
 *
 * Distribuidos en los 3 grupos del GroupSeeder. El ultimo es control
 * especial (requires_authorization=true) para validar la RN-05 en
 * dispensacion y el bloqueo en kardex.
 *
 * Los CUM son sinteticos: el enunciado prohibe expresamente usar datos
 * reales, asi que siguen el formato colombiano (numero de registro
 * sanitario) sin corresponder a ningun producto existente.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('name', 'admin')->first();
        if (! $admin) {
            throw new \RuntimeException(
                'ProductSeeder necesita el usuario admin del UserSeeder.'
            );
        }

        $unidadTableta = Unit::where('abbreviation', 'Tab')->first();
        $unidadCapsula = Unit::where('abbreviation', 'Cap')->first();
        $unidadAmpolla = Unit::where('abbreviation', 'Amp')->first();
        $unidadFrasco = Unit::where('abbreviation', 'Fras')->first();

        if (! $unidadTableta || ! $unidadCapsula || ! $unidadAmpolla || ! $unidadFrasco) {
            throw new \RuntimeException(
                'ProductSeeder necesita las unidades del UnitSeeder (Tab, Cap, Amp, Fras). '
                . 'Corre UnitSeeder antes que este seeder.'
            );
        }

        $grupoAnalgesico = Group::where('name', 'Analgesicos y Antipireticos')->first();
        $grupoAntibiotico = Group::where('name', 'Antibioticos')->first();
        $grupoControl = Group::where('name', 'Control Especial')->first();

        if (! $grupoAnalgesico || ! $grupoAntibiotico || ! $grupoControl) {
            throw new \RuntimeException(
                'ProductSeeder necesita los 3 grupos del GroupSeeder. '
                . 'Corre GroupSeeder antes que este seeder.'
            );
        }

        $productos = [
            // Analgesicos (uso masivo, sin control especial)
            [
                'name' => 'Acetaminofen 500mg',
                'cum' => '19936-78-1',
                'barcode' => '7701234567001',
                'invima_registration' => 'INV-2018-001',
                'description' => 'Tableta para manejo del dolor y fiebre. Dosis adulto: 500mg cada 6h.',
                'group_id' => $grupoAnalgesico->id,
                'unit_id' => $unidadTableta->id,
                'status' => true,
                'requires_authorization' => false,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
            [
                'name' => 'Ibuprofeno 400mg',
                'cum' => '19937-12-3',
                'barcode' => '7701234567002',
                'invima_registration' => 'INV-2019-014',
                'description' => 'Antiinflamatorio no esteroideo. Tableta 400mg.',
                'group_id' => $grupoAnalgesico->id,
                'unit_id' => $unidadTableta->id,
                'status' => true,
                'requires_authorization' => false,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],

            // Antibioticos (rotacion controlada, sin control especial)
            [
                'name' => 'Amoxicilina 500mg',
                'cum' => '19941-23-5',
                'barcode' => '7701234567003',
                'invima_registration' => 'INV-2017-089',
                'description' => 'Antibiotico betalactamico. Capsula 500mg cada 8h.',
                'group_id' => $grupoAntibiotico->id,
                'unit_id' => $unidadCapsula->id,
                'status' => true,
                'requires_authorization' => false,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
            [
                'name' => 'Ciprofloxacino 500mg',
                'cum' => '19944-56-2',
                'barcode' => '7701234567004',
                'invima_registration' => 'INV-2020-042',
                'description' => 'Quinolona de segunda generacion. Tableta 500mg cada 12h.',
                'group_id' => $grupoAntibiotico->id,
                'unit_id' => $unidadTableta->id,
                'status' => true,
                'requires_authorization' => false,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
            [
                'name' => 'Ceftriaxona 1g IM',
                'cum' => '19947-89-7',
                'barcode' => '7701234567005',
                'invima_registration' => 'INV-2016-201',
                'description' => 'Cefalosporina de tercera generacion. Ampolla 1g para administracion IM/IV.',
                'group_id' => $grupoAntibiotico->id,
                'unit_id' => $unidadAmpolla->id,
                'status' => true,
                'requires_authorization' => false,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],

            // Control especial (RN-05)
            [
                'name' => 'Morfina 10mg/ml',
                'cum' => '19912-34-5',
                'barcode' => '7701234567006',
                'invima_registration' => 'INV-2015-067',
                'description' => 'Opioide mayor. Ampolla 10mg/ml. Sujeto a fiscalizacion y autorizacion del regente.',
                'group_id' => $grupoControl->id,
                'unit_id' => $unidadAmpolla->id,
                'status' => true,
                'requires_authorization' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        ];

        foreach ($productos as $producto) {
            Product::updateOrCreate(
                ['cum' => $producto['cum']],
                $producto,
            );
        }
    }
}
