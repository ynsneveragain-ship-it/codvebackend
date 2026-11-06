<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'razon_social',
        'numero_documento',
        'telefono',
        'correo',
    ];

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class, 'cliente_id');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'cliente_id');
    }
}
