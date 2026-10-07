<?php

namespace App\Enums;

enum TaxPaymentStatus: string
{
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case OVERPAID = 'overpaid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::PARTIAL => 'Parcialmente pagada',
            self::PAID => 'Pagada',
            self::OVERPAID => 'Pago excedente',
            self::CANCELLED => 'Anulada',
        };
    }
}
