<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crie dentro de um tenant ativo (TenantContext::run), como os demais models do salão.
 *
 * @extends Factory<WorkingHour>
 */
class WorkingHourFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'professional_id' => Professional::factory(),
            'weekday' => 1,
            'starts_at' => '09:00:00',
            'ends_at' => '18:00:00',
        ];
    }
}
