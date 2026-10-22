<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Grava o lançamento sem atualizar o saldo do produto. Para movimentar o
 * estoque de verdade, use o RecordStockMovement (ou ProductFactory::withStock).
 *
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'type' => StockMovementType::Purchase,
            'quantity' => 10,
            'balance_after' => 10,
        ];
    }
}
