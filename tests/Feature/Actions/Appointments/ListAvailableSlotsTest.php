<?php

namespace Tests\Feature\Actions\Appointments;

use App\Actions\Appointments\ListAvailableSlots;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListAvailableSlotsTest extends TestCase
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

    public function test_oferece_inicios_a_cada_15_minutos_enquanto_o_servico_cabe_na_jornada(): void
    {
        [$professional, $service] = $this->professionalWithService(hours: [['09:00', '11:00']]);

        $this->assertSame(
            ['09:00', '09:15', '09:30', '09:45', '10:00'],
            $this->slots($professional, $service),
        );
    }

    public function test_nao_oferece_horarios_na_pausa_entre_intervalos(): void
    {
        [$professional, $service] = $this->professionalWithService(hours: [['09:00', '10:30'], ['13:00', '14:00']]);

        $this->assertSame(['09:00', '09:15', '09:30', '13:00'], $this->slots($professional, $service));
    }

    public function test_jornada_segue_o_fuso_do_salao(): void
    {
        [$professional, $service] = $this->professionalWithService(hours: [['09:00', '10:00']]);

        $slots = app(ListAvailableSlots::class)->handle($professional, $service, self::DAY);

        $this->assertSame('2026-11-03 12:00', $slots[0]->utc()->format('Y-m-d H:i'));
    }

    public function test_exclui_horarios_que_colidem_com_agendamento_considerando_os_buffers(): void
    {
        [$professional, $service] = $this->professionalWithService(hours: [['09:00', '13:00']], bufferMinutes: 15);
        Appointment::factory()
            ->for($professional)
            ->at($this->at('10:30'), durationMinutes: 60, bufferMinutes: 30)
            ->create();

        // Ocupado de 10:30 a 12:00. 09:15 + 60 + 15 de buffer termina exatamente às 10:30.
        $this->assertSame(['09:00', '09:15', '12:00'], $this->slots($professional, $service));
    }

    public function test_agendamento_cancelado_ou_falta_nao_ocupa_o_horario(): void
    {
        [$professional, $service] = $this->professionalWithService(hours: [['09:00', '10:00']]);
        Appointment::factory()->for($professional)->at($this->at('09:00'))->status(AppointmentStatus::Cancelled)->create();
        Appointment::factory()->for($professional)->at($this->at('09:00'))->status(AppointmentStatus::NoShow)->create();

        $this->assertSame(['09:00'], $this->slots($professional, $service));
    }

    public function test_agendamento_de_outro_profissional_nao_ocupa_o_horario(): void
    {
        [$professional, $service] = $this->professionalWithService(hours: [['09:00', '10:00']]);
        Appointment::factory()->at($this->at('09:00'))->create();

        $this->assertSame(['09:00'], $this->slots($professional, $service));
    }

    public function test_exclui_horarios_durante_folga_ou_bloqueio(): void
    {
        [$professional, $service] = $this->professionalWithService(hours: [['09:00', '11:00']]);
        TimeOff::factory()->for($professional)->create([
            'starts_at' => $this->at('09:00')->utc(),
            'ends_at' => $this->at('10:00')->utc(),
        ]);

        $this->assertSame(['10:00'], $this->slots($professional, $service));
    }

    public function test_nao_oferece_horarios_que_ja_passaram(): void
    {
        [$professional, $service] = $this->professionalWithService(hours: [['09:00', '11:00']]);
        $this->travelTo($this->at('09:20'));

        $this->assertSame(['09:30', '09:45', '10:00'], $this->slots($professional, $service));
    }

    /**
     * @param  Closure(Professional, Service): void  $makeUnavailable
     */
    #[DataProvider('semHorarios')]
    public function test_nao_oferece_horarios(Closure $makeUnavailable): void
    {
        [$professional, $service] = $this->professionalWithService(hours: [['09:00', '11:00']]);

        $makeUnavailable($professional, $service);

        $this->assertSame([], $this->slots($professional->refresh(), $service->refresh()));
    }

    /**
     * @return array<string, array{Closure(Professional, Service): void}>
     */
    public static function semHorarios(): array
    {
        return [
            'profissional inativo' => [fn (Professional $professional) => $professional->update(['is_active' => false])],
            'serviço inativo' => [fn (Professional $professional, Service $service) => $service->update(['is_active' => false])],
            'profissional não faz o serviço' => [fn (Professional $professional, Service $service) => $professional->services()->detach($service)],
            'sem jornada no dia da semana' => [fn (Professional $professional) => $professional->workingHours()->update(['weekday' => 3])],
        ];
    }

    /**
     * Profissional que faz o serviço, com jornada na terça (DAY).
     *
     * @param  list<array{string, string}>  $hours
     * @return array{Professional, Service}
     */
    private function professionalWithService(array $hours, int $durationMinutes = 60, int $bufferMinutes = 0): array
    {
        $professional = Professional::factory()->create();
        $service = Service::factory()->create([
            'duration_minutes' => $durationMinutes,
            'buffer_minutes' => $bufferMinutes,
        ]);
        $professional->services()->attach($service);

        foreach ($hours as [$startsAt, $endsAt]) {
            WorkingHour::factory()->for($professional)->create([
                'weekday' => CarbonImmutable::parse(self::DAY)->dayOfWeek,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        }

        return [$professional, $service];
    }

    /**
     * @return list<string>
     */
    private function slots(Professional $professional, Service $service): array
    {
        return array_map(
            fn (CarbonImmutable $slot): string => $slot->format('H:i'),
            app(ListAvailableSlots::class)->handle($professional, $service, self::DAY),
        );
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(self::DAY.' '.$time, self::TIMEZONE);
    }
}
