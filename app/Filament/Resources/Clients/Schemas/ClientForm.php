<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * O telefone identifica o cliente (e será o login da área do cliente), por
 * isso só é informado no cadastro.
 */
class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Telefone (WhatsApp)')
                    ->tel()
                    ->required()
                    ->regex('/^[\d\s()+-]{10,20}$/')
                    ->disabledOn('edit')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('whatsapp_opt_in')
                    ->label('Autoriza mensagens no WhatsApp')
                    ->helperText('Confirmações e lembretes só são enviados com a autorização do cliente (LGPD).')
                    ->inline(false),
            ]);
    }
}
