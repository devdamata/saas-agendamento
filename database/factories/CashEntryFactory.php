<?php

namespace Database\Factories;

use App\Enums\CashCategory;
use App\Enums\PaymentMethod;
use App\Models\CashEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crie dentro de um tenant ativo (TenantContext::run), como os demais models do salão.
 *
 * @extends Factory<CashEntry>
 */
class CashEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => CashCategory::OtherIncome,
            'amount' => fake()->randomFloat(2, 10, 300),
            'payment_method' => PaymentMethod::Pix,
            'occurred_at' => now(),
        ];
    }

    public function expense(CashCategory $category = CashCategory::OtherExpense): static
    {
        return $this->state(['category' => $category]);
    }
}
