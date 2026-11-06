<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['nombre' => 'plan1'], [
            'vigencia' => 12,
            'precio' => 100.00,
            'precio_oferta' => 80.00,
            'caracteristicas' => ['caract1', 'caract2', 'caract3'],
        ]);
    }
}
