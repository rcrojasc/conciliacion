<?php

namespace App\Enums;

enum TaxDocumentPaymentStatus: string
{
    case PROPOSED = 'proposed';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REVERSED = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::PROPOSED => 'Propuesta',
            self::APPROVED => 'Aprobada',
            self::REJECTED => 'Rechazada',
            self::REVERSED => 'Revertida',
        };
    }
}
