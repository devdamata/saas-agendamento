<?php

namespace Tests\Feature\Tenancy;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    private Tenant $salaoA;

    private Tenant $salaoB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);
        $this->salaoA = Tenant::factory()->create();
        $this->salaoB = Tenant::factory()->create();
    }

    public function test_consultas_retornam_apenas_registros_do_tenant_atual(): void
    {
        $this->context->run($this->salaoA, fn () => Membership::factory()->count(2)->create());
        $this->context->run($this->salaoB, fn () => Membership::factory()->count(3)->create());

        $this->context->set($this->salaoA);
        $this->assertSame(2, Membership::count());
        $this->assertTrue(Membership::all()->every(fn ($m) => $m->tenant_id === $this->salaoA->id));

        $this->context->set($this->salaoB);
        $this->assertSame(3, Membership::count());
    }

    public function test_nao_encontra_registro_de_outro_tenant_pelo_id(): void
    {
        $doSalaoB = $this->context->run($this->salaoB, fn () => Membership::factory()->create());

        $this->context->set($this->salaoA);

        $this->assertNull(Membership::find($doSalaoB->id));
    }

    public function test_relacao_do_usuario_respeita_o_tenant_atual(): void
    {
        $user = User::factory()->create();
        $this->context->run($this->salaoA, fn () => Membership::factory()->for($user)->create());
        $this->context->run($this->salaoB, fn () => Membership::factory()->for($user)->admin()->create());

        $this->context->set($this->salaoA);

        $this->assertCount(1, $user->memberships);
        $this->assertSame(MembershipRole::Client, $user->memberships->first()->role);
        $this->assertCount(2, $user->tenants, 'tenants() lista todos os salões do usuário');
    }

    public function test_consulta_sem_tenant_definido_falha(): void
    {
        $this->expectException(TenantNotResolvedException::class);

        Membership::count();
    }

    public function test_without_tenancy_enxerga_todos_os_tenants(): void
    {
        $this->context->run($this->salaoA, fn () => Membership::factory()->create());
        $this->context->run($this->salaoB, fn () => Membership::factory()->create());

        $this->assertSame(2, Membership::withoutTenancy()->count());
    }

    public function test_tenant_id_e_preenchido_automaticamente_ao_criar(): void
    {
        $this->context->set($this->salaoA);

        $membership = Membership::create([
            'user_id' => User::factory()->create()->id,
            'role' => MembershipRole::Professional,
        ]);

        $this->assertSame($this->salaoA->id, $membership->tenant_id);
    }

    public function test_tenant_id_nao_pode_ser_atribuido_em_massa(): void
    {
        $this->context->set($this->salaoA);

        $membership = Membership::create([
            'tenant_id' => $this->salaoB->id,
            'user_id' => User::factory()->create()->id,
            'role' => MembershipRole::Client,
        ]);

        $this->assertSame($this->salaoA->id, $membership->tenant_id);
    }

    public function test_nao_permite_criar_registro_em_outro_tenant(): void
    {
        $this->context->set($this->salaoA);

        $this->expectException(LogicException::class);

        Membership::factory()->forTenant($this->salaoB)->create();
    }

    public function test_nao_permite_criar_registro_sem_tenant_definido(): void
    {
        $this->expectException(TenantNotResolvedException::class);

        Membership::factory()->forTenant($this->salaoA)->create();
    }

    public function test_nao_permite_mover_registro_para_outro_tenant(): void
    {
        $this->context->set($this->salaoA);
        $membership = Membership::factory()->create();

        $this->expectException(LogicException::class);

        $membership->forceFill(['tenant_id' => $this->salaoB->id])->save();
    }

    public function test_run_restaura_o_tenant_anterior(): void
    {
        $this->context->set($this->salaoA);

        $this->context->run($this->salaoB, fn () => $this->assertSame($this->salaoB->id, $this->context->id()));

        $this->assertSame($this->salaoA->id, $this->context->id());
    }

    public function test_mesmo_usuario_pode_ter_papeis_em_varios_tenants(): void
    {
        $user = User::factory()->create();

        $this->context->run($this->salaoA, fn () => Membership::factory()->for($user)->create());
        $this->context->run($this->salaoB, fn () => Membership::factory()->for($user)->create());
        $this->context->run($this->salaoB, fn () => Membership::factory()->for($user)->professional()->create());

        $this->assertSame(3, Membership::withoutTenancy()->where('user_id', $user->id)->count());
    }

    public function test_papel_duplicado_no_mesmo_tenant_e_bloqueado_no_banco(): void
    {
        $user = User::factory()->create();
        $this->context->set($this->salaoA);
        Membership::factory()->for($user)->create();

        $this->expectException(QueryException::class);

        Membership::factory()->for($user)->create();
    }

    #[DataProvider('slugsInvalidos')]
    public function test_slug_invalido_ou_reservado_e_bloqueado_no_banco(string $slug): void
    {
        $this->expectException(QueryException::class);

        Tenant::factory()->create(['slug' => $slug]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function slugsInvalidos(): array
    {
        return [
            'reservado app' => ['app'],
            'reservado admin' => ['admin'],
            'reservado www' => ['www'],
            'reservado api' => ['api'],
            'maiúsculas' => ['Salao'],
            'com ponto' => ['salao.bela'],
            'hífen no início' => ['-salao'],
        ];
    }
}
