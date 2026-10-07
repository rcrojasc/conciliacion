<?php
namespace App\Contracts;

interface BankConnectorInterface
{
    public function accounts(): array;
    public function balances(): array;
    public function transactions(array $filters = []): iterable;
    public function refreshAuthorization(): void;
    public function revokeAuthorization(): void;
}
