<?php

namespace App\Enums;

enum TaxDocumentDirection: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Compra',
            self::SALE => 'Venta',
        };
    }
}
