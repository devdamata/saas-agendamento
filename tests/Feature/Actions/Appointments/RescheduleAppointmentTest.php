<?php

namespace Tests\Feature\Actions\Appointments;

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\ListAvailableSlots;
use App\Actions\Appointments\RescheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RescheduleAppointmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Terça-feira; o "agora" dos testes é a segunda anterior.
     */
    private const DAY = '2026-11-03';

    private const TIMEZONE = 'America/Sao_Paulo';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', self::TIMEZONE));
        app(TenantContext::class)->set(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
    }

    public function test_move_para_horario_livre_e_registra_o_anterior_no_historico(): void
    {
        [$ana, $service] = $this->professionalWithService('Ana', bufferMinutes: 15);
        $appointment = $this->book($ana, $service, '10:00');
        $staff = User::factory()->create();

        app(RescheduleAppointment::class)->handle($appointment, $this->at('15:00'), rescheduledBy: $staff);

        $appointment->refresh();
        $this->assertTrue($appointment->starts_at->equalTo($this->at('15:00')));
        $this->assertTrue($appointment->ends_at->equalTo($this->at('16:00')));
        $this->assertTrue($appointment->blocked_until->equalTo($this->at('16:15')));
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'user_id' => $staff->id,
            'description' => 'Remarcado de 03/11/2026 10:00 com Ana para 03/11/2026 15:00 com Ana',
        ]);
    }

    public function test_pode_trocar_de_profissional(): void
    {
        [$ana, $service] = $this->professionalWithService('Ana');
        [$bruna] = $this->professionalWithService('Bruna', service: $service);
        $appointment = $this->book($ana, $service, '10:00');

        app(RescheduleAppointment::class)->handle($appointment, $this->at('10:00'), $bruna);

        $this->assertTrue($appointment->refresh()->professional->is($bruna));
    }

    public function test_pode_adiar_para_horario_que_se_sobrepoe_ao_proprio_horario_atual(): void
    {
        [$ana, $service] = $this->professionalWithService('Ana');
        $appointment = $this->book($ana, $service, '10:00');

        app(RescheduleAppointment::class)->handle($appointment, $this->at('10:30'));

        $this->assertTrue($appointment->refresh()->starts_at->equalTo($this->at('10:30')));
    }

    public function test_mantem_o_status_confirmado(): void
    {
        [$ana, $service] = $this->professionalWithService('Ana');
        $appointment = $this->book($ana, $service, '10:00');
        $appointment->update(['status' => AppointmentStatus::Confirmed]);

        app(RescheduleAppointment::class)->handle($appointment, $this->at('14:00'));

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->refresh()->status);
    }

    public function test_recusa_horario_ocupado_por_outro_agendamento(): void
    {
        [$ana, $service] = $this->professionalWithService('Ana');
        $appointment = $this->book($ana, $service, '10:00');
        $this->book($ana, $service, '14:00');

        $this->assertUnavailable(fn () => app(RescheduleAppointment::class)->handle($appointment, $this->at('14:30')));
        $this->assertTrue($appointment->refresh()->starts_at->equalTo($this->at('10:00')));
    }

    public function test_recusa_profissional_que_nao_faz_o_servico(): void
    {
        [$ana, $service] = $this->professionalWithService('Ana');
        [$bruna] = $this->professionalWithService('Bruna');
        $appointment = $this->book($ana, $service, '10:00');

        $this->assertUnavailable(fn () => app(RescheduleAppointment::class)->handle($appointment, $this->at('10:00'), $bruna));
    }

    public function test_recusa_profissional_de_outro_salao(): void
    {
        [$ana, $service] = $this->professionalWithService('Ana');
        $appointment = $this->book($ana, $service, '10:00');
        $otherSalonProfessional = app(TenantContext::class)->run(Tenant::factory()->create(), fn () => Professional::factory()->create());

        $this->assertUnavailable(fn () => app(RescheduleAppointment::class)->handle($appointment, $this->at('11:00'), $otherSalonProfessional));
    }

    public function test_conflito_barrado_pelo_banco_vira_erro_de_horario_indisponivel(): void
    {
        [$ana, $service] = $this->professionalWithService('Ana');
        $appointment = $this->book($ana, $service, '10:00');
        $this->book($ana, $service, '14:00');
        $this->mock(ListAvailableSlots::class, function (MockInterface $mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
        });

        $this->assertUnavailable(fn () => app(RescheduleAppointment::class)->handle($appointment, $this->at('14:00')));
        $this->assertTrue($appointment->refresh()->starts_at->equalTo($this->at('10:00')));
    }

    #[DataProvider('statusFinais')]
    public function test_nao_remarca_agendamento_encerrado(AppointmentStatus $status): void
    {
        $appointment = Appointment::factory()->status($status)->create();

        try {
            app(RescheduleAppointment::class)->handle($appointment, $this->at('14:00'));
            $this->fail('O agendamento foi remarcado.');
        } catch (ValidationException $exception) {
            $this->assertSame(['status' => ['Só é possível remarcar agendamentos agendados ou confirmados.']], $exception->errors());
        }
    }

    /**
     * @return array<string, array{AppointmentStatus}>
     */
    public static function statusFinais(): array
    {
        return [
            'concluído' => [AppointmentStatus::Completed],
            'cancelado' => [AppointmentStatus::Cancelled],
            'faltou' => [AppointmentStatus::NoShow],
        ];
    }

    /**
     * @return array{Professional, Service}
     */
    private function professionalWithService(string $name, ?Service $service = null, int $bufferMinutes = 0): array
    {
        $professional = Professional::factory()->create(['name' => $name]);
        $service ??= Service::factory()->create(['duration_minutes' => 60, 'buffer_minutes' => $bufferMinutes]);
        $professional->services()->attach($service);
        WorkingHour::factory()->for($professional)->create([
            'weekday' => CarbonImmutable::parse(self::DAY)->dayOfWeek,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ]);

        return [$professional, $service];
    }

    private function book(Professional $professional, Service $service, string $time): Appointment
    {
        return app(BookAppointment::class)->handle($professional, $service, User::factory()->create(), $this->at($time));
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(self::DAY.' '.$time, self::TIMEZONE);
    }

    private function assertUnavailable(callable $reschedule): void
    {
        try {
            $reschedule();
            $this->fail('A remarcação foi aceita.');
        } catch (ValidationException $exception) {
            $this->assertSame(['starts_at' => [BookAppointment::UNAVAILABLE_MESSAGE]], $exception->errors());
        }
    }
}
