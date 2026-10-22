<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Stock\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Estoque inicial entra no livro como uma entrada.
     */
    protected function afterCreate(): void
    {
        $initialStock = (float) ($this->data['initial_stock'] ?? 0);

        if ($initialStock > 0) {
            /** @var Product $product */
            $product = $this->getRecord();

            app(RecordStockMovement::class)->handle($product, StockMovementType::Purchase, $initialStock, description: 'Estoque inicial', user: auth()->user());
        }
    }
}
