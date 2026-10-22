<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Pix = 'pix';
    case Cash = 'dinheiro';
    case DebitCard = 'cartao_debito';
    case CreditCard = 'cartao_credito';
    case Other = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pix => 'Pix',
            self::Cash => 'Dinheiro',
            self::DebitCard => 'Cartão de débito',
            self::CreditCard => 'Cartão de crédito',
            self::Other => 'Outro',
        };
    }
}
