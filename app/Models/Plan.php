<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = [
        'nombre',
        'vigencia',
        'precio',
        'precio_oferta',
        'caracteristicas',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'precio_oferta' => 'decimal:2',
            'caracteristicas' => 'array',
        ];
    }

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class, 'plan_id');
    }
}
