<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        Cliente::updateOrCreate(['numero_documento' => '1000001'], [
            'nombre' => 'cliente1',
            'razon_social' => 'razon1',
            'telefono' => '70000001',
            'correo' => 'cliente1@gmail.com',
        ]);
    }
}
