<?php
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\BankTransactionController;
use App\Http\Controllers\Api\V1\Imports\BankImportController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ReconciliationController;
use Illuminate\Support\Facades\Route;
Route::prefix('v1')->group(function () {
    Route::post('/auth/login',[AuthController::class,'login'])->middleware('throttle:login');
    Route::middleware('auth:sanctum')->group(function(){
        Route::get('/auth/me',[AuthController::class,'me']); Route::post('/auth/logout',[AuthController::class,'logout']);
        Route::middleware('organization')->group(function () {
            Route::get('/organizations/current', OrganizationController::class);
            Route::apiResource('bank-accounts', BankAccountController::class)->only(['index','show']);
            Route::get('bank-transactions', [BankTransactionController::class,'index']);
            Route::get('reconciliations', [ReconciliationController::class,'index']);
            Route::get('bank-imports',[BankImportController::class,'index'])->middleware('permission:bank_transaction.import');
            Route::post('bank-imports',[BankImportController::class,'store'])->middleware('permission:bank_transaction.import');
            Route::get('bank-imports/{bankImport}',[BankImportController::class,'show'])->middleware('permission:bank_transaction.import');
        });
    });
});
