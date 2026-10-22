<?php

namespace Database\Seeders;

use App\Enums\CashCategory;
use App\Enums\PaymentMethod;
use App\Enums\ProductUnit;
use App\Models\CashEntry;
use App\Models\Membership;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceProduct;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Salão de demonstração para desenvolvimento: http://demo.localhost:8000/painel,
 * com um administrador (admin@demo.test / password), dois profissionais,
 * jornada de terça a sábado, produtos com ficha técnica e um produto já
 * abaixo do estoque mínimo.
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

            $products = collect([
                'Shampoo' => ['stock' => 5000, 'minimum_stock' => 1000, 'cost' => '0.04'],
                'Condicionador' => ['stock' => 300, 'minimum_stock' => 500, 'cost' => '0.05'],
                'Esmalte' => ['stock' => 200, 'minimum_stock' => 50, 'cost' => '0.60'],
                'Acetona' => ['stock' => 1000, 'minimum_stock' => 200, 'cost' => '0.02'],
            ])->map(fn (array $attributes, string $name) => Product::factory()->for($tenant)->withStock($attributes['stock'])->create([
                'name' => $name,
                'unit' => ProductUnit::Milliliter,
                'minimum_stock' => $attributes['minimum_stock'],
                'cost' => $attributes['cost'],
            ]));

            $technicalSheets = [
                'Corte feminino' => ['Shampoo' => 20, 'Condicionador' => 15],
                'Escova' => ['Shampoo' => 15],
                'Manicure' => ['Esmalte' => 2, 'Acetona' => 10],
            ];

            foreach ($technicalSheets as $serviceName => $items) {
                foreach ($items as $productName => $quantity) {
                    ServiceProduct::factory()->for($tenant)->create([
                        'service_id' => $services->firstWhere('name', $serviceName)->id,
                        'product_id' => $products[$productName]->id,
                        'quantity' => $quantity,
                    ]);
                }
            }

            CashEntry::factory()->for($tenant)->expense(CashCategory::Rent)->create([
                'amount' => '2500.00',
                'payment_method' => PaymentMethod::Pix,
                'description' => 'Aluguel do mês',
                'occurred_at' => now()->startOfMonth()->addDays(4),
            ]);
        });
    }
}
