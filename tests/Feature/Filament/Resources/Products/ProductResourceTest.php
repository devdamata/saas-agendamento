<?php

namespace Tests\Feature\Filament\Resources\Products;

use App\Enums\MembershipRole;
use App\Enums\ProductUnit;
use App\Enums\StockMovementType;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\Tenant;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastra_produto_com_estoque_inicial_no_livro(): void
    {
        $staff = $this->actingAsSalonMember(Tenant::factory()->create());

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Shampoo profissional',
                'unit' => ProductUnit::Milliliter->value,
                'minimum_stock' => 500,
                'cost' => '0.08',
                'initial_stock' => 5000,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'Shampoo profissional')->sole();
        $this->assertSame('5000.000', $product->current_stock);
        $movement = $product->stockMovements()->sole();
        $this->assertSame(StockMovementType::Purchase, $movement->type);
        $this->assertTrue($movement->user->is($staff));
    }

    public function test_movimentar_registra_entrada(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create());
        $product = Product::factory()->withStock(100)->create();

        Livewire::test(ListProducts::class)
            ->callAction(TestAction::make('moveStock')->table($product), [
                'type' => StockMovementType::Purchase->value,
                'quantity' => '250',
                'description' => 'Compra fornecedor',
            ])
            ->assertNotified('Estoque atualizado.');

        $this->assertSame('350.000', $product->refresh()->current_stock);
    }

    public function test_movimentar_com_quantidade_invalida_avisa_e_nao_altera(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create());
        $product = Product::factory()->withStock(100)->create();

        Livewire::test(ListProducts::class)
            ->callAction(TestAction::make('moveStock')->table($product), [
                'type' => StockMovementType::Purchase->value,
                'quantity' => '-10',
            ])
            ->assertNotified('A quantidade de uma entrada deve ser maior que zero.');

        $this->assertSame('100.000', $product->refresh()->current_stock);
    }

    public function test_alerta_no_menu_conta_produtos_que_precisam_de_reposicao(): void
    {
        $this->actingAsSalonMember(Tenant::factory()->create());
        Product::factory()->withStock(100)->create(['minimum_stock' => 200]);
        Product::factory()->withStock(200)->create(['minimum_stock' => 200]);
        Product::factory()->withStock(500)->create(['minimum_stock' => 200]);
        Product::factory()->withStock(10)->create(['minimum_stock' => 200, 'is_active' => false]);

        $this->assertSame('2', ProductResource::getNavigationBadge());
    }

    public function test_profissional_nao_acessa_o_estoque(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsSalonMember($tenant, MembershipRole::Professional);

        $this->get($this->salonUrl($tenant, '/painel/produtos'))->assertForbidden();
    }
}
