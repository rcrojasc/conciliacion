<?php

namespace Tests\Unit;

use App\Services\Banking\StatementBalanceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class StatementBalanceCalculatorTest extends TestCase
{
    public function test_calculates_opening_balance_for_debit(): void
    {
        $calculator = new StatementBalanceCalculator();

        $result = $calculator->openingBalance(
            264104,
            470,
            'debit'
        );

        $this->assertSame(
            264574.0,
            $result
        );
    }

    public function test_calculates_opening_balance_for_credit(): void
    {
        $calculator = new StatementBalanceCalculator();

        $result = $calculator->openingBalance(
            290145,
            50000,
            'credit'
        );

        $this->assertSame(
            240145.0,
            $result
        );
    }

    public function test_closing_balance_is_last_balance_after(): void
    {
        $calculator = new StatementBalanceCalculator();

        $result = $calculator->closingBalance(
            290145
        );

        $this->assertSame(
            290145.0,
            $result
        );
    }

    public function test_rejects_unknown_direction(): void
    {
        $calculator = new StatementBalanceCalculator();

        $this->expectException(
            InvalidArgumentException::class
        );

        $calculator->openingBalance(
            100000,
            5000,
            'unknown'
        );
    }
}
