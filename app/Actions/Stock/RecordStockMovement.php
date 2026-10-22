<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\Appointment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra um lançamento no livro de estoque e atualiza o saldo do produto
 * na mesma transação, com lock no produto. É o único caminho que altera
 * products.current_stock.
 *
 * O saldo pode ficar negativo: um atendimento não deixa de ser concluído
 * porque o estoque registrado está desatualizado; o produto entra no
 * alerta de reposição.
 */
class RecordStockMovement
{
    /**
     * @param  string|int|float  $quantity  Com sinal: entrada > 0, consumo < 0, ajuste ≠ 0.
     *
     * @throws ValidationException quando a quantidade não combina com o tipo.
     */
    public function handle(
        Product $product,
        StockMovementType $type,
        string|int|float $quantity,
        ?Appointment $appointment = null,
        ?string $description = null,
        ?User $user = null,
    ): StockMovement {
        if (! $type->acceptsQuantity((float) $quantity)) {
            throw ValidationException::withMessages([
                'quantity' => match ($type) {
                    StockMovementType::Purchase => 'A quantidade de uma entrada deve ser maior que zero.',
                    StockMovementType::Consumption => 'A quantidade de um consumo deve ser negativa.',
                    StockMovementType::Adjustment => 'A quantidade de um ajuste não pode ser zero.',
                },
            ]);
        }

        return DB::transaction(function () use ($product, $type, $quantity, $appointment, $description, $user): StockMovement {
            $product = Product::whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $product->increment('current_stock', $quantity);
            $product->refresh();

            return StockMovement::create([
                'product_id' => $product->getKey(),
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $product->current_stock,
                'appointment_id' => $appointment?->getKey(),
                'description' => $description,
                'user_id' => $user?->getKey(),
            ]);
        });
    }
}
