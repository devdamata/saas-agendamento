<?php

namespace App\Filament\Widgets;

use App\Enums\CashEntryType;
use App\Enums\MembershipRole;
use App\Models\CashEntry;
use App\Tenancy\TenantContext;
use Filament\Widgets\ChartWidget;

/**
 * Entradas e saídas por dia nos últimos 30 dias, no fuso do salão. Só para
 * administradores.
 */
class CashFlowChart extends ChartWidget
{
    public const DAYS = 30;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Entradas e saídas nos últimos 30 dias';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->hasRoleInCurrentTenant(MembershipRole::Admin) ?? false;
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    protected function getData(): array
    {
        $timezone = app(TenantContext::class)->get()->timezone;
        $firstDay = app(TenantContext::class)->now()->startOfDay()->subDays(self::DAYS - 1);

        $totals = CashEntry::query()
            ->where('occurred_at', '>=', $firstDay->utc())
            ->selectRaw("to_char(occurred_at AT TIME ZONE ?, 'YYYY-MM-DD') AS day, type, SUM(amount) AS total", [$timezone])
            ->groupBy('day', 'type')
            ->toBase()
            ->get()
            ->groupBy('type')
            ->map(fn ($rows) => $rows->pluck('total', 'day'));

        $days = collect(range(0, self::DAYS - 1))->map(fn (int $offset) => $firstDay->addDays($offset));
        $seriesFor = fn (CashEntryType $type): array => $days
            ->map(fn ($day): float => (float) ($totals[$type->value][$day->toDateString()] ?? 0))
            ->all();

        return [
            'labels' => $days->map(fn ($day): string => $day->format('d/m'))->all(),
            'datasets' => [
                [
                    'label' => 'Entradas',
                    'data' => $seriesFor(CashEntryType::Income),
                    'backgroundColor' => 'rgba(22, 163, 74, 0.7)',
                ],
                [
                    'label' => 'Saídas',
                    'data' => $seriesFor(CashEntryType::Expense),
                    'backgroundColor' => 'rgba(220, 38, 38, 0.7)',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
