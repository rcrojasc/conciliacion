<?php

namespace Tests\Unit;

use App\Enums\TaxDocumentDirection;
use App\Enums\TaxDocumentMatchMethod;
use App\Enums\TaxDocumentPaymentStatus;
use App\Enums\TaxPaymentStatus;
use PHPUnit\Framework\TestCase;

class TaxDocumentEnumsTest extends TestCase
{
    public function test_document_direction_labels_are_in_spanish(): void
    {
        $this->assertSame('Compra', TaxDocumentDirection::PURCHASE->label());
        $this->assertSame('Venta', TaxDocumentDirection::SALE->label());
    }

    public function test_payment_status_labels_are_in_spanish(): void
    {
        $this->assertSame('Pendiente', TaxPaymentStatus::PENDING->label());
        $this->assertSame('Parcialmente pagada', TaxPaymentStatus::PARTIAL->label());
        $this->assertSame('Pagada', TaxPaymentStatus::PAID->label());
        $this->assertSame('Pago excedente', TaxPaymentStatus::OVERPAID->label());
        $this->assertSame('Anulada', TaxPaymentStatus::CANCELLED->label());
    }

    public function test_match_method_labels_are_in_spanish(): void
    {
        $this->assertSame('Monto exacto', TaxDocumentMatchMethod::EXACT_AMOUNT->label());
        $this->assertSame('RUT y monto', TaxDocumentMatchMethod::RUT_AMOUNT->label());
        $this->assertSame('Combinación de documentos', TaxDocumentMatchMethod::COMBINATION->label());
        $this->assertSame('Conciliación manual', TaxDocumentMatchMethod::MANUAL->label());
        $this->assertSame('Asistida por IA', TaxDocumentMatchMethod::AI_ASSISTED->label());
    }

    public function test_document_payment_status_labels_are_in_spanish(): void
    {
        $this->assertSame('Propuesta', TaxDocumentPaymentStatus::PROPOSED->label());
        $this->assertSame('Aprobada', TaxDocumentPaymentStatus::APPROVED->label());
        $this->assertSame('Rechazada', TaxDocumentPaymentStatus::REJECTED->label());
        $this->assertSame('Revertida', TaxDocumentPaymentStatus::REVERSED->label());
    }
}
