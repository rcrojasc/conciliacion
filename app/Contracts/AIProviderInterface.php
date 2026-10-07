<?php
namespace App\Contracts;

interface AIProviderInterface
{
    /** Return validated structured data; never free-form financial actions. */
    public function analyzeTransaction(array $transaction, array $schema): array;
}
