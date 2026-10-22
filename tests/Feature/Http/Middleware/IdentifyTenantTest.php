<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\Membership;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IdentifyTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::domain('{tenant}.'.config('tenancy.domain'))
            ->middleware(['web', 'tenant'])
            ->group(function () {
                Route::get('/_teste/contexto', fn () => [
                    'tenant_id' => app(TenantContext::class)->id(),
                    'parametros' => request()->route()->parameters(),
                ]);

                Route::get('/_teste/memberships/{membership}', fn (Membership $membership) => [
                    'id' => $membership->id,
                ]);
            });
    }

    public function test_subdominio_de_salao_cadastrado_abre_a_rota_do_salao(): void
    {
        $tenant = Tenant::factory()->create();

        $this->get($this->urlDoSalao($tenant))->assertOk();
    }

    public function test_subdominio_define_o_tenant_atual_sem_repassar_o_parametro_para_a_rota(): void
    {
        $tenant = Tenant::factory()->create();

        $this->get($this->urlDoSalao($tenant, '/_teste/contexto'))
            ->assertOk()
            ->assertExactJson([
                'tenant_id' => $tenant->id,
                'parametros' => [],
            ]);
    }

    public function test_subdominio_sem_salao_cadastrado_retorna_404(): void
    {
        $this->get('http://inexistente.'.config('tenancy.domain').'/')->assertNotFound();

        $this->assertFalse(app(TenantContext::class)->check());
    }

    public function test_dominio_principal_nao_identifica_salao(): void
    {
        $this->get('http://'.config('tenancy.domain').'/')->assertOk();

        $this->assertFalse(app(TenantContext::class)->check());
    }

    public function test_model_da_url_e_buscado_dentro_do_salao_atual(): void
    {
        $tenant = Tenant::factory()->create();
        $membership = app(TenantContext::class)->run($tenant, fn () => Membership::factory()->create());

        $this->get($this->urlDoSalao($tenant, "/_teste/memberships/{$membership->id}"))
            ->assertOk()
            ->assertExactJson(['id' => $membership->id]);
    }

    public function test_model_da_url_de_outro_salao_retorna_404(): void
    {
        $salaoA = Tenant::factory()->create();
        $salaoB = Tenant::factory()->create();
        $doSalaoB = app(TenantContext::class)->run($salaoB, fn () => Membership::factory()->create());

        $this->get($this->urlDoSalao($salaoA, "/_teste/memberships/{$doSalaoB->id}"))->assertNotFound();
    }

    private function urlDoSalao(Tenant $tenant, string $path = '/'): string
    {
        return "http://{$tenant->slug}.".config('tenancy.domain').$path;
    }
}
