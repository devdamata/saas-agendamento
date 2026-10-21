<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Salão de demonstração para desenvolvimento: http://demo.localhost:8000,
 * com um administrador (admin@demo.test / password).
 */
class DemoTenantSeeder extends Seeder
{
    public function run(TenantContext $tenantContext): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Salão Demo',
            'slug' => 'demo',
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin Demo',
            'phone' => '+5511999990000',
            'email' => 'admin@demo.test',
        ]);

        $tenantContext->run($tenant, fn () => Membership::factory()->for($tenant)->for($admin)->admin()->create());
    }
}
