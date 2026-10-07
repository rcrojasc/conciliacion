<?php

namespace Tests\Unit;

use App\Data\Tax\TaxDocumentData;
use App\Data\Tax\TaxDocumentLineData;
use App\Enums\TaxDocumentDirection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaxDocumentDataTest extends TestCase
{
    public function test_purchase_document_can_be_created(): void
    {
        $document =
            $this->createDocument();

        $this->assertSame(
            TaxDocumentDirection::PURCHASE,
            $document->direction
        );

        $this->assertSame(
            33,
            $document->documentType
        );

        $this->assertSame(
            '12345',
            $document->folio
        );

        $this->assertSame(
            119000.0,
            $document->totalAmount
        );

        $this->assertCount(
            1,
            $document->lines
        );
    }

    public function test_tax_ids_are_normalized(): void
    {
        $document =
            $this->createDocument();

        $this->assertSame(
            '76123456-7',
            $document->normalizedIssuerTaxId()
        );

        $this->assertSame(
            '76987654-3',
            $document->normalizedReceiverTaxId()
        );
    }

    public function test_currency_is_normalized(): void
    {
        $document =
            $this->createDocument(
                currency: ' clp '
            );

        $this->assertSame(
            'CLP',
            $document->normalizedCurrency()
        );
    }

    public function test_document_has_stable_identity_key(): void
    {
        $document =
            $this->createDocument();

        $this->assertSame(
            '76123456-7|33|12345',
            $document->identityKey()
        );
    }

    public function test_document_can_be_converted_to_array(): void
    {
        $document =
            $this->createDocument();

        $data =
            $document->toArray();

        $this->assertSame(
            'purchase',
            $data['direction']
        );

        $this->assertSame(
            33,
            $data['document_type']
        );

        $this->assertSame(
            '12345',
            $data['folio']
        );

        $this->assertSame(
            'CLP',
            $data['currency']
        );

        $this->assertCount(
            1,
            $data['lines']
        );

        $this->assertSame(
            'SERVICIO DE PRUEBA',
            $data['lines'][0]['description']
        );
    }

    public function test_empty_folio_is_rejected(): void
    {
        try {
            $this->createDocument(
                folio: ' '
            );

            $this->fail(
                'Se esperaba una InvalidArgumentException.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'El folio del documento tributario es obligatorio.',
                $exception->getMessage()
            );
        }
    }

    public function test_negative_total_is_rejected(): void
    {
        try {
            $this->createDocument(
                totalAmount: -100
            );

            $this->fail(
                'Se esperaba una InvalidArgumentException.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'El monto total del documento tributario no puede ser negativo.',
                $exception->getMessage()
            );
        }
    }

    public function test_invalid_line_type_is_rejected(): void
    {
        try {
            new TaxDocumentData(
                direction:
                    TaxDocumentDirection::PURCHASE,

                documentType:
                    33,

                folio:
                    '12345',

                issuerTaxId:
                    '76.123.456-7',

                issuerName:
                    'PROVEEDOR TEST SPA',

                receiverTaxId:
                    '76.987.654-3',

                receiverName:
                    'ARIONS TEST',

                issueDate:
                    CarbonImmutable::parse(
                        '2026-10-01'
                    ),

                dueDate:
                    CarbonImmutable::parse(
                        '2026-10-31'
                    ),

                netAmount:
                    100000,

                exemptAmount:
                    0,

                vatAmount:
                    19000,

                totalAmount:
                    119000,

                currency:
                    'CLP',

                source:
                    'test',

                lines:
                    collect([
                        'esto no es una línea válida',
                    ])
            );

            $this->fail(
                'Se esperaba una InvalidArgumentException.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Todas las líneas deben ser instancias de TaxDocumentLineData.',
                $exception->getMessage()
            );
        }
    }

    private function createDocument(
        string $folio = '12345',
        float $totalAmount = 119000,
        string $currency = 'CLP'
    ): TaxDocumentData {
        $line =
            new TaxDocumentLineData(
                lineNumber: 1,
                productCode: 'SERV-001',
                description: 'SERVICIO DE PRUEBA',
                quantity: 1,
                unit: 'UN',
                unitPrice: 100000,
                discountAmount: 0,
                surchargeAmount: 0,
                netAmount: 100000,
                metadata: []
            );

        return new TaxDocumentData(
            direction:
                TaxDocumentDirection::PURCHASE,

            documentType:
                33,

            folio:
                $folio,

            issuerTaxId:
                '76.123.456-7',

            issuerName:
                'PROVEEDOR TEST SPA',

            receiverTaxId:
                '76.987.654-3',

            receiverName:
                'ARIONS TEST',

            issueDate:
                CarbonImmutable::parse(
                    '2026-10-01'
                ),

            dueDate:
                CarbonImmutable::parse(
                    '2026-10-31'
                ),

            netAmount:
                100000,

            exemptAmount:
                0,

            vatAmount:
                19000,

            totalAmount:
                $totalAmount,

            currency:
                $currency,

            source:
                'test',

            externalId:
                'TEST-33-12345',

            metadata:
                [
                    'environment' => 'testing',
                ],

            lines:
                new Collection([
                    $line,
                ])
        );
    }
}
