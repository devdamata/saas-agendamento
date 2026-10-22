<?php

namespace Tests\Feature\Actions\Stock;

use App\Actions\Stock\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecordStockMovementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->set(Tenant::factory()->create());
    }

    public function test_entrada_soma_ao_saldo_e_registra_o_lancamento(): void
    {
        $product = Product::factory()->withStock('100.5')->create();
        $user = User::factory()->create();

        $movement = app(RecordStockMovement::class)->handle($product, StockMovementType::Purchase, '250', description: 'Compra NF 123', user: $user);

        $this->assertSame('350.500', $product->refresh()->current_stock);
        $this->assertSame('250.000', $movement->quantity);
        $this->assertSame('350.500', $movement->balance_after);
        $this->assertSame('Compra NF 123', $movement->description);
        $this->assertTrue($movement->user->is($user));
    }

    public function test_ajuste_negativo_reduz_o_saldo(): void
    {
        $product = Product::factory()->withStock(100)->create();

        app(RecordStockMovement::class)->handle($product, StockMovementType::Adjustment, -30, description: 'Contagem de inventário');

        $this->assertSame('70.000', $product->refresh()->current_stock);
    }

    public function test_saldo_pode_ficar_negativo(): void
    {
        $product = Product::factory()->withStock(5)->create();

        app(RecordStockMovement::class)->handle($product, StockMovementType::Consumption, -8);

        $this->assertSame('-3.000', $product->refresh()->current_stock);
        $this->assertTrue($product->isBelowMinimum());
    }

    public function test_saldo_e_a_soma_do_livro(): void
    {
        $product = Product::factory()->withStock(100)->create();
        app(RecordStockMovement::class)->handle($product, StockMovementType::Consumption, -12.5);
        app(RecordStockMovement::class)->handle($product, StockMovementType::Purchase, 40);
        app(RecordStockMovement::class)->handle($product, StockMovementType::Adjustment, -2);

        $this->assertEquals(
            (float) $product->stockMovements()->sum('quantity'),
            (float) $product->refresh()->current_stock,
        );
    }

    #[DataProvider('quantidadesInvalidas')]
    public function test_recusa_quantidade_que_nao_combina_com_o_tipo(StockMovementType $type, int $quantity, string $message): void
    {
        $product = Product::factory()->withStock(10)->create();

        try {
            app(RecordStockMovement::class)->handle($product, $type, $quantity);
            $this->fail('O movimento foi aceito.');
        } catch (ValidationException $exception) {
            $this->assertSame(['quantity' => [$message]], $exception->errors());
        }

        $this->assertSame('10.000', $product->refresh()->current_stock);
    }

    /**
     * @return array<string, array{StockMovementType, int, string}>
     */
    public static function quantidadesInvalidas(): array
    {
        return [
            'entrada negativa' => [StockMovementType::Purchase, -5, 'A quantidade de uma entrada deve ser maior que zero.'],
            'consumo positivo' => [StockMovementType::Consumption, 5, 'A quantidade de um consumo deve ser negativa.'],
            'ajuste zero' => [StockMovementType::Adjustment, 0, 'A quantidade de um ajuste não pode ser zero.'],
        ];
    }

    public function test_lancamento_nao_pode_ser_alterado(): void
    {
        $product = Product::factory()->withStock(10)->create();
        $movement = $product->stockMovements()->sole();

        $this->expectException(LogicException::class);

        $movement->forceFill(['quantity' => 999])->save();
    }

    public function test_lancamento_nao_pode_ser_apagado(): void
    {
        $product = Product::factory()->withStock(10)->create();

        $this->expectException(LogicException::class);

        $product->stockMovements()->sole()->delete();
    }

    public function test_saldo_nao_e_preenchivel_por_atribuicao_em_massa(): void
    {
        $product = Product::factory()->create();

        $product->update(['current_stock' => 500]);

        $this->assertSame('0.000', $product->refresh()->current_stock);
        $this->assertSame(0, StockMovement::count());
    }
}
