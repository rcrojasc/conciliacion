<?php

use App\Http\Controllers\Web\AITransactionController;
use App\Http\Controllers\Web\ApprovalQueueController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\AutoReconciliationController;
use App\Http\Controllers\Web\AuditController;
use App\Http\Controllers\Web\BankAccountController;
use App\Http\Controllers\Web\BankImportController;
use App\Http\Controllers\Web\BankingController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OrganizationSwitchController;
use App\Http\Controllers\Web\ReconciliationCenterController;
use App\Http\Controllers\Web\ReconciliationHistoryController;
use App\Http\Controllers\Web\ReconciliationRuleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {

    Route::get('/login', [AuthController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

/*
|--------------------------------------------------------------------------
| Área autenticada
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {

    Route::post('/logout', [AuthController::class, 'destroy'])
        ->name('logout');

    Route::post('/organization/switch', OrganizationSwitchController::class)
        ->name('organization.switch');

    /*
    |--------------------------------------------------------------------------
    | Organización activa
    |--------------------------------------------------------------------------
    */

    Route::middleware('organization')->group(function (): void {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/', DashboardController::class)
            ->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Banca
        |--------------------------------------------------------------------------
        */

        Route::get('/banking/accounts', [BankingController::class, 'accounts'])
            ->name('banking.accounts');

        Route::get('/banking/accounts/create', [BankAccountController::class, 'create'])
            ->middleware('permission:bank_accounts.manage')
            ->name('banking.accounts.create');

        Route::post('/banking/accounts', [BankAccountController::class, 'store'])
            ->middleware('permission:bank_accounts.manage')
            ->name('banking.accounts.store');

        Route::get('/banking/transactions', [BankingController::class, 'transactions'])
            ->name('banking.transactions');

        Route::get('/banking/imports', [BankingController::class, 'imports'])
            ->name('banking.imports');

        Route::post('/banking/imports', [BankImportController::class, 'store'])
            ->name('banking.imports.store');

        /*
        |--------------------------------------------------------------------------
        | Centro de conciliación
        |--------------------------------------------------------------------------
        */

        Route::get('/reconciliation', [ReconciliationCenterController::class, 'index'])
            ->name('reconciliation.index');

        Route::get('/reconciliation/{transaction}', [ReconciliationCenterController::class, 'show'])
            ->name('reconciliation.show');

        Route::post('/reconciliation/{transaction}/approve', [ReconciliationCenterController::class, 'approve'])
            ->name('reconciliation.approve');

        Route::post('/reconciliation/{transaction}/reject', [ReconciliationCenterController::class, 'reject'])
            ->name('reconciliation.reject');

        Route::post('/reconciliation/auto-run', AutoReconciliationController::class)
            ->middleware('permission:reconciliation.execute')
            ->name('reconciliation.auto-run');

        /*
        |--------------------------------------------------------------------------
        | Reglas de conciliación
        |--------------------------------------------------------------------------
        */

        Route::get('/reconciliation/rules', [ReconciliationRuleController::class, 'index'])
            ->middleware('permission:rules.manage')
            ->name('reconciliation.rules.index');

        Route::post('/reconciliation/rules', [ReconciliationRuleController::class, 'store'])
            ->middleware('permission:rules.manage')
            ->name('reconciliation.rules.store');

        Route::post('/reconciliation/rules/{rule}/toggle', [ReconciliationRuleController::class, 'toggle'])
            ->middleware('permission:rules.manage')
            ->name('reconciliation.rules.toggle');

        /*
        |--------------------------------------------------------------------------
        | Historial de conciliaciones
        |--------------------------------------------------------------------------
        */

        Route::get('/reconciliations/history', [ReconciliationHistoryController::class, 'index'])
            ->name('reconciliation.history');

        /*
        |--------------------------------------------------------------------------
        | Aprobaciones Maker / Checker
        |--------------------------------------------------------------------------
        */

        Route::get('/reconciliations/approvals', [ApprovalQueueController::class, 'index'])
            ->middleware('permission:reconciliation.approve')
            ->name('reconciliation.approvals');

        Route::post(
            '/reconciliations/{reconciliation}/checker-approve',
            [ApprovalQueueController::class, 'approve']
        )
            ->middleware('permission:reconciliation.approve')
            ->name('reconciliation.approvals.approve');

        Route::post(
            '/reconciliations/{reconciliation}/checker-reject',
            [ApprovalQueueController::class, 'reject']
        )
            ->middleware('permission:reconciliation.approve')
            ->name('reconciliation.approvals.reject');

        /*
        |--------------------------------------------------------------------------
        | Auditoría
        |--------------------------------------------------------------------------
        */

        Route::get('/audit', AuditController::class)
            ->middleware('permission:audit.view')
            ->name('audit.index');

        /*
        |--------------------------------------------------------------------------
        | Inteligencia Artificial
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/ai/transactions/{transaction}/suggest',
            [AITransactionController::class, 'suggest']
        )
            ->name('ai.transactions.suggest');

        Route::post(
            '/ai/suggestions/{suggestion}/feedback',
            [AITransactionController::class, 'feedback']
        )
            ->name('ai.suggestions.feedback');

        /*
        |--------------------------------------------------------------------------
        | Reversas
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/reconciliations/{reconciliation}/reverse',
            [ReconciliationHistoryController::class, 'reverse']
        )
            ->name('reconciliation.reverse');
    });
});
