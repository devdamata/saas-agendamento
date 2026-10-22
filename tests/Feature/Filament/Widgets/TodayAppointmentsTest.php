<?php

namespace Tests\Feature\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Filament\Widgets\TodayAppointments;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TodayAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/Sao_Paulo';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', self::TIMEZONE));
    }

    public function test_lista_os_atendimentos_de_hoje_menos_os_cancelados(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        $morning = Appointment::factory()->at($this->at('2026-11-20 09:00'))->create();
        $night = Appointment::factory()->at($this->at('2026-11-20 22:00'))->create();
        $cancelled = Appointment::factory()->at($this->at('2026-11-20 11:00'))->status(AppointmentStatus::Cancelled)->create();
        $tomorrow = Appointment::factory()->at($this->at('2026-11-21 09:00'))->create();
        $otherSalon = app(TenantContext::class)->run(
            Tenant::factory()->create(),
            fn () => Appointment::factory()->at($this->at('2026-11-20 09:00'))->create(),
        );

        Livewire::test(TodayAppointments::class)
            ->assertCanSeeTableRecords([$morning, $night], inOrder: true)
            ->assertCanNotSeeTableRecords([$cancelled, $tomorrow, $otherSalon]);
    }

    public function test_conclui_atendimento_pela_tela_inicial(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        $appointment = Appointment::factory()->at($this->at('2026-11-20 09:00'))->create(['price' => '70.00']);

        Livewire::test(TodayAppointments::class)
            ->callAction(TestAction::make('complete')->table($appointment), [
                'amount' => '70',
                'payment_method' => PaymentMethod::Cash->value,
            ])
            ->assertNotified('Atendimento concluído.');

        $this->assertSame(AppointmentStatus::Completed, $appointment->refresh()->status);
        $this->assertSame('70.00', $appointment->cashEntry->amount);
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, self::TIMEZONE);
    }
}
