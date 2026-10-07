<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use RuntimeException;

class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Organización
        |--------------------------------------------------------------------------
        */

        $organization = Organization::query()
            ->where('name', 'ARIONS')
            ->first();

        if (!$organization) {
            throw new RuntimeException(
                'No existe la organización ARIONS. '
                .'Ejecute primero DatabaseSeeder.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | BancoEstado
        |--------------------------------------------------------------------------
        |
        | Código SBIF/CMF utilizado por nuestro BankSeeder:
        | 012 = Banco del Estado de Chile
        |
        */

        $bank = Bank::query()
            ->where('code', '012')
            ->first();

        if (!$bank) {
            throw new RuntimeException(
                'No existe BancoEstado (código 012). '
                .'Ejecute primero BankSeeder.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Cuenta bancaria de desarrollo
        |--------------------------------------------------------------------------
        |
        | external_id se utiliza como identificador estable para que el seeder
        | sea idempotente.
        |
        | No almacenamos el número completo de la cuenta bancaria.
        */

        BankAccount::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'bank_id' => $bank->id,
                'external_id' => 'ARIONS-BANCOESTADO-CUENTARUT-001',
            ],
            [
                'name' => 'BancoEstado - CuentaRUT',
                'account_type' => 'cuenta_vista',
                'currency' => 'CLP',
                'masked_number' => '****1093',
                'status' => 'active',
            ]
        );

        $this->command?->info(
            'Cuenta BancoEstado de ARIONS creada correctamente.'
        );
    }
}
