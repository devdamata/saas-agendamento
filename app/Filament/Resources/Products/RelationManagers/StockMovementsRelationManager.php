<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\StockMovementType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Livro de movimentações do produto, só leitura.
 */
class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    protected static ?string $title = 'Movimentações';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->numeric(maxDecimalPlaces: 3, locale: 'pt_BR'),
                TextColumn::make('balance_after')
                    ->label('Saldo')
                    ->numeric(maxDecimalPlaces: 3, locale: 'pt_BR'),
                TextColumn::make('description')
                    ->label('Observação'),
                TextColumn::make('user.name')
                    ->label('Por'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(StockMovementType::class),
            ]);
    }
}
