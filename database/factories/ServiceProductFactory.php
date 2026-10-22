<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crie dentro de um tenant ativo (TenantContext::run), como os demais models do salão.
 *
 * @extends Factory<ServiceProduct>
 */
class ServiceProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'product_id' => Product::factory(),
            'quantity' => 10,
        ];
    }
}
