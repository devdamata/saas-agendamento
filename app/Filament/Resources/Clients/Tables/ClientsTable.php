<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Enums\AppointmentStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        $completed = fn (Builder $query) => $query->where('status', AppointmentStatus::Completed);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['appointments as visits_count' => $completed])
                ->withSum(['appointments as total_spent' => $completed], 'price')
                ->withMax(['appointments as last_visit_at' => $completed], 'starts_at'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Telefone')
                    ->searchable(),
                TextColumn::make('visits_count')
                    ->label('Atendimentos')
                    ->sortable(),
                TextColumn::make('last_visit_at')
                    ->label('Última visita')
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('total_spent')
                    ->label('Total gasto')
                    ->money('BRL', locale: 'pt_BR')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('whatsapp_opt_in')
                    ->label('WhatsApp')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Abrir'),
            ]);
    }
}
