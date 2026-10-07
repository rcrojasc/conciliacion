<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationControl;
use App\Models\Permission;
use App\Models\ReconciliationRule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {

            /*
            |--------------------------------------------------------------------------
            | 1. Organización inicial
            |--------------------------------------------------------------------------
            */

            $organization = Organization::updateOrCreate(
                [
                    'name' => 'ARIONS',
                ],
                [
                    'status' => 'active',
                    'base_currency' => 'CLP',
                    'timezone' => 'America/Santiago',
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 2. Permisos
            |--------------------------------------------------------------------------
            */

            $permissions = [
                'organizations.view' => 'Ver organizaciones',
                'organizations.manage' => 'Administrar organizaciones',

                'users.view' => 'Ver usuarios',
                'users.manage' => 'Administrar usuarios',

                'bank_accounts.view' => 'Ver cuentas bancarias',
                'bank_accounts.manage' => 'Administrar cuentas bancarias',

                'imports.view' => 'Ver importaciones bancarias',
                'imports.create' => 'Importar cartolas bancarias',

                'transactions.view' => 'Ver transacciones bancarias',

                'reconciliations.view' => 'Ver conciliaciones',
                'reconciliations.create' => 'Crear conciliaciones',
                'reconciliations.approve' => 'Aprobar conciliaciones',
                'reconciliations.reverse' => 'Reversar conciliaciones',

                'rules.view' => 'Ver reglas de conciliación',
                'rules.manage' => 'Administrar reglas de conciliación',

                'ai.view' => 'Ver sugerencias de IA',
                'ai.use' => 'Utilizar asistente de IA',

                'audit.view' => 'Consultar auditoría',

                'reports.view' => 'Consultar reportes',
            ];

            $permissionModels = [];

            foreach ($permissions as $code => $name) {
                $permissionModels[$code] = Permission::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Roles
            |--------------------------------------------------------------------------
            */

            $roles = [
                'super_admin' => 'Super Administrador',
                'admin' => 'Administrador',
                'maker' => 'Conciliador / Maker',
                'checker' => 'Revisor / Checker',
                'auditor' => 'Auditor',
                'viewer' => 'Consulta',
            ];

            $roleModels = [];

            foreach ($roles as $code => $name) {
                $roleModels[$code] = Role::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Permisos del Super Administrador
            |--------------------------------------------------------------------------
            */

            $roleModels['super_admin']
                ->permissions()
                ->sync(
                    collect($permissionModels)
                        ->pluck('id')
                        ->all()
                );

            /*
            |--------------------------------------------------------------------------
            | 5. Administrador
            |--------------------------------------------------------------------------
            */

            $roleModels['admin']
                ->permissions()
                ->sync(
                    $this->permissionIds(
                        $permissionModels,
                        [
                            'organizations.view',

                            'users.view',
                            'users.manage',

                            'bank_accounts.view',
                            'bank_accounts.manage',

                            'imports.view',
                            'imports.create',

                            'transactions.view',

                            'reconciliations.view',
                            'reconciliations.create',
                            'reconciliations.approve',
                            'reconciliations.reverse',

                            'rules.view',
                            'rules.manage',

                            'ai.view',
                            'ai.use',

                            'audit.view',
                            'reports.view',
                        ]
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | 6. Maker
            |--------------------------------------------------------------------------
            */

            $roleModels['maker']
                ->permissions()
                ->sync(
                    $this->permissionIds(
                        $permissionModels,
                        [
                            'bank_accounts.view',

                            'imports.view',
                            'imports.create',

                            'transactions.view',

                            'reconciliations.view',
                            'reconciliations.create',

                            'rules.view',

                            'ai.view',
                            'ai.use',

                            'reports.view',
                        ]
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | 7. Checker
            |--------------------------------------------------------------------------
            */

            $roleModels['checker']
                ->permissions()
                ->sync(
                    $this->permissionIds(
                        $permissionModels,
                        [
                            'bank_accounts.view',

                            'imports.view',

                            'transactions.view',

                            'reconciliations.view',
                            'reconciliations.approve',
                            'reconciliations.reverse',

                            'rules.view',

                            'ai.view',

                            'audit.view',
                            'reports.view',
                        ]
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | 8. Auditor
            |--------------------------------------------------------------------------
            */

            $roleModels['auditor']
                ->permissions()
                ->sync(
                    $this->permissionIds(
                        $permissionModels,
                        [
                            'organizations.view',
                            'users.view',
                            'bank_accounts.view',
                            'imports.view',
                            'transactions.view',
                            'reconciliations.view',
                            'rules.view',
                            'ai.view',
                            'audit.view',
                            'reports.view',
                        ]
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | 9. Usuario de consulta
            |--------------------------------------------------------------------------
            */

            $roleModels['viewer']
                ->permissions()
                ->sync(
                    $this->permissionIds(
                        $permissionModels,
                        [
                            'bank_accounts.view',
                            'transactions.view',
                            'reconciliations.view',
                            'reports.view',
                        ]
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | 10. Usuario administrador inicial
            |--------------------------------------------------------------------------
            */

            $adminName = env(
                'ARIONS_ADMIN_NAME',
                'Administrador ARIONS'
            );

            $adminEmail = env('ARIONS_ADMIN_EMAIL');
            $adminPassword = env('ARIONS_ADMIN_PASSWORD');

            if (!$adminEmail || !$adminPassword) {
                throw new RuntimeException(
                    'Debe configurar ARIONS_ADMIN_EMAIL y '
                    .'ARIONS_ADMIN_PASSWORD en el archivo .env antes '
                    .'de ejecutar DatabaseSeeder.'
                );
            }

            if (strlen($adminPassword) < 12) {
                throw new RuntimeException(
                    'ARIONS_ADMIN_PASSWORD debe contener al menos '
                    .'12 caracteres.'
                );
            }

            $admin = User::updateOrCreate(
                [
                    'email' => strtolower(trim($adminEmail)),
                ],
                [
                    'name' => $adminName,

                    /*
                     * Aunque User posee el cast "hashed",
                     * utilizamos Hash::make explícitamente para que
                     * el propósito sea inequívoco en el seeder.
                     */
                    'password' => Hash::make($adminPassword),

                    'email_verified_at' => now(),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 11. Asociación usuario-organización
            |--------------------------------------------------------------------------
            |
            | organization_user posee su propio ULID.
            |
            */

            DB::table('organization_user')->updateOrInsert(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $admin->id,
                ],
                [
                    'id' => (string) Str::ulid(),
                    'role_id' => $roleModels['super_admin']->id,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 12. Controles Maker / Checker
            |--------------------------------------------------------------------------
            */

            OrganizationControl::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                ],
                [
                    'maker_checker_enabled' => true,
                    'require_checker_for_manual' => true,
                    'require_checker_for_differences' => true,

                    'checker_amount_threshold' => '1000000.0000',
                    'high_risk_amount_threshold' => '10000000.0000',
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 13. Reglas iniciales de conciliación
            |--------------------------------------------------------------------------
            */

            $rules = [
                [
                    'name' => 'Coincidencia exacta por monto',
                    'priority' => 10,
                    'rule_type' => 'exact_amount',
                    'config' => [
                        'enabled' => true,
                        'tolerance' => 0,
                        'weight' => 40,
                    ],
                    'auto_approve_threshold' => '100.00',
                ],

                [
                    'name' => 'Coincidencia por referencia',
                    'priority' => 20,
                    'rule_type' => 'reference',
                    'config' => [
                        'enabled' => true,
                        'exact_match' => true,
                        'weight' => 25,
                    ],
                    'auto_approve_threshold' => null,
                ],

                [
                    'name' => 'Coincidencia por RUT',
                    'priority' => 30,
                    'rule_type' => 'tax_id',
                    'config' => [
                        'enabled' => true,
                        'normalize' => true,
                        'weight' => 20,
                    ],
                    'auto_approve_threshold' => null,
                ],

                [
                    'name' => 'Coincidencia por fecha',
                    'priority' => 40,
                    'rule_type' => 'date',
                    'config' => [
                        'enabled' => true,
                        'days_before' => 3,
                        'days_after' => 3,
                        'weight' => 15,
                    ],
                    'auto_approve_threshold' => null,
                ],
            ];

            foreach ($rules as $rule) {
                ReconciliationRule::updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'rule_type' => $rule['rule_type'],
                    ],
                    [
                        'name' => $rule['name'],
                        'priority' => $rule['priority'],
                        'config' => $rule['config'],
                        'auto_approve_threshold' =>
                            $rule['auto_approve_threshold'],
                        'enabled' => true,
                    ]
                );
            }
        });

        $this->command?->info('');
        $this->command?->info(
            'ARIONS FINANCE inicializado correctamente.'
        );
        $this->command?->info(
            'Organización, RBAC, gobernanza y reglas creadas.'
        );
    }

    /**
     * Obtiene los IDs correspondientes a una lista de códigos de permisos.
     */
    private function permissionIds(
        array $permissions,
        array $codes
    ): array {
        return collect($codes)
            ->map(
                fn (string $code) => $permissions[$code]->id
            )
            ->all();
    }
}
