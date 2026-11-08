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
        // Rastro del aviso de vencimiento que se manda desde el panel del
        // dashboard. NULL = nunca se le aviso al cliente (ver 5.26).
        'estado_notificacion',
        'fecha_notificacion',
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
            'fecha_notificacion' => 'datetime',
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

    /**
     * A que correo se le escribe al cliente de ESTA suscripcion.
     *
     * Vive aqui, y no en cada sitio que manda un correo, porque la usan tres:
     * el aviso de compra cobrada (Pago::enviarAvisoDeCompra) y los dos avisos
     * de vencimiento del panel del dashboard (VencimientoController). Es la
     * misma razon por la que la charla con Porkbun vive en Dominio y la del
     * BNB en Pago: si cada sitio decidiera por su cuenta, acabarian
     * escribiendo a direcciones distintas del mismo cliente.
     *
     * ORDEN: primero el correo que se escribio EN ESTA VENTA (detalles ->
     * contacto_indicado.correo) y solo despues el de la ficha del cliente.
     * Cuando el CI ya existia, CompraController reutiliza el cliente y NO le
     * pisa los datos, asi que la ficha puede llevar un correo viejo mientras
     * el de esta venta es el que la persona acaba de escribir.
     *
     * Devuelve null si ninguno de los dos es una direccion valida.
     */
    public function correoDeAviso(): ?string
    {
        return self::primerCorreoValido([
            $this->detalles['contacto_indicado']['correo'] ?? null,
            $this->cliente?->correo,
        ]);
    }

    /**
     * El primero de la lista que sea una direccion de correo utilizable.
     * Compartido con Pago, que tiene un candidato mas (su propio cliente).
     */
    public static function primerCorreoValido(array $candidatos): ?string
    {
        foreach ($candidatos as $correo) {
            $correo = trim((string) $correo);

            if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                return $correo;
            }
        }

        return null;
    }
}
