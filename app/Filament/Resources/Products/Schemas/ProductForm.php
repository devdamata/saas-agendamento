<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductUnit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * O saldo não é editável aqui: ele só muda por movimentações. Na criação,
 * o estoque inicial vira uma entrada no livro (ver CreateProduct).
 */
class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Select::make('unit')
                    ->label('Unidade de medida')
                    ->options(ProductUnit::class)
                    ->default(ProductUnit::Milliliter)
                    ->required()
                    ->live(),
                TextInput::make('minimum_stock')
                    ->label('Estoque mínimo')
                    ->helperText('Abaixo disso o produto aparece no alerta de reposição.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->suffix(fn (Get $get): ?string => self::unitSuffix($get('unit'))),
                TextInput::make('cost')
                    ->label('Custo por unidade de medida')
                    ->helperText('Ex.: custo de 1 ml. Usado para calcular o custo dos atendimentos.')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('R$'),
                TextInput::make('initial_stock')
                    ->label('Estoque inicial')
                    ->numeric()
                    ->minValue(0)
                    ->suffix(fn (Get $get): ?string => self::unitSuffix($get('unit')))
                    ->visibleOn('create')
                    ->dehydrated(false),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    private static function unitSuffix(mixed $unit): ?string
    {
        return $unit instanceof ProductUnit ? $unit->value : ProductUnit::tryFrom((string) $unit)?->value;
    }
}
