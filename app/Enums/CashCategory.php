<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CashCategory: string implements HasLabel
{
    case Service = 'atendimento';
    case ProductSale = 'venda_produto';
    case OtherIncome = 'outras_entradas';
    case Supplies = 'produtos';
    case Rent = 'aluguel';
    case Payroll = 'salarios';
    case Commission = 'comissoes';
    case Bills = 'contas';
    case Taxes = 'impostos';
    case OtherExpense = 'outras_saidas';

    public function getLabel(): string
    {
        return match ($this) {
            self::Service => 'Atendimento',
            self::ProductSale => 'Venda de produto',
            self::OtherIncome => 'Outras entradas',
            self::Supplies => 'Compra de produtos',
            self::Rent => 'Aluguel',
            self::Payroll => 'Salários',
            self::Commission => 'Comissões',
            self::Bills => 'Contas (água, luz, internet)',
            self::Taxes => 'Impostos e taxas',
            self::OtherExpense => 'Outras saídas',
        };
    }

    public function type(): CashEntryType
    {
        return match ($this) {
            self::Service, self::ProductSale, self::OtherIncome => CashEntryType::Income,
            default => CashEntryType::Expense,
        };
    }

    /**
     * @return array<string, string> valor => rótulo, das categorias do tipo
     */
    public static function optionsFor(CashEntryType $type): array
    {
        return collect(self::cases())
            ->filter(fn (self $category): bool => $category->type() === $type)
            ->mapWithKeys(fn (self $category): array => [$category->value => $category->getLabel()])
            ->all();
    }
}
