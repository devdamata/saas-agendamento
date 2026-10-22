<?php

namespace Tests\Feature\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\ManageServices;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastra_servico_ligado_aos_profissionais(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant);
        $professional = Professional::factory()->create();

        Livewire::test(ManageServices::class)
            ->callAction(CreateAction::class, [
                'name' => 'Escova',
                'price' => '60',
                'duration_minutes' => 45,
                'buffer_minutes' => 10,
                'professionals' => [$professional->id],
                'is_active' => true,
            ])
            ->assertHasNoFormErrors();

        $service = Service::where('name', 'Escova')->sole();
        $this->assertSame('60.00', $service->price);
        $this->assertSame(45, $service->duration_minutes);
        $this->assertSame(10, $service->buffer_minutes);
        $this->assertTrue($professional->offers($service));
    }

    public function test_servico_com_agendamentos_nao_pode_ser_apagado(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create());
        $withAppointments = Appointment::factory()->create()->service;
        $withoutAppointments = Service::factory()->create();

        Livewire::test(ManageServices::class)
            ->assertActionHidden(TestAction::make('delete')->table($withAppointments))
            ->assertActionVisible(TestAction::make('delete')->table($withoutAppointments));
    }
}
