<?php
namespace App\Providers;
use App\Models\BankAccount;
use App\Policies\BankAccountPolicy;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
final class AppServiceProvider extends ServiceProvider { public function register(): void { $this->app->singleton(TenantContext::class); $this->app->bind(\App\Contracts\AIProviderInterface::class, \App\Services\AI\HeuristicAIProvider::class); } public function boot(): void { Gate::policy(BankAccount::class,BankAccountPolicy::class); } }
