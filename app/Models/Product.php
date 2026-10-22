<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * current_stock não é preenchível: é o saldo do livro stock_movements e só
 * muda pelo RecordStockMovement.
 */
#[Fillable(['name', 'unit', 'minimum_stock', 'cost', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => ProductUnit::class,
            'current_stock' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Produtos que pedem reposição: no mínimo configurado ou abaixo dele, ou
     * com saldo negativo (consumo maior que o registrado).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function belowMinimum(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->where('current_stock', '<', 0)
                ->orWhere(fn (Builder $query) => $query->where('minimum_stock', '>', 0)->whereColumn('current_stock', '<=', 'minimum_stock'));
        });
    }

    public function isBelowMinimum(): bool
    {
        return $this->current_stock < 0 || ($this->minimum_stock > 0 && $this->current_stock <= $this->minimum_stock);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
