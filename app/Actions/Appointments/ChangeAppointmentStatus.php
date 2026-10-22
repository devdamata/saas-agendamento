<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Muda o status do agendamento seguindo o fluxo permitido e registra a
 * mudança em appointment_events.
 */
class ChangeAppointmentStatus
{
    /**
     * @param  User|null  $changedBy  null quando a mudança vem do sistema (ex.: webhook).
     *
     * @throws ValidationException quando a transição não é permitida.
     */
    public function handle(Appointment $appointment, AppointmentStatus $status, ?User $changedBy = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $status, $changedBy): Appointment {
            $appointment = Appointment::whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $previousStatus = $appointment->status;

            if (! $previousStatus->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => 'Não é possível mudar um agendamento '.mb_strtolower($previousStatus->label()).' para '.mb_strtolower($status->label()).'.',
                ]);
            }

            $appointment->update(['status' => $status]);

            $appointment->events()->create([
                'from_status' => $previousStatus,
                'to_status' => $status,
                'user_id' => $changedBy?->getKey(),
            ]);

            return $appointment;
        });
    }
}
