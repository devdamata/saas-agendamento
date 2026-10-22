<?php

namespace Tests\Feature\Filament\Resources\Appointments;

use App\Actions\Appointments\ListAvailableSlots;
use App\Enums\MembershipRole;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Models\Appointment;
use App\Models\Membership;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateAppointmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Terça-feira; o "agora" dos testes é a segunda anterior.
     */
    private const DAY = '2026-11-03';

    private const TIMEZONE = 'America/Sao_Paulo';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', self::TIMEZONE));
    }

    public function test_agenda_cliente_no_horario_escolhido(): void
    {
        $staff = $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        [$professional, $service] = $this->professionalWithService();
        $client = $this->salonClient();

        Livewire::test(CreateAppointment::class)
            ->fillForm($this->formData($client, $professional, $service, '10:00'))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $appointment = Appointment::sole();
        $this->assertTrue($appointment->starts_at->equalTo($this->at('10:00')));
        $this->assertTrue($appointment->client->is($client));
        $this->assertTrue($appointment->creator->is($staff));
    }

    public function test_horario_ocupado_desde_a_escolha_mostra_erro_no_campo(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        [$professional, $service] = $this->professionalWithService();
        Appointment::factory()->for($professional)->at($this->at('10:00'))->create();

        Livewire::test(CreateAppointment::class)
            ->fillForm($this->formData($this->salonClient(), $professional, $service, '10:00'))
            ->call('create')
            ->assertHasFormErrors(['starts_at'])
            ->assertSee(AppointmentForm::UNAVAILABLE_MESSAGE);

        $this->assertSame(1, Appointment::count());
    }

    public function test_conflito_barrado_pelo_banco_mostra_erro_no_campo(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        [$professional, $service] = $this->professionalWithService();
        Appointment::factory()->for($professional)->at($this->at('10:00'))->create();
        $this->mock(ListAvailableSlots::class, function (MockInterface $mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('handle')->andReturn([$this->at('10:00')]);
        });

        Livewire::test(CreateAppointment::class)
            ->fillForm($this->formData($this->salonClient(), $professional, $service, '10:00'))
            ->call('create')
            ->assertHasFormErrors(['starts_at'])
            ->assertSee(AppointmentForm::UNAVAILABLE_MESSAGE);

        $this->assertSame(1, Appointment::count());
    }

    public function test_recusa_cliente_que_nao_e_do_salao(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create(['timezone' => self::TIMEZONE]));
        [$professional, $service] = $this->professionalWithService();
        $outsider = User::factory()->create();

        Livewire::test(CreateAppointment::class)
            ->fillForm($this->formData($outsider, $professional, $service, '10:00'))
            ->call('create')
            ->assertHasFormErrors(['client_id']);

        $this->assertSame(0, Appointment::count());
    }

    public function test_cadastra_cliente_novo_pelo_formulario(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create());

        Livewire::test(CreateAppointment::class)
            ->callAction(TestAction::make('createOption')->schemaComponent('client_id'), [
                'name' => 'Maria Souza',
                'phone' => '(11) 98765-4321',
            ])
            ->assertHasNoFormErrors();

        $client = User::where('phone', '+5511987654321')->sole();
        $this->assertSame('Maria Souza', $client->name);
        $this->assertTrue($client->hasRoleInCurrentTenant(MembershipRole::Client));
    }

    public function test_cliente_de_outro_salao_e_reaproveitado_pelo_telefone(): void
    {
        $existing = User::factory()->create(['name' => 'Maria Souza', 'phone' => '+5511987654321']);
        $this->actingAsSalonMember(Tenant::factory()->create());

        Livewire::test(CreateAppointment::class)
            ->callAction(TestAction::make('createOption')->schemaComponent('client_id'), [
                'name' => 'Maria S.',
                'phone' => '11987654321',
            ]);

        $this->assertSame(1, User::where('phone', '+5511987654321')->count());
        $this->assertSame('Maria Souza', $existing->refresh()->name);
        $this->assertTrue($existing->hasRoleInCurrentTenant(MembershipRole::Client));
    }

    /**
     * @return array{Professional, Service}
     */
    private function professionalWithService(): array
    {
        $professional = Professional::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $professional->services()->attach($service);
        WorkingHour::factory()->for($professional)->create([
            'weekday' => CarbonImmutable::parse(self::DAY)->dayOfWeek,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ]);

        return [$professional, $service];
    }

    private function salonClient(): User
    {
        $client = User::factory()->create();
        Membership::factory()->for($client)->create(['role' => MembershipRole::Client]);

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $client, Professional $professional, Service $service, string $time): array
    {
        return [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'date' => self::DAY,
            'starts_at' => $this->at($time)->utc()->toIso8601String(),
        ];
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(self::DAY.' '.$time, self::TIMEZONE);
    }
}
