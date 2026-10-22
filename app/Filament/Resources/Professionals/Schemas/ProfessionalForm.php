<?php

namespace App\Filament\Resources\Professionals\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProfessionalForm
{
    /**
     * Dias da semana na numeração do Carbon (0 = domingo).
     */
    public const WEEKDAYS = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        0 => 'Domingo',
    ];

    /**
     * A jornada é horário de relógio local do salão, não um instante: os
     * TimePickers usam o fuso da aplicação para o Filament não converter
     * do fuso do salão para UTC.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Ativo')
                            ->helperText('Profissionais inativos não recebem agendamentos.')
                            ->default(true)
                            ->inline(false),
                        Select::make('services')
                            ->label('Serviços que atende')
                            ->relationship('services', 'name')
                            ->multiple()
                            ->preload()
                            ->columnSpanFull(),
                    ]),
                Section::make('Jornada semanal')
                    ->description('Horários de trabalho em cada dia. Para pausas, como o almoço, cadastre dois intervalos no mesmo dia.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('workingHours')
                            ->hiddenLabel()
                            ->relationship()
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Adicionar intervalo')
                            ->schema([
                                Select::make('weekday')
                                    ->label('Dia')
                                    ->options(self::WEEKDAYS)
                                    ->required(),
                                TimePicker::make('starts_at')
                                    ->label('Início')
                                    ->seconds(false)
                                    ->timezone(config('app.timezone'))
                                    ->required(),
                                TimePicker::make('ends_at')
                                    ->label('Fim')
                                    ->seconds(false)
                                    ->timezone(config('app.timezone'))
                                    ->required()
                                    ->after('starts_at'),
                            ]),
                    ]),
            ]);
    }
}
