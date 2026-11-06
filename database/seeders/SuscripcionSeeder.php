<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Plan;
use App\Models\Suscripcion;
use Illuminate\Database\Seeder;

class SuscripcionSeeder extends Seeder
{
    public function run(): void
    {
        $cliente = Cliente::first();
        $plan = Plan::first();

        Suscripcion::updateOrCreate(
            ['cliente_id' => $cliente->id, 'tipo_servicio' => 'serv1'],
            [
                'plan_id' => $plan->id,
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => now()->addYear()->toDateString(),
                'costo' => 80.00,
                'estado' => 'activo',
                'usuario' => 'user_serv1',
                'password' => 'pass_serv1',
                'detalles' => ['nota' => 'detalle1'],
            ]
        );
    }
}
