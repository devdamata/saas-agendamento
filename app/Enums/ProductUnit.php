<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductUnit: string implements HasLabel
{
    case Unit = 'un';
    case Milliliter = 'ml';
    case Gram = 'g';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unit => 'Unidade (un)',
            self::Milliliter => 'Mililitro (ml)',
            self::Gram => 'Grama (g)',
        };
    }
}
