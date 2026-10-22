<?php

namespace App\Filament\Resources\CashEntries\Widgets;

use App\Enums\CashEntryType;
use App\Filament\Resources\CashEntries\Pages\ManageCashEntries;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Totais do caixa para os mesmos filtros da tabela.
 */
class CashFlowOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ManageCashEntries::class;
    }

    protected function getStats(): array
    {
        $income = $this->sumOf(CashEntryType::Income);
        $expense = $this->sumOf(CashEntryType::Expense);
        $balance = $income - $expense;

        return [
            Stat::make('Entradas', $this->money($income))->color('success'),
            Stat::make('Saídas', $this->money($expense))->color('danger'),
            Stat::make('Saldo', $this->money($balance))->color($balance >= 0 ? 'success' : 'danger'),
        ];
    }

    private function sumOf(CashEntryType $type): float
    {
        return (float) $this->getPageTableQuery()->reorder()->where('type', $type)->sum('amount');
    }

    private function money(float $amount): string
    {
        return Number::currency($amount, 'BRL', 'pt_BR');
    }
}
