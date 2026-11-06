<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suscripcion extends Model
{
    protected $table = 'suscripciones';

    protected $fillable = [
        'cliente_id',
        'plan_id',
        'tipo_servicio',
        'fecha_inicio',
        'fecha_fin',
        'costo',
        'estado',
        'usuario',
        'password',
        'detalles',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'costo' => 'decimal:2',
            'detalles' => 'array',
        ];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function dominios()
    {
        return $this->hasMany(Dominio::class, 'suscripcion_id');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'suscripcion_id');
    }
}
