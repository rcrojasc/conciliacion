<?php

namespace App\Enums;

enum TaxDocumentMatchMethod: string
{
    case EXACT_AMOUNT = 'exact_amount';
    case RUT_AMOUNT = 'rut_amount';
    case COMBINATION = 'combination';
    case MANUAL = 'manual';
    case AI_ASSISTED = 'ai_assisted';

    public function label(): string
    {
        return match ($this) {
            self::EXACT_AMOUNT => 'Monto exacto',
            self::RUT_AMOUNT => 'RUT y monto',
            self::COMBINATION => 'Combinación de documentos',
            self::MANUAL => 'Conciliación manual',
            self::AI_ASSISTED => 'Asistida por IA',
        };
    }
}
