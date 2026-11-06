<?php

namespace Database\Seeders;

use App\Models\Dominio;
use App\Models\Suscripcion;
use Illuminate\Database\Seeder;

class DominioSeeder extends Seeder
{
    public function run(): void
    {
        $suscripcion = Suscripcion::first();

        foreach ([1, 2, 3] as $n) {
            Dominio::updateOrCreate(['nombre' => "dominio{$n}.com"], [
                'suscripcion_id' => $suscripcion->id,
                'proveedor' => "proveedor{$n}",
                'precio_real' => 10.00,
                'precio' => 15.00,
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => now()->addYear()->toDateString(),
            ]);
        }
    }
}
