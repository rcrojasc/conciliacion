<?php

namespace App\Services\Banking;

use InvalidArgumentException;

class StatementBalanceCalculator
{
    /**
     * Calcula el saldo inmediatamente anterior
     * al primer movimiento de una cartola.
     *
     * Crédito:
     * saldo inicial = saldo posterior - monto
     *
     * Débito:
     * saldo inicial = saldo posterior + monto
     */
    public function openingBalance(
        string|int|float $balanceAfter,
        string|int|float $amount,
        string $direction
    ): float {
        $balanceAfter = (float) $balanceAfter;
        $amount = (float) $amount;

        return match ($direction) {
            'credit' => $balanceAfter - $amount,
            'debit' => $balanceAfter + $amount,

            default => throw new InvalidArgumentException(
                "Dirección bancaria no soportada: {$direction}"
            ),
        };
    }

    /**
     * El saldo final de la cartola corresponde
     * al saldo posterior del último movimiento.
     */
    public function closingBalance(
        string|int|float $balanceAfter
    ): float {
        return (float) $balanceAfter;
    }
}
