<?php

namespace Tests\Feature\Filament\Resources\Professionals;

use App\Filament\Resources\Professionals\Pages\CreateProfessional;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateProfessionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastra_profissional_com_servicos_e_jornada(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant);
        $services = Service::factory()->count(2)->create();

        Livewire::test(CreateProfessional::class)
            ->fillForm([
                'name' => 'Carla Dias',
                'is_active' => true,
                'services' => $services->pluck('id')->all(),
                'workingHours' => [
                    ['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '12:00'],
                    ['weekday' => 2, 'starts_at' => '13:00', 'ends_at' => '18:00'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $professional = Professional::where('name', 'Carla Dias')->sole();
        $this->assertEqualsCanonicalizing($services->pluck('id')->all(), $professional->services->pluck('id')->all());
        $this->assertSame(
            [['weekday' => 2, 'starts_at' => '09:00:00', 'ends_at' => '12:00:00'], ['weekday' => 2, 'starts_at' => '13:00:00', 'ends_at' => '18:00:00']],
            $professional->workingHours()->orderBy('starts_at')->get(['weekday', 'starts_at', 'ends_at'])->toArray(),
        );
        $this->assertDatabaseHas('professional_service', ['tenant_id' => $tenant->id, 'professional_id' => $professional->id]);
    }

    public function test_recusa_intervalo_que_termina_antes_de_comecar(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create());

        Livewire::test(CreateProfessional::class)
            ->fillForm([
                'name' => 'Carla Dias',
                'workingHours' => [
                    ['weekday' => 2, 'starts_at' => '12:00', 'ends_at' => '09:00'],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, Professional::count());
    }
}
