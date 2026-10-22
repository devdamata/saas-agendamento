<?php

namespace Tests\Feature\Filament\Resources\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Models\Appointment;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProduct;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEZONE = 'America/Sao_Paulo';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-03 08:00', self::TIMEZONE));
    }

    public function test_mostra_por_padrao_os_agendamentos_de_hoje_no_fuso_do_salao(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        $today = Appointment::factory()->at($this->at('2026-11-03 22:00'))->create();
        $tomorrow = Appointment::factory()->at($this->at('2026-11-04 09:00'))->create();

        Livewire::test(ListAppointments::class)
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$tomorrow]);
    }

    public function test_nao_mostra_agendamentos_de_outro_salao(): void
    {
        $otherSalonAppointment = app(TenantContext::class)->run(
            Tenant::factory()->create(),
            fn () => Appointment::factory()->at($this->at('2026-11-03 10:00'))->create(),
        );
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        $ownAppointment = Appointment::factory()->at($this->at('2026-11-03 10:00'))->create();

        Livewire::test(ListAppointments::class)
            ->assertCanSeeTableRecords([$ownAppointment])
            ->assertCanNotSeeTableRecords([$otherSalonAppointment]);
    }

    public function test_confirmar_muda_o_status_e_registra_quem_confirmou(): void
    {
        $staff = $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        $appointment = Appointment::factory()->at($this->at('2026-11-03 10:00'))->create();

        Livewire::test(ListAppointments::class)
            ->callAction(TestAction::make('confirm')->table($appointment))
            ->assertNotified('Agendamento confirmado.');

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->refresh()->status);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'to_status' => AppointmentStatus::Confirmed->value,
            'user_id' => $staff->id,
        ]);
    }

    public function test_concluir_registra_pagamento_e_baixa_o_estoque(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        $product = Product::factory()->withStock(200)->create();
        $service = Service::factory()->create();
        ServiceProduct::factory()->for($service)->for($product)->create(['quantity' => 25]);
        $appointment = Appointment::factory()->for($service)->at($this->at('2026-11-03 10:00'))->create(['price' => '90.00']);

        Livewire::test(ListAppointments::class)
            ->callAction(TestAction::make('complete')->table($appointment), [
                'amount' => '80',
                'payment_method' => PaymentMethod::DebitCard->value,
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Atendimento concluído.');

        $this->assertSame(AppointmentStatus::Completed, $appointment->refresh()->status);
        $this->assertSame('80.00', $appointment->cashEntry->amount);
        $this->assertSame(PaymentMethod::DebitCard, $appointment->cashEntry->payment_method);
        $this->assertSame('175.000', $product->refresh()->current_stock);
    }

    public function test_acoes_de_status_seguem_o_fluxo_permitido(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        $confirmed = Appointment::factory()->at($this->at('2026-11-03 10:00'))->status(AppointmentStatus::Confirmed)->create();
        $cancelled = Appointment::factory()->at($this->at('2026-11-03 11:00'))->status(AppointmentStatus::Cancelled)->create();

        Livewire::test(ListAppointments::class)
            ->assertActionHidden(TestAction::make('confirm')->table($confirmed))
            ->assertActionVisible(TestAction::make('complete')->table($confirmed))
            ->assertActionHidden(TestAction::make('complete')->table($cancelled))
            ->assertActionHidden(TestAction::make('cancel')->table($cancelled));
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, self::TIMEZONE);
    }
}
