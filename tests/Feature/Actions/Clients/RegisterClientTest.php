<?php

namespace Tests\Feature\Actions\Clients;

use App\Actions\Clients\RegisterClient;
use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->set(Tenant::factory()->create());
    }

    public function test_cadastra_cliente_novo_no_salao(): void
    {
        $this->freezeSecond();

        $client = app(RegisterClient::class)->handle('Maria Souza', '(11) 98765-4321', 'maria@example.com', whatsappOptIn: true);

        $this->assertSame('+5511987654321', $client->phone);
        $this->assertSame('maria@example.com', $client->email);
        $this->assertTrue($client->whatsapp_opt_in);
        $this->assertTrue($client->whatsapp_opt_in_at->equalTo(now()));
        $this->assertTrue($client->hasRoleInCurrentTenant(MembershipRole::Client));
    }

    public function test_reaproveita_cliente_de_outro_salao_sem_sobrescrever_dados(): void
    {
        $existing = User::factory()->create(['name' => 'Maria Souza', 'phone' => '+5511987654321', 'email' => 'maria@example.com']);

        $client = app(RegisterClient::class)->handle('Maria S.', '11987654321', 'outro@example.com');

        $this->assertTrue($client->is($existing));
        $this->assertSame('Maria Souza', $client->refresh()->name);
        $this->assertSame('maria@example.com', $client->email);
        $this->assertSame(1, User::count());
    }

    public function test_completa_email_que_faltava(): void
    {
        User::factory()->create(['phone' => '+5511987654321', 'email' => null]);

        $client = app(RegisterClient::class)->handle('Maria', '11987654321', 'maria@example.com');

        $this->assertSame('maria@example.com', $client->refresh()->email);
    }

    public function test_nao_duplica_vinculo_de_quem_ja_e_cliente(): void
    {
        $existing = User::factory()->create(['phone' => '+5511987654321']);
        Membership::factory()->for($existing)->create();

        app(RegisterClient::class)->handle('Maria', '11987654321');

        $this->assertSame(1, Membership::where('user_id', $existing->id)->count());
    }

    public function test_retirar_autorizacao_de_whatsapp_limpa_a_data(): void
    {
        $client = app(RegisterClient::class)->handle('Maria', '11987654321', whatsappOptIn: true);

        $client->update(['whatsapp_opt_in' => false]);

        $this->assertNull($client->refresh()->whatsapp_opt_in_at);
    }
}
