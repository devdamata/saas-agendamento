<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Muda o horário (e, se pedido, o profissional) de um agendamento ativo,
 * com as mesmas garantias contra conflito do BookAppointment: lock no
 * profissional e a constraint appointments_no_overlap como garantia final.
 *
 * O status é mantido e a remarcação fica registrada no histórico, com o
 * horário anterior.
 */
class RescheduleAppointment
{
    public function __construct(
        private ListAvailableSlots $listAvailableSlots,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param  Professional|null  $professional  Novo profissional; sem valor, mantém o atual.
     *
     * @throws ValidationException quando o agendamento não pode ser remarcado ou o horário não está livre.
     */
    public function handle(
        Appointment $appointment,
        DateTimeInterface $startsAt,
        ?Professional $professional = null,
        ?User $rescheduledBy = null,
    ): Appointment {
        $startsAt = CarbonImmutable::instance($startsAt)->utc();

        return DB::transaction(function () use ($appointment, $startsAt, $professional, $rescheduledBy): Appointment {
            $appointment = Appointment::whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Só é possível remarcar agendamentos agendados ou confirmados.',
                ]);
            }

            $appointment->load(['professional', 'service']);
            $professional = Professional::whereKey(($professional ?? $appointment->professional)->getKey())->lockForUpdate()->first();
            $service = $appointment->service;

            if ($professional === null || ! $this->listAvailableSlots->isAvailable($professional, $service, $startsAt, ignoring: $appointment)) {
                throw $this->unavailable();
            }

            $previousSlot = $this->describe($appointment->starts_at, $appointment->professional);

            try {
                $appointment->update([
                    'professional_id' => $professional->getKey(),
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->addMinutes($service->duration_minutes),
                    'blocked_until' => $startsAt->addMinutes($service->duration_minutes + $service->buffer_minutes),
                ]);
            } catch (QueryException $exception) {
                throw Appointment::isOverlapViolation($exception) ? $this->unavailable() : $exception;
            }

            $appointment->events()->create([
                'from_status' => $appointment->status,
                'to_status' => $appointment->status,
                'user_id' => $rescheduledBy?->getKey(),
                'description' => 'Remarcado de '.$previousSlot.' para '.$this->describe($startsAt, $professional),
            ]);

            return $appointment->refresh();
        });
    }

    private function describe(CarbonImmutable $startsAt, Professional $professional): string
    {
        return $startsAt->setTimezone($this->tenantContext->get()->timezone)->format('d/m/Y H:i')." com {$professional->name}";
    }

    private function unavailable(): ValidationException
    {
        return ValidationException::withMessages(['starts_at' => BookAppointment::UNAVAILABLE_MESSAGE]);
    }
}
