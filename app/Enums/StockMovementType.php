<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasColor, HasLabel
{
    /** Compra ou reposição: quantidade positiva. */
    case Purchase = 'entrada';

    /** Baixa pela ficha técnica de um atendimento concluído: quantidade negativa. */
    case Consumption = 'consumo';

    /** Correção de inventário (contagem, perda, vencimento): qualquer sinal. */
    case Adjustment = 'ajuste';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchase => 'Entrada',
            self::Consumption => 'Consumo em atendimento',
            self::Adjustment => 'Ajuste',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Purchase => 'success',
            self::Consumption => 'gray',
            self::Adjustment => 'warning',
        };
    }

    /**
     * Mesma regra da constraint stock_movements_type_check.
     */
    public function acceptsQuantity(float $quantity): bool
    {
        return match ($this) {
            self::Purchase => $quantity > 0,
            self::Consumption => $quantity < 0,
            self::Adjustment => $quantity != 0,
        };
    }
}
