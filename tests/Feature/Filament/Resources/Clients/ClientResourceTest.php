<?php

namespace Tests\Feature\Filament\Resources\Clients;

use App\Enums\AppointmentStatus;
use App\Enums\MembershipRole;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\RelationManagers\AppointmentsRelationManager;
use App\Models\Appointment;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_so_os_clientes_do_salao(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = $this->actingAsSalonMember($tenant);
        $client = $this->salonClient();
        $otherSalonClient = app(TenantContext::class)->run(Tenant::factory()->create(), fn () => $this->salonClient());
        app(TenantContext::class)->set($tenant);

        Livewire::test(ListClients::class)
            ->assertCanSeeTableRecords([$client])
            ->assertCanNotSeeTableRecords([$otherSalonClient, $staff]);
    }

    public function test_mostra_atendimentos_concluidos_ultima_visita_e_total_gasto(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create());
        $client = $this->salonClient();
        Appointment::factory()->for($client, 'client')->status(AppointmentStatus::Completed)
            ->at(CarbonImmutable::parse('2026-09-10 10:00'))->create(['price' => '100.00']);
        Appointment::factory()->for($client, 'client')->status(AppointmentStatus::Completed)
            ->at(CarbonImmutable::parse('2026-10-05 10:00'))->create(['price' => '80.00']);
        Appointment::factory()->for($client, 'client')->status(AppointmentStatus::Cancelled)
            ->at(CarbonImmutable::parse('2026-10-20 10:00'))->create(['price' => '500.00']);

        Livewire::test(ListClients::class)
            ->assertTableColumnStateSet('visits_count', 2, $client)
            ->assertTableColumnStateSet('total_spent', '180.00', $client)
            ->assertTableColumnFormattedStateSet('last_visit_at', '05/10/2026', $client);
    }

    public function test_historico_mostra_os_atendimentos_do_cliente_no_salao(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant);
        $client = $this->salonClient();
        $own = Appointment::factory()->for($client, 'client')->create();
        $otherSalon = app(TenantContext::class)->run(Tenant::factory()->create(), fn () => Appointment::factory()->for($client, 'client')->create());
        app(TenantContext::class)->set($tenant);

        Livewire::test(AppointmentsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$otherSalon]);
    }

    public function test_cadastra_cliente_reaproveitando_telefone_de_outro_salao(): void
    {
        $existing = User::factory()->create(['name' => 'Maria Souza', 'phone' => '+5511987654321']);
        $this->actingAsSalonMember(Tenant::factory()->create());

        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Maria S.',
                'phone' => '(11) 98765-4321',
                'whatsapp_opt_in' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, User::where('phone', '+5511987654321')->count());
        $this->assertTrue($existing->refresh()->hasRoleInCurrentTenant(MembershipRole::Client));
        $this->assertTrue($existing->whatsapp_opt_in);
        $this->assertNotNull($existing->whatsapp_opt_in_at);
    }

    public function test_edicao_nao_altera_o_telefone(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create());
        $client = $this->salonClient(['phone' => '+5511987654321']);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['name' => 'Novo Nome', 'phone' => '+5511000000000'])
            ->call('save')
            ->assertHasNoFormErrors();

        $client->refresh();
        $this->assertSame('Novo Nome', $client->name);
        $this->assertSame('+5511987654321', $client->phone);
    }

    public function test_cliente_de_outro_salao_retorna_404(): void
    {
        $otherSalonClient = app(TenantContext::class)->run(Tenant::factory()->create(), fn () => $this->salonClient());
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant);

        $this->get($this->salonUrl($tenant, "/painel/clientes/{$otherSalonClient->id}/editar"))->assertNotFound();
    }

    public function test_profissional_acessa_os_clientes(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant, MembershipRole::Professional);

        $this->get($this->salonUrl($tenant, '/painel/clientes'))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function salonClient(array $attributes = []): User
    {
        $client = User::factory()->create($attributes);
        Membership::factory()->for($client)->create(['role' => MembershipRole::Client]);

        return $client;
    }
}
