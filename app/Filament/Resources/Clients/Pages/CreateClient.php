<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Actions\Clients\RegisterClient;
use App\Filament\Resources\Clients\ClientResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    /**
     * Pelo RegisterClient: se o telefone já é de um cliente de outro salão,
     * o cadastro é reaproveitado e só ganha o vínculo com este salão.
     *
     * @param  array{name: string, phone: string, email?: string|null, whatsapp_opt_in?: bool}  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(RegisterClient::class)->handle(
            $data['name'],
            $data['phone'],
            $data['email'] ?? null,
            (bool) ($data['whatsapp_opt_in'] ?? false),
        );
    }
}
