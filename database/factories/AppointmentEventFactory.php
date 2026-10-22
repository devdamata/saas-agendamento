<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crie dentro de um tenant ativo (TenantContext::run), como os demais models do salão.
 *
 * @extends Factory<AppointmentEvent>
 */
class AppointmentEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'from_status' => null,
            'to_status' => AppointmentStatus::Scheduled,
        ];
    }
}
