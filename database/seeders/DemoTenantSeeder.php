<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Salão de demonstração para desenvolvimento: http://demo.localhost:8000,
 * com um administrador (admin@demo.test / password), dois profissionais e
 * jornada de terça a sábado.
 *
 * O DatabaseSeeder desliga os eventos dos models, então o tenant_id é
 * informado explicitamente com for($tenant).
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

        $tenantContext->run($tenant, function () use ($tenant, $admin) {
            Membership::factory()->for($tenant)->for($admin)->admin()->create();

            $services = collect([
                ['name' => 'Corte feminino', 'duration_minutes' => 60, 'buffer_minutes' => 15, 'price' => '90.00'],
                ['name' => 'Escova', 'duration_minutes' => 45, 'buffer_minutes' => 0, 'price' => '60.00'],
                ['name' => 'Manicure', 'duration_minutes' => 45, 'buffer_minutes' => 15, 'price' => '40.00'],
            ])->map(fn (array $attributes) => Service::factory()->for($tenant)->create($attributes));

            $professionals = [
                'Ana Souza' => ['Corte feminino', 'Escova'],
                'Bruna Lima' => ['Escova', 'Manicure'],
            ];

            foreach ($professionals as $name => $serviceNames) {
                $professional = Professional::factory()->for($tenant)->create(['name' => $name]);
                $professional->services()->attach($services->whereIn('name', $serviceNames)->pluck('id'));

                foreach (range(2, 6) as $weekday) {
                    WorkingHour::factory()->for($tenant)->for($professional)->create(['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '12:00']);
                    WorkingHour::factory()->for($tenant)->for($professional)->create(['weekday' => $weekday, 'starts_at' => '13:00', 'ends_at' => '19:00']);
                }
            }
        });
    }
}
