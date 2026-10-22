<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Crie dentro de um tenant ativo (TenantContext::run), como os demais models do salão.
 *
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDay()->startOfHour();

        return [
            'client_id' => User::factory(),
            'professional_id' => Professional::factory(),
            'service_id' => Service::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'blocked_until' => $startsAt->copy()->addHour(),
            'status' => AppointmentStatus::Scheduled,
            'price' => fake()->randomFloat(2, 30, 300),
        ];
    }

    /**
     * Atendimento em um horário específico, com buffer opcional depois do fim.
     */
    public function at(DateTimeInterface $startsAt, int $durationMinutes = 60, int $bufferMinutes = 0): static
    {
        $startsAt = Carbon::instance($startsAt)->utc();

        return $this->state([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($durationMinutes),
            'blocked_until' => $startsAt->copy()->addMinutes($durationMinutes + $bufferMinutes),
        ]);
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
