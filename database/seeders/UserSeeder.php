<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Rol::where('nombre', 'admin')->first();
        $visita = Rol::where('nombre', 'visita')->first();

        User::updateOrCreate(['correo' => 'admin@gmail.com'], [
            'rol_id' => $admin->id,
            'primer_nombre' => 'Admin',
            'apellido_paterno' => 'Sistema',
            'password' => Hash::make('admin123'),
        ]);

        User::updateOrCreate(['correo' => 'visita@gmail.com'], [
            'rol_id' => $visita->id,
            'primer_nombre' => 'Visita',
            'apellido_paterno' => 'Sistema',
            'password' => Hash::make('visita123'),
        ]);
    }
}
