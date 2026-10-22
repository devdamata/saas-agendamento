<?php

namespace Tests\Feature\Filament;

use App\Enums\MembershipRole;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_e_levado_ao_login_do_proprio_salao(): void
    {
        $tenant = Tenant::factory()->create();

        $this->get($this->salonUrl($tenant, '/painel'))
            ->assertRedirect($this->salonUrl($tenant, '/painel/login'));
    }

    #[DataProvider('equipe')]
    public function test_equipe_do_salao_abre_o_painel_e_a_agenda(MembershipRole $role): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant, $role);

        $this->get($this->salonUrl($tenant, '/painel'))->assertOk();
        $this->get($this->salonUrl($tenant, '/painel/agenda'))->assertOk();
    }

    /**
     * @return array<string, array{MembershipRole}>
     */
    public static function equipe(): array
    {
        return [
            'admin' => [MembershipRole::Admin],
            'profissional' => [MembershipRole::Professional],
        ];
    }

    public function test_cliente_do_salao_nao_acessa_o_painel(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant, MembershipRole::Client);

        $this->get($this->salonUrl($tenant, '/painel'))->assertForbidden();
    }

    public function test_admin_de_outro_salao_nao_acessa_o_painel(): void
    {
        $salaoA = Tenant::factory()->create();
        $this->actingAsSalonMember(Tenant::factory()->create(), MembershipRole::Admin);

        $this->get($this->salonUrl($salaoA, '/painel'))->assertForbidden();
    }

    public function test_so_admin_acessa_os_cadastros(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant, MembershipRole::Professional);

        $this->get($this->salonUrl($tenant, '/painel/servicos'))->assertForbidden();
        $this->get($this->salonUrl($tenant, '/painel/profissionais'))->assertForbidden();
    }

    public function test_admin_acessa_os_cadastros(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant, MembershipRole::Admin);

        $this->get($this->salonUrl($tenant, '/painel/servicos'))->assertOk();
        $this->get($this->salonUrl($tenant, '/painel/profissionais'))->assertOk();
        $this->get($this->salonUrl($tenant, '/painel/profissionais/novo'))->assertOk();
    }

    public function test_formularios_abrem_com_os_dados_do_salao(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant, MembershipRole::Admin);
        $professional = Professional::factory()->create(['name' => 'Ana Souza']);
        WorkingHour::factory()->for($professional)->create(['starts_at' => '09:00', 'ends_at' => '12:00']);

        $this->get($this->salonUrl($tenant, "/painel/profissionais/{$professional->id}/editar"))
            ->assertOk()
            ->assertSee('Ana Souza')
            ->assertSee('09:00');
        $this->get($this->salonUrl($tenant, '/painel/agenda/novo'))->assertOk();
    }

    public function test_profissional_de_outro_salao_retorna_404_na_edicao(): void
    {
        $otherSalonProfessional = app(TenantContext::class)->run(
            Tenant::factory()->create(),
            fn () => Professional::factory()->create(),
        );
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant, MembershipRole::Admin);

        $this->get($this->salonUrl($tenant, "/painel/profissionais/{$otherSalonProfessional->id}/editar"))->assertNotFound();
    }
}
