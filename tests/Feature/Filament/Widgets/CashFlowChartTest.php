<?php

namespace Tests\Feature\Filament\Widgets;

use App\Enums\CashCategory;
use App\Enums\MembershipRole;
use App\Filament\Widgets\CashFlowChart;
use App\Models\CashEntry;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

use function Livewire\invade;

class CashFlowChartTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/Sao_Paulo';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', self::TIMEZONE));
    }

    public function test_soma_entradas_e_saidas_por_dia_no_fuso_do_salao(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        // 22:00 em São Paulo já é dia 5 em UTC; precisa contar no dia 4.
        CashEntry::factory()->create(['amount' => '100.00', 'occurred_at' => $this->at('2026-11-04 22:00')]);
        CashEntry::factory()->create(['amount' => '50.00', 'occurred_at' => $this->at('2026-11-04 10:00')]);
        CashEntry::factory()->expense(CashCategory::Bills)->create(['amount' => '30.00', 'occurred_at' => $this->at('2026-11-20 08:00')]);
        CashEntry::factory()->create(['amount' => '999.00', 'occurred_at' => $this->at('2026-10-21 23:00')]);

        $data = invade(Livewire::test(CashFlowChart::class)->instance())->getData();

        $this->assertCount(CashFlowChart::DAYS, $data['labels']);
        $this->assertSame('22/10', $data['labels'][0]);
        $this->assertSame('20/11', $data['labels'][CashFlowChart::DAYS - 1]);

        $income = array_combine($data['labels'], $data['datasets'][0]['data']);
        $expense = array_combine($data['labels'], $data['datasets'][1]['data']);
        $this->assertSame(150.0, $income['04/11']);
        $this->assertSame(0.0, $income['05/11']);
        $this->assertSame(30.0, $expense['20/11']);
        $this->assertSame(150.0, array_sum($income));
    }

    public function test_profissional_nao_ve_o_grafico(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(), MembershipRole::Professional);

        $this->assertFalse(CashFlowChart::canView());
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, self::TIMEZONE);
    }
}
