<?php

namespace Tests\Unit;

use App\Services\Banking\StatementIntegrityValidator;
use PHPUnit\Framework\TestCase;

class StatementIntegrityValidatorTest extends TestCase
{
    private StatementIntegrityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator =
            new StatementIntegrityValidator();
    }

    public function test_accepts_consistent_statement(): void
    {
        $rows = [
            [
                'position' => 1,
                'amount' => 470,
                'direction' => 'debit',
                'balance_after_reported' => 264104,
            ],
            [
                'position' => 2,
                'amount' => 50000,
                'direction' => 'credit',
                'balance_after_reported' => 314104,
            ],
            [
                'position' => 3,
                'amount' => 10000,
                'direction' => 'debit',
                'balance_after_reported' => 304104,
            ],
        ];

        $result =
            $this->validator->validate($rows);

        $this->assertTrue(
            $result['valid']
        );

        $this->assertSame(
            2,
            $result['checked_transitions']
        );

        $this->assertSame(
            [],
            $result['inconsistencies']
        );
    }

    public function test_detects_balance_mismatch(): void
    {
        $rows = [
            [
                'position' => 1,
                'amount' => 470,
                'direction' => 'debit',
                'balance_after_reported' => 264104,
            ],
            [
                'position' => 2,
                'amount' => 50000,
                'direction' => 'credit',

                /*
                 * Debería ser 314104.
                 */
                'balance_after_reported' => 314204,
            ],
        ];

        $result =
            $this->validator->validate($rows);

        $this->assertFalse(
            $result['valid']
        );

        $this->assertSame(
            1,
            $result['checked_transitions']
        );

        $this->assertCount(
            1,
            $result['inconsistencies']
        );

        $error =
            $result['inconsistencies'][0];

        $this->assertSame(
            'balance_mismatch',
            $error['type']
        );

        $this->assertSame(
            2,
            $error['position']
        );

        $this->assertEquals(
            314104.0,
            $error['expected_balance']
        );

        $this->assertEquals(
            314204.0,
            $error['reported_balance']
        );

        $this->assertEquals(
            100.0,
            $error['difference']
        );
    }

    public function test_does_not_cascade_balance_errors(): void
    {
        $rows = [
            [
                'position' => 1,
                'amount' => 470,
                'direction' => 'debit',
                'balance_after_reported' => 264104,
            ],
            [
                'position' => 2,
                'amount' => 50000,
                'direction' => 'credit',

                /*
                 * Error intencional de $100.
                 */
                'balance_after_reported' => 314204,
            ],
            [
                'position' => 3,
                'amount' => 10000,
                'direction' => 'debit',

                /*
                 * Este movimiento sí cuadra respecto del saldo
                 * realmente reportado en la fila anterior:
                 *
                 * 314204 - 10000 = 304204
                 */
                'balance_after_reported' => 304204,
            ],
        ];

        $result =
            $this->validator->validate($rows);

        $this->assertFalse(
            $result['valid']
        );

        /*
         * Se comprobaron ambas transiciones.
         */
        $this->assertSame(
            2,
            $result['checked_transitions']
        );

        /*
         * Solamente debe existir el error real de la posición 2.
         */
        $this->assertCount(
            1,
            $result['inconsistencies']
        );

        $this->assertSame(
            2,
            $result['inconsistencies'][0]['position']
        );
    }

    public function test_skips_transition_when_balance_is_missing(): void
    {
        $rows = [
            [
                'position' => 1,
                'amount' => 470,
                'direction' => 'debit',
                'balance_after_reported' => null,
            ],
            [
                'position' => 2,
                'amount' => 50000,
                'direction' => 'credit',
                'balance_after_reported' => 314104,
            ],
        ];

        $result =
            $this->validator->validate($rows);

        $this->assertTrue(
            $result['valid']
        );

        $this->assertSame(
            0,
            $result['checked_transitions']
        );

        $this->assertSame(
            [],
            $result['inconsistencies']
        );
    }

    public function test_detects_unsupported_direction(): void
    {
        $rows = [
            [
                'position' => 1,
                'amount' => 470,
                'direction' => 'debit',
                'balance_after_reported' => 264104,
            ],
            [
                'position' => 2,
                'amount' => 50000,
                'direction' => 'unknown',
                'balance_after_reported' => 314104,
            ],
        ];

        $result =
            $this->validator->validate($rows);

        $this->assertFalse(
            $result['valid']
        );

        $this->assertSame(
            0,
            $result['checked_transitions']
        );

        $this->assertCount(
            1,
            $result['inconsistencies']
        );

        $this->assertSame(
            'unsupported_direction',
            $result['inconsistencies'][0]['type']
        );

        $this->assertSame(
            2,
            $result['inconsistencies'][0]['position']
        );
    }

    public function test_accepts_empty_statement(): void
    {
        $result =
            $this->validator->validate([]);

        $this->assertTrue(
            $result['valid']
        );

        $this->assertSame(
            0,
            $result['checked_transitions']
        );

        $this->assertSame(
            [],
            $result['inconsistencies']
        );
    }
}
