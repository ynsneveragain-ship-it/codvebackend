<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * updateOrCreate por nombre: volver a ejecutar el seeder actualiza los
     * planes existentes en vez de duplicarlos.
     */
    public function run(): void
    {
        // Plan semestral
        Plan::updateOrCreate(['nombre' => 'plan1'], [
            'vigencia' => 6,
            'precio' => 100.00,
            'precio_oferta' => 80.00,
            'caracteristicas' => ['caract1', 'caract2', 'caract3'],
        ]);

        // Plan anual
        Plan::updateOrCreate(['nombre' => 'plan2'], [
            'vigencia' => 12,
            'precio' => 150.00,
            'precio_oferta' => null,
            'caracteristicas' => ['caract1', 'caract2', 'caract3', 'caract4'],
        ]);
    }
}
