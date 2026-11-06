<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        Rol::updateOrCreate(['nombre' => 'admin'], ['descripcion' => 'Administrador']);
        Rol::updateOrCreate(['nombre' => 'visita'], ['descripcion' => 'Solo lectura']);
    }
}
