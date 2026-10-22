<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Muda o status do agendamento seguindo o fluxo permitido e registra a
 * mudança em appointment_events. Para concluir, use o CompleteAppointment,
 * que também dá baixa no estoque e lança no caixa.
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
        if ($status === AppointmentStatus::Completed) {
            throw new InvalidArgumentException('Use o CompleteAppointment para concluir: ele dá baixa no estoque e lança no caixa.');
        }

        return DB::transaction(function () use ($appointment, $status, $changedBy): Appointment {
            $appointment = Appointment::whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $appointment->transitionTo($status, $changedBy);

            return $appointment;
        });
    }
}
