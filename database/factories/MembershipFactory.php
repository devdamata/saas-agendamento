<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Como Membership usa BelongsToTenant, crie dentro de um tenant ativo:
 * app(TenantContext::class)->run($tenant, fn () => Membership::factory()->create()).
 *
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => MembershipRole::Client,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->getKey()]);
    }

    public function admin(): static
    {
        return $this->state(['role' => MembershipRole::Admin]);
    }

    public function professional(): static
    {
        return $this->state(['role' => MembershipRole::Professional]);
    }
}
