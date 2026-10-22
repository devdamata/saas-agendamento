<?php

namespace Tests;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Define o salão como atual e autentica um usuário com o papel informado
     * nele. Útil para testes do painel (Livewire), que não passam pelo
     * middleware de identificação do salão.
     */
    protected function actingAsSalonMember(Tenant $tenant, MembershipRole $role = MembershipRole::Admin): User
    {
        app(TenantContext::class)->set($tenant);

        $user = User::factory()->create();
        Membership::factory()->for($user)->create(['role' => $role]);

        $this->actingAs($user);

        return $user;
    }

    protected function salonUrl(Tenant $tenant, string $path = '/'): string
    {
        return "http://{$tenant->slug}.".config('tenancy.domain').$path;
    }
}
