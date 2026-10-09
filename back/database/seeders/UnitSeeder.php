<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Unit;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $units = [
            [
                'name' => 'Tableta',
                'abbreviation' => 'Tab',
            ],
            [
                'name' => 'Cápsula',
                'abbreviation' => 'Cap',
            ],
            [
                'name' => 'Mililitro',
                'abbreviation' => 'ml',
            ],
            [
                'name' => 'Gramo',
                'abbreviation' => 'g',
            ],
            [
                'name' => 'Frasco',
                'abbreviation' => 'Fras',
            ],
            [
                'name' => 'Ampolla',
                'abbreviation' => 'Amp',
            ],
            [
                'name' => 'Caja',
                'abbreviation' => 'Caj',
            ],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(
                ['abbreviation' => $unit['abbreviation']],
                ['name' => $unit['name']]
            );
        }
    }
}
