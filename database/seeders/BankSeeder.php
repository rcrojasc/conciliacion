<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            [
                'code' => '001',
                'name' => 'Banco de Chile',
                'country' => 'CL',
                'status' => 'active',
            ],
            [
                'code' => '009',
                'name' => 'Banco Internacional',
                'country' => 'CL',
                'status' => 'active',
            ],
            [
                'code' => '012',
                'name' => 'Banco del Estado de Chile',
                'country' => 'CL',
                'status' => 'active',
            ],
            [
                'code' => '014',
                'name' => 'Scotiabank Chile',
                'country' => 'CL',
                'status' => 'active',
            ],
            [
                'code' => '016',
                'name' => 'Banco de Crédito e Inversiones',
                'country' => 'CL',
                'status' => 'active',
            ],
            [
                'code' => '028',
                'name' => 'Banco BICE',
                'country' => 'CL',
                'status' => 'active',
            ],
            [
                'code' => '037',
                'name' => 'Banco Santander-Chile',
                'country' => 'CL',
                'status' => 'active',
            ],
            [
                'code' => '039',
                'name' => 'Banco Itaú Chile',
                'country' => 'CL',
                'status' => 'active',
            ],
            [
                'code' => '051',
                'name' => 'Banco Falabella',
                'country' => 'CL',
                'status' => 'active',
            ],
            [
                'code' => '053',
                'name' => 'Banco Ripley',
                'country' => 'CL',
                'status' => 'active',
            ],
            [
                'code' => '055',
                'name' => 'Banco Consorcio',
                'country' => 'CL',
                'status' => 'active',
            ],
        ];

        foreach ($banks as $bank) {
            Bank::query()->updateOrCreate(
                [
                    'code' => $bank['code'],
                ],
                [
                    'name' => $bank['name'],
                    'country' => $bank['country'],
                    'status' => $bank['status'],
                ]
            );
        }
    }
}
