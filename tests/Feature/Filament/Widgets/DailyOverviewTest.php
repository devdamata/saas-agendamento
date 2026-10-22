<?php

namespace Tests\Feature\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\CashCategory;
use App\Enums\MembershipRole;
use App\Filament\Widgets\DailyOverview;
use App\Models\Appointment;
use App\Models\CashEntry;
use App\Models\Product;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Number;
use Livewire\Livewire;
use Tests\TestCase;

use function Livewire\invade;

class DailyOverviewTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/Sao_Paulo';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', self::TIMEZONE));
    }

    public function test_mostra_atendimentos_de_hoje_no_fuso_do_salao(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        Appointment::factory()->at($this->at('2026-11-20 09:00'))->status(AppointmentStatus::Completed)->create();
        Appointment::factory()->at($this->at('2026-11-20 14:00'))->status(AppointmentStatus::Confirmed)->create();
        Appointment::factory()->at($this->at('2026-11-20 22:30'))->create();
        Appointment::factory()->at($this->at('2026-11-20 16:00'))->status(AppointmentStatus::Cancelled)->create();
        Appointment::factory()->at($this->at('2026-11-21 09:00'))->create();

        $stat = $this->stat('Atendimentos hoje');

        $this->assertSame(3, $stat->getValue());
        $this->assertSame('1 confirmados · 1 concluídos', $stat->getDescription());
    }

    public function test_mostra_faturamento_do_dia_e_do_mes(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        CashEntry::factory()->create(['amount' => '150.00', 'occurred_at' => $this->at('2026-11-20 09:30')]);
        CashEntry::factory()->create(['amount' => '200.00', 'occurred_at' => $this->at('2026-11-03 15:00')]);
        CashEntry::factory()->expense(CashCategory::Rent)->create(['amount' => '400.00', 'occurred_at' => $this->at('2026-11-05 08:00')]);
        CashEntry::factory()->create(['amount' => '999.00', 'occurred_at' => $this->at('2026-10-31 23:00')]);

        $this->assertSame($this->money(150), $this->stat('Faturamento hoje')->getValue());
        $month = $this->stat('Faturamento do mês');
        $this->assertSame($this->money(350), $month->getValue());
        $this->assertSame('Saídas '.$this->money(400).' · Saldo '.$this->money(-50), $month->getDescription());
    }

    public function test_conta_produtos_ativos_para_repor(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        Product::factory()->withStock(100)->create(['minimum_stock' => 200]);
        Product::factory()->withStock(100)->create(['minimum_stock' => 200, 'is_active' => false]);
        Product::factory()->withStock(500)->create(['minimum_stock' => 200]);

        $this->assertSame(1, $this->stat('Produtos para repor')->getValue());
    }

    public function test_profissional_nao_ve_os_numeros_financeiros(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(), MembershipRole::Professional);

        $this->assertFalse(DailyOverview::canView());
    }

    private function stat(string $label): Stat
    {
        $stats = invade(Livewire::test(DailyOverview::class)->instance())->getStats();

        return collect($stats)->sole(fn (Stat $stat): bool => $stat->getLabel() === $label);
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, self::TIMEZONE);
    }

    private function money(float $amount): string
    {
        return Number::currency($amount, 'BRL', 'pt_BR');
    }
}
