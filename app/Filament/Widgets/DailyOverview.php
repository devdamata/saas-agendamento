<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\CashEntryType;
use App\Enums\MembershipRole;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\CashEntries\CashEntryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Appointment;
use App\Models\CashEntry;
use App\Models\Product;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Números do dia e do mês na tela inicial. Só para administradores, por
 * mostrar o financeiro.
 */
class DailyOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasRoleInCurrentTenant(MembershipRole::Admin) ?? false;
    }

    protected function getStats(): array
    {
        $now = app(TenantContext::class)->now();
        $today = $now->startOfDay();
        $month = $now->startOfMonth();

        $appointmentsToday = Appointment::occupying()
            ->where('starts_at', '>=', $today->utc())
            ->where('starts_at', '<', $today->addDay()->utc())
            ->pluck('status');

        $monthIncome = $this->sumOf(CashEntryType::Income, $month, $month->addMonth());
        $monthExpense = $this->sumOf(CashEntryType::Expense, $month, $month->addMonth());
        $productsToRestock = Product::active()->belowMinimum()->count();

        return [
            Stat::make('Atendimentos hoje', $appointmentsToday->count())
                ->description(sprintf(
                    '%d confirmados · %d concluídos',
                    $appointmentsToday->filter(fn (AppointmentStatus $status): bool => $status === AppointmentStatus::Confirmed)->count(),
                    $appointmentsToday->filter(fn (AppointmentStatus $status): bool => $status === AppointmentStatus::Completed)->count(),
                ))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->url(AppointmentResource::getUrl('index')),
            Stat::make('Faturamento hoje', $this->money($this->sumOf(CashEntryType::Income, $today, $today->addDay())))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->url(CashEntryResource::getUrl('index')),
            Stat::make('Faturamento do mês', $this->money($monthIncome))
                ->description('Saídas '.$this->money($monthExpense).' · Saldo '.$this->money($monthIncome - $monthExpense))
                ->icon(Heroicon::OutlinedChartBar)
                ->color($monthIncome - $monthExpense >= 0 ? 'success' : 'danger')
                ->url(CashEntryResource::getUrl('index')),
            Stat::make('Produtos para repor', $productsToRestock)
                ->description($productsToRestock > 0 ? 'No estoque mínimo ou abaixo' : 'Estoque em dia')
                ->icon(Heroicon::OutlinedBeaker)
                ->color($productsToRestock > 0 ? 'danger' : 'success')
                ->url(ProductResource::getUrl('index')),
        ];
    }

    private function sumOf(CashEntryType $type, CarbonImmutable $from, CarbonImmutable $until): float
    {
        return (float) CashEntry::where('type', $type)
            ->where('occurred_at', '>=', $from->utc())
            ->where('occurred_at', '<', $until->utc())
            ->sum('amount');
    }

    private function money(float $amount): string
    {
        return Number::currency($amount, 'BRL', 'pt_BR');
    }
}
