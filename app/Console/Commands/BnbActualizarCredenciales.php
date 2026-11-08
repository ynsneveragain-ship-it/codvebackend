<?php

namespace App\Console\Commands;

use App\Models\Pago;
use Illuminate\Console\Command;

/**
 * Cambia la contraseña (authorizationId) de la cuenta del BNB.
 *
 * El banco OBLIGA a hacerlo una vez, con las credenciales iniciales que
 * entrega, antes de poder usar cualquier otro servicio de su API: hasta
 * entonces ni el token sirve (ver BNB_QR_API.md, paso 2 del flujo).
 *
 * Es un comando y no una pantalla a proposito: cambia una credencial EN EL
 * BANCO, no se puede deshacer desde aqui, y si luego no se guarda la nueva en
 * el .env el sistema se queda sin poder cobrar. Lo ejecuta a mano quien
 * administra el sistema:
 *
 *     php artisan bnb:credenciales
 */
class BnbActualizarCredenciales extends Command
{
    protected $signature = 'bnb:credenciales
                            {--nueva= : Nueva contraseña. Si no se indica, se pide por pantalla sin mostrarla}';

    protected $description = 'Cambia la contraseña (authorizationId) de la cuenta del BNB. Obligatorio la primera vez.';

    public function handle(): int
    {
        $cuenta = (string) config('bnb.account_id');

        if ($cuenta === '' || blank(config('bnb.authorization_id'))) {
            $this->error('Faltan BNB_ACCOUNT_ID y BNB_AUTHORIZATION_ID en el .env del backend.');

            return self::FAILURE;
        }

        $this->line('Banco:   ' . config('bnb.url'));
        $this->line('Cuenta:  ' . $cuenta);
        $this->newLine();
        $this->warn('La contraseña actual del .env dejará de servir en cuanto esto termine bien.');
        $this->line('Requisitos del banco: 15 caracteres o más, con al menos una letra, un');
        $this->line('número y uno de estos especiales:  + - / * $ < > ! ¡ # | =');
        $this->newLine();

        $nueva = (string) ($this->option('nueva') ?? '');

        if ($nueva === '') {
            $nueva = (string) $this->secret('Nueva contraseña (no se mostrará)');
            $repetida = (string) $this->secret('Repítela');

            if ($nueva !== $repetida) {
                $this->error('Las dos contraseñas no coinciden.');

                return self::FAILURE;
            }
        }

        if ($motivo = Pago::validarNuevaAutorizacion($nueva)) {
            $this->error("Esa contraseña no cumple lo que pide el banco: {$motivo}.");

            return self::FAILURE;
        }

        if (! $this->confirm('¿Cambiarla en el banco ahora? No se puede deshacer desde aquí', false)) {
            $this->line('Cancelado, no se ha cambiado nada.');

            return self::SUCCESS;
        }

        $r = Pago::actualizarCredenciales($nueva);

        if (! $r['ok']) {
            $this->error($r['message']);
            $this->line('La contraseña del banco NO ha cambiado; el .env sigue siendo válido.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Credencial cambiada en el banco.');
        $this->warn('AHORA, antes de cobrar nada: pon la nueva en backend/.env');
        $this->line('    BNB_AUTHORIZATION_ID=<la que acabas de escribir>');
        $this->line('y luego  php artisan config:clear');

        return self::SUCCESS;
    }
}
