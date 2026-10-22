<?php

namespace Tests\Feature\Filament\Resources\CashEntries;

use App\Enums\CashCategory;
use App\Enums\CashEntryType;
use App\Enums\MembershipRole;
use App\Enums\PaymentMethod;
use App\Filament\Resources\CashEntries\Pages\ManageCashEntries;
use App\Filament\Resources\CashEntries\Widgets\CashFlowOverview;
use App\Models\CashEntry;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Number;
use Livewire\Livewire;
use Tests\TestCase;

class ManageCashEntriesTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/Sao_Paulo';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', self::TIMEZONE));
    }

    public function test_lanca_saida_manual(): void
    {
        $staff = $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));

        Livewire::test(ManageCashEntries::class)
            ->callAction(CreateAction::class, [
                'type' => CashEntryType::Expense->value,
                'category' => CashCategory::Rent->value,
                'amount' => '1500',
                'payment_method' => PaymentMethod::Pix->value,
                'occurred_at' => '2026-11-05 09:00',
                'description' => 'Aluguel de novembro',
            ])
            ->assertHasNoFormErrors();

        $entry = CashEntry::sole();
        $this->assertSame(CashEntryType::Expense, $entry->type);
        $this->assertSame('1500.00', $entry->amount);
        $this->assertTrue($entry->user->is($staff));
    }

    public function test_mostra_por_padrao_o_mes_atual(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        $thisMonth = CashEntry::factory()->create(['occurred_at' => $this->at('2026-11-01 08:00')]);
        $lastMonth = CashEntry::factory()->create(['occurred_at' => $this->at('2026-10-31 23:00')]);

        Livewire::test(ManageCashEntries::class)
            ->assertCanSeeTableRecords([$thisMonth])
            ->assertCanNotSeeTableRecords([$lastMonth]);
    }

    public function test_totais_seguem_os_filtros_da_tabela(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        CashEntry::factory()->create(['amount' => '300.00', 'occurred_at' => $this->at('2026-11-10 10:00')]);
        CashEntry::factory()->create(['amount' => '200.00', 'occurred_at' => $this->at('2026-11-11 10:00')]);
        CashEntry::factory()->expense(CashCategory::Bills)->create(['amount' => '120.50', 'occurred_at' => $this->at('2026-11-12 10:00')]);
        CashEntry::factory()->create(['amount' => '999.00', 'occurred_at' => $this->at('2026-10-15 10:00')]);

        Livewire::test(CashFlowOverview::class, ['tableFilters' => ['period' => ['from' => '2026-11-01', 'until' => '2026-11-20']]])
            ->assertSee(Number::currency(500, 'BRL', 'pt_BR'))
            ->assertSee(Number::currency(120.50, 'BRL', 'pt_BR'))
            ->assertSee(Number::currency(379.50, 'BRL', 'pt_BR'));
    }

    public function test_exporta_csv_com_os_filtros_da_tabela(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        CashEntry::factory()->expense(CashCategory::Rent)->create([
            'amount' => '1500.00',
            'payment_method' => PaymentMethod::Pix,
            'description' => 'Aluguel',
            'occurred_at' => $this->at('2026-11-05 09:00'),
        ]);
        CashEntry::factory()->create(['description' => 'Mês passado', 'occurred_at' => $this->at('2026-10-05 09:00')]);

        $csv = Livewire::test(ManageCashEntries::class)->instance()->exportCsv();

        ob_start();
        $csv->sendContent();
        $content = ob_get_clean();

        $this->assertSame(
            "\xEF\xBB\xBF".implode("\n", [
                'Data;Tipo;Categoria;Descrição;"Forma de pagamento";Valor',
                '"05/11/2026 09:00";Saída;Aluguel;Aluguel;Pix;-1500,00',
            ])."\n",
            $content,
        );
    }

    public function test_data_em_qualquer_fuso_e_gravada_no_instante_correto(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));

        $entry = CashEntry::factory()->create(['occurred_at' => $this->at('2026-11-05 09:00')]);

        $this->assertSame('2026-11-05 12:00', $entry->refresh()->occurred_at->utc()->format('Y-m-d H:i'));
    }

    public function test_nao_mostra_lancamentos_de_outro_salao(): void
    {
        $otherSalonEntry = app(TenantContext::class)->run(
            Tenant::factory()->create(),
            fn () => CashEntry::factory()->create(['occurred_at' => $this->at('2026-11-10 10:00')]),
        );
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));

        Livewire::test(ManageCashEntries::class)->assertCanNotSeeTableRecords([$otherSalonEntry]);
    }

    public function test_profissional_nao_acessa_o_caixa(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant, MembershipRole::Professional);

        $this->get($this->salonUrl($tenant, '/painel/caixa'))->assertForbidden();
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, self::TIMEZONE);
    }
}
