<?php

namespace Database\Factories;

use App\Actions\Stock\RecordStockMovement;
use App\Enums\ProductUnit;
use App\Enums\StockMovementType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crie dentro de um tenant ativo (TenantContext::run), como os demais models do salão.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Shampoo', 'Condicionador', 'Máscara de hidratação', 'Esmalte', 'Acetona', 'Tintura']),
            'unit' => ProductUnit::Milliliter,
            'minimum_stock' => 0,
            'cost' => fake()->randomFloat(2, 0.05, 2),
            'is_active' => true,
        ];
    }

    /**
     * Saldo inicial registrado como entrada no livro de estoque.
     */
    public function withStock(string|int|float $quantity): static
    {
        return $this->afterCreating(function (Product $product) use ($quantity): void {
            app(RecordStockMovement::class)->handle($product, StockMovementType::Purchase, $quantity, description: 'Estoque inicial');
            $product->refresh();
        });
    }
}
