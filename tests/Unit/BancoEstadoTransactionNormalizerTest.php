<?php

namespace Tests\Unit;

use App\Services\Banking\Normalization\TransactionNormalizer;
use PHPUnit\Framework\TestCase;

class BancoEstadoTransactionNormalizerTest extends TestCase
{
    private TransactionNormalizer $normalizer;

    private array $map;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new TransactionNormalizer();

        $this->map = [
            'booking_date' => 'Fecha',
            'operation_number' => 'N° Operación',
            'description' => 'Descripción',
            'credit_amount' => 'Abonos',
            'debit_amount' => 'Cargos',
            'balance_after' => 'Saldo',
            '_currency' => 'CLP',
            '_statement_year' => 2026,
        ];
    }

    public function test_normalizes_bancoestado_debit_transaction(): void
    {
        $row = [
            'Fecha' => '17/Ago',
            'N° Operación' => '8083883',
            'Descripción' => 'COMISION TRANSACCION INTERNACIONAL',
            'Abonos' => '',
            'Cargos' => 470,
            'Saldo' => '264.104',
        ];

        $result = $this->normalizer->normalize(
            $row,
            $this->map
        );

        $this->assertSame(
            '2026-08-17',
            $result['booking_date']
        );

        $this->assertSame(
            '8083883',
            $result['operation_number']
        );

        $this->assertSame(
            'COMISION TRANSACCION INTERNACIONAL',
            $result['description_raw']
        );

        $this->assertSame(
            'debit',
            $result['direction']
        );

        $this->assertEquals(
            470.0,
            $result['amount']
        );

        /*
         * REGRESIÓN CRÍTICA:
         *
         * BancoEstado entrega "264.104"
         * como $264.104 CLP.
         *
         * Nunca debe convertirse nuevamente en 264.
         */
        $this->assertEquals(
            264104.0,
            $result['balance_after']
        );

        $this->assertSame(
            'CLP',
            $result['currency']
        );
    }

    public function test_normalizes_bancoestado_credit_transaction(): void
    {
        $row = [
            'Fecha' => '19/Ago',
            'N° Operación' => '8171580',
            'Descripción' => 'TEF DE NELSON NICOLAS VARELA VILLALO',
            'Abonos' => 50000,
            'Cargos' => '',
            'Saldo' => '290.145',
        ];

        $result = $this->normalizer->normalize(
            $row,
            $this->map
        );

        $this->assertSame(
            '2026-08-19',
            $result['booking_date']
        );

        $this->assertSame(
            '8171580',
            $result['operation_number']
        );

        $this->assertSame(
            'credit',
            $result['direction']
        );

        $this->assertEquals(
            50000.0,
            $result['amount']
        );

        $this->assertEquals(
            290145.0,
            $result['balance_after']
        );

        $this->assertSame(
            'CLP',
            $result['currency']
        );
    }

    public function test_normalizes_chilean_thousands_separator(): void
    {
        $row = [
            'Fecha' => '20/Ago',
            'N° Operación' => '8074975',
            'Descripción' => 'PAGO NETFLIX',
            'Abonos' => '',
            'Cargos' => '12.990',
            'Saldo' => '272.655',
        ];

        $result = $this->normalizer->normalize(
            $row,
            $this->map
        );

        $this->assertEquals(
            12990.0,
            $result['amount']
        );

        $this->assertEquals(
            272655.0,
            $result['balance_after']
        );

        $this->assertSame(
            'debit',
            $result['direction']
        );
    }

    public function test_removes_visual_formatting_from_operation_number(): void
    {
        /*
         * Esta prueba protege el sistema ante archivos
         * donde Excel entregue visualmente:
         *
         * 8,083,883
         *
         * en lugar de:
         *
         * 8083883
         */

        $row = [
            'Fecha' => '17/Ago',
            'N° Operación' => '8,083,883',
            'Descripción' => 'COMISION TRANSACCION INTERNACIONAL',
            'Abonos' => '',
            'Cargos' => 470,
            'Saldo' => '264.104',
        ];

        $result = $this->normalizer->normalize(
            $row,
            $this->map
        );

        $this->assertSame(
            '8083883',
            $result['operation_number']
        );

        $this->assertEquals(
            264104.0,
            $result['balance_after']
        );
    }
}
