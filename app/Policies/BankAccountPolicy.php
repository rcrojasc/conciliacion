<?php
namespace App\Policies;
use App\Models\BankAccount;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
final class BankAccountPolicy { public function __construct(private TenantContext $ctx){} public function view(User $user,BankAccount $account): bool { return $this->ctx->id()===$account->organization_id; } }
