<?php

namespace Tests\Feature\Actions\Appointments;

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\ListAvailableSlots;
use App\Enums\AppointmentStatus;
use App\Enums\MembershipRole;
use App\Models\Appointment;
use App\Models\Membership;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class BookAppointmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Terça-feira; o "agora" dos testes é a segunda anterior.
     */
    private const DAY = '2026-11-03';

    private const TIMEZONE = 'America/Sao_Paulo';

    private const UNAVAILABLE_MESSAGE = 'Esse horário não está disponível. Escolha outro horário.';

    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', self::TIMEZONE));
        $this->tenantContext = app(TenantContext::class);
        $this->tenantContext->set(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
    }

    public function test_cria_agendamento_no_horario_livre_com_historico_e_vinculo_de_cliente(): void
    {
        [$professional, $service] = $this->professionalWithService(durationMinutes: 45, bufferMinutes: 15, price: '120.00');
        $client = User::factory()->create();
        $receptionist = User::factory()->create();

        $appointment = app(BookAppointment::class)->handle($professional, $service, $client, $this->at('10:00'), $receptionist);

        $this->assertTrue($appointment->starts_at->equalTo($this->at('10:00')));
        $this->assertTrue($appointment->ends_at->equalTo($this->at('10:45')));
        $this->assertTrue($appointment->blocked_until->equalTo($this->at('11:00')));
        $this->assertSame(AppointmentStatus::Scheduled, $appointment->status);
        $this->assertSame('120.00', $appointment->price);
        $this->assertTrue($appointment->client->is($client));
        $this->assertTrue($appointment->creator->is($receptionist));
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'from_status' => null,
            'to_status' => AppointmentStatus::Scheduled->value,
            'user_id' => $receptionist->id,
        ]);
        $this->assertTrue(Membership::where('user_id', $client->id)->where('role', MembershipRole::Client)->exists());
    }

    public function test_grava_o_instante_correto_quando_o_horario_vem_no_fuso_do_salao(): void
    {
        [$professional, $service] = $this->professionalWithService();

        app(BookAppointment::class)->handle($professional, $service, User::factory()->create(), $this->at('09:00'));

        $this->assertSame(
            '2026-11-03 12:00',
            DB::table('appointments')->value(DB::raw("to_char(starts_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI')")),
        );
    }

    public function test_cliente_que_ja_e_do_salao_nao_ganha_vinculo_duplicado(): void
    {
        [$professional, $service] = $this->professionalWithService();
        $client = User::factory()->create();
        Membership::factory()->for($client)->create();

        app(BookAppointment::class)->handle($professional, $service, $client, $this->at('09:00'));

        $this->assertSame(1, Membership::where('user_id', $client->id)->count());
    }

    public function test_recusa_horario_que_colide_com_outro_agendamento(): void
    {
        [$professional, $service] = $this->professionalWithService();
        Appointment::factory()->for($professional)->at($this->at('09:30'))->create();

        $this->assertUnavailable(fn () => app(BookAppointment::class)->handle($professional, $service, User::factory()->create(), $this->at('09:00')));
        $this->assertSame(1, Appointment::count());
    }

    public function test_recusa_horario_fora_da_jornada(): void
    {
        [$professional, $service] = $this->professionalWithService();

        $this->assertUnavailable(fn () => app(BookAppointment::class)->handle($professional, $service, User::factory()->create(), $this->at('08:00')));
    }

    public function test_recusa_profissional_de_outro_salao(): void
    {
        [, $service] = $this->professionalWithService();
        $otherSalonProfessional = $this->tenantContext->run(
            Tenant::factory()->create(),
            fn () => Professional::factory()->create(),
        );

        $this->assertUnavailable(fn () => app(BookAppointment::class)->handle($otherSalonProfessional, $service, User::factory()->create(), $this->at('09:00')));
        $this->assertSame(0, Appointment::withoutTenancy()->count());
    }

    public function test_conflito_barrado_pelo_banco_vira_erro_de_horario_indisponivel(): void
    {
        [$professional, $service] = $this->professionalWithService();
        Appointment::factory()->for($professional)->at($this->at('09:00'))->create();
        $this->mock(ListAvailableSlots::class, function (MockInterface $mock) {
            $mock->shouldReceive('handle')->andReturn([$this->at('09:00')]);
        });

        $this->assertUnavailable(fn () => app(BookAppointment::class)->handle($professional, $service, User::factory()->create(), $this->at('09:00')));
        $this->assertSame(1, Appointment::count());
    }

    public function test_banco_recusa_agendamentos_sobrepostos_do_mesmo_profissional(): void
    {
        $professional = Professional::factory()->create();
        Appointment::factory()->for($professional)->at($this->at('09:00'), bufferMinutes: 15)->create();

        try {
            Appointment::factory()->for($professional)->at($this->at('10:10'))->create();
            $this->fail('O banco aceitou um agendamento sobreposto.');
        } catch (QueryException $exception) {
            $this->assertSame('23P01', $exception->getCode());
        }
    }

    public function test_banco_aceita_novo_agendamento_no_horario_de_um_cancelado(): void
    {
        $professional = Professional::factory()->create();
        Appointment::factory()->for($professional)->at($this->at('09:00'))->status(AppointmentStatus::Cancelled)->create();

        Appointment::factory()->for($professional)->at($this->at('09:00'))->create();

        $this->assertSame(2, Appointment::count());
    }

    /**
     * @return array{Professional, Service}
     */
    private function professionalWithService(int $durationMinutes = 60, int $bufferMinutes = 0, string $price = '80.00'): array
    {
        $professional = Professional::factory()->create();
        $service = Service::factory()->create([
            'duration_minutes' => $durationMinutes,
            'buffer_minutes' => $bufferMinutes,
            'price' => $price,
        ]);
        $professional->services()->attach($service);
        WorkingHour::factory()->for($professional)->create([
            'weekday' => CarbonImmutable::parse(self::DAY)->dayOfWeek,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ]);

        return [$professional, $service];
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(self::DAY.' '.$time, self::TIMEZONE);
    }

    private function assertUnavailable(callable $booking): void
    {
        try {
            $booking();
            $this->fail('O agendamento foi aceito.');
        } catch (ValidationException $exception) {
            $this->assertSame(['starts_at' => [self::UNAVAILABLE_MESSAGE]], $exception->errors());
        }
    }
}
