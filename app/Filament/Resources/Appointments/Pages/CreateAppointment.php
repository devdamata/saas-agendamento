<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Actions\Appointments\BookAppointment;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    /**
     * @param  array{client_id: int|string, service_id: int|string, professional_id: int|string, starts_at: string}  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(BookAppointment::class)->handle(
                Professional::findOrFail($data['professional_id']),
                Service::findOrFail($data['service_id']),
                User::findOrFail($data['client_id']),
                CarbonImmutable::parse($data['starts_at']),
                auth()->user(),
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'data.starts_at' => $exception->errors()['starts_at'] ?? $exception->getMessage(),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
