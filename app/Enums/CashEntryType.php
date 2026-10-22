<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CashEntryType: string implements HasColor, HasLabel
{
    case Income = 'entrada';
    case Expense = 'saida';

    public function getLabel(): string
    {
        return match ($this) {
            self::Income => 'Entrada',
            self::Expense => 'Saída',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Income => 'success',
            self::Expense => 'danger',
        };
    }
}
