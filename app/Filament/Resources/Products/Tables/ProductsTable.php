<?php

namespace App\Filament\Resources\Products\Tables;

use App\Actions\Stock\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('current_stock')
                    ->label('Em estoque')
                    ->numeric(maxDecimalPlaces: 3, locale: 'pt_BR')
                    ->suffix(fn (Product $record): string => " {$record->unit->value}")
                    ->color(fn (Product $record): ?string => $record->isBelowMinimum() ? 'danger' : null)
                    ->icon(fn (Product $record): ?Heroicon => $record->isBelowMinimum() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->sortable(),
                TextColumn::make('minimum_stock')
                    ->label('Mínimo')
                    ->numeric(maxDecimalPlaces: 3, locale: 'pt_BR')
                    ->suffix(fn (Product $record): string => " {$record->unit->value}"),
                TextColumn::make('cost')
                    ->label('Custo / unidade')
                    ->money('BRL', locale: 'pt_BR', decimalPlaces: 4),
                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                Filter::make('below_minimum')
                    ->label('Precisa de reposição')
                    ->query(fn (Builder $query): Builder => $query->belowMinimum()),
                TernaryFilter::make('is_active')
                    ->label('Ativo'),
            ])
            ->recordActions([
                Action::make('moveStock')
                    ->label('Movimentar')
                    ->icon(Heroicon::OutlinedArrowsUpDown)
                    ->authorize('update')
                    ->modalHeading(fn (Product $record): string => "Movimentar estoque: {$record->name}")
                    ->schema([
                        Select::make('type')
                            ->label('Tipo')
                            ->options([
                                StockMovementType::Purchase->value => 'Entrada (compra ou reposição)',
                                StockMovementType::Adjustment->value => 'Ajuste (contagem, perda, vencimento)',
                            ])
                            ->default(StockMovementType::Purchase->value)
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Quantidade')
                            ->helperText('Num ajuste, use um valor negativo para retirar do estoque.')
                            ->numeric()
                            ->required()
                            ->suffix(fn (Product $record): string => $record->unit->value),
                        TextInput::make('description')
                            ->label('Observação')
                            ->maxLength(255),
                    ])
                    ->action(function (Product $record, array $data): void {
                        try {
                            app(RecordStockMovement::class)->handle(
                                $record,
                                StockMovementType::from($data['type']),
                                $data['quantity'],
                                description: $data['description'] ?? null,
                                user: auth()->user(),
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Estoque atualizado.')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
