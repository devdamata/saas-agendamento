<?php

namespace Tests\Feature\Actions\Appointments;

use App\Actions\Appointments\ChangeAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChangeAppointmentStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->set(Tenant::factory()->create());
    }

    #[DataProvider('transicoesPermitidas')]
    public function test_muda_o_status_e_registra_no_historico(AppointmentStatus $from, AppointmentStatus $to): void
    {
        $appointment = Appointment::factory()->status($from)->create();
        $staff = User::factory()->create();

        $updated = app(ChangeAppointmentStatus::class)->handle($appointment, $to, $staff);

        $this->assertSame($to, $updated->status);
        $this->assertSame($to, $appointment->refresh()->status);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'user_id' => $staff->id,
        ]);
    }

    /**
     * @return array<string, array{AppointmentStatus, AppointmentStatus}>
     */
    public static function transicoesPermitidas(): array
    {
        return [
            'agendado → confirmado' => [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed],
            'agendado → concluído' => [AppointmentStatus::Scheduled, AppointmentStatus::Completed],
            'agendado → cancelado' => [AppointmentStatus::Scheduled, AppointmentStatus::Cancelled],
            'agendado → faltou' => [AppointmentStatus::Scheduled, AppointmentStatus::NoShow],
            'confirmado → concluído' => [AppointmentStatus::Confirmed, AppointmentStatus::Completed],
            'confirmado → cancelado' => [AppointmentStatus::Confirmed, AppointmentStatus::Cancelled],
            'confirmado → faltou' => [AppointmentStatus::Confirmed, AppointmentStatus::NoShow],
        ];
    }

    #[DataProvider('transicoesProibidas')]
    public function test_recusa_transicao_fora_do_fluxo_sem_alterar_nada(AppointmentStatus $from, AppointmentStatus $to): void
    {
        $appointment = Appointment::factory()->status($from)->create();

        try {
            app(ChangeAppointmentStatus::class)->handle($appointment, $to);
            $this->fail('A transição foi aceita.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame($from, $appointment->refresh()->status);
        $this->assertSame(0, AppointmentEvent::count());
    }

    /**
     * @return array<string, array{AppointmentStatus, AppointmentStatus}>
     */
    public static function transicoesProibidas(): array
    {
        return [
            'confirmado → agendado' => [AppointmentStatus::Confirmed, AppointmentStatus::Scheduled],
            'agendado → agendado' => [AppointmentStatus::Scheduled, AppointmentStatus::Scheduled],
            'concluído → cancelado' => [AppointmentStatus::Completed, AppointmentStatus::Cancelled],
            'cancelado → confirmado' => [AppointmentStatus::Cancelled, AppointmentStatus::Confirmed],
            'faltou → concluído' => [AppointmentStatus::NoShow, AppointmentStatus::Completed],
        ];
    }

    public function test_mensagem_de_erro_explica_a_transicao_recusada(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Cancelled)->create();

        try {
            app(ChangeAppointmentStatus::class)->handle($appointment, AppointmentStatus::Confirmed);
            $this->fail('A transição foi aceita.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['status' => ['Não é possível mudar um agendamento cancelado para confirmado.']],
                $exception->errors(),
            );
        }
    }
}
