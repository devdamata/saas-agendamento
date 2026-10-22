<?php

namespace Tests\Feature\Actions\Appointments;

use App\Actions\Appointments\CompleteAppointment;
use App\Enums\AppointmentStatus;
use App\Enums\CashCategory;
use App\Enums\CashEntryType;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Models\Appointment;
use App\Models\CashEntry;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProduct;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompleteAppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->set(Tenant::factory()->create());
    }

    public function test_conclui_dando_baixa_no_estoque_pela_ficha_tecnica(): void
    {
        $shampoo = Product::factory()->withStock(1000)->create(['name' => 'Shampoo']);
        $mascara = Product::factory()->withStock(500)->create(['name' => 'Máscara']);
        $service = Service::factory()->create(['name' => 'Hidratação']);
        ServiceProduct::factory()->for($service)->for($shampoo)->create(['quantity' => 30]);
        ServiceProduct::factory()->for($service)->for($mascara)->create(['quantity' => 12.5]);
        $appointment = Appointment::factory()->for($service)->create();
        $staff = User::factory()->create();

        app(CompleteAppointment::class)->handle($appointment, PaymentMethod::Pix, completedBy: $staff);

        $this->assertSame('970.000', $shampoo->refresh()->current_stock);
        $this->assertSame('487.500', $mascara->refresh()->current_stock);
        $this->assertSame(2, $appointment->stockMovements()->count());
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $mascara->id,
            'appointment_id' => $appointment->id,
            'type' => StockMovementType::Consumption->value,
            'quantity' => '-12.500',
            'user_id' => $staff->id,
        ]);
    }

    public function test_conclui_lancando_a_entrada_no_caixa(): void
    {
        $appointment = Appointment::factory()->create(['price' => '120.00']);
        $staff = User::factory()->create();

        app(CompleteAppointment::class)->handle($appointment, PaymentMethod::CreditCard, completedBy: $staff);

        $entry = $appointment->cashEntry()->sole();
        $this->assertSame(CashEntryType::Income, $entry->type);
        $this->assertSame(CashCategory::Service, $entry->category);
        $this->assertSame('120.00', $entry->amount);
        $this->assertSame(PaymentMethod::CreditCard, $entry->payment_method);
        $this->assertTrue($entry->user->is($staff));
    }

    public function test_valor_cobrado_diferente_atualiza_o_agendamento_e_o_caixa(): void
    {
        $appointment = Appointment::factory()->create(['price' => '120.00']);

        app(CompleteAppointment::class)->handle($appointment, PaymentMethod::Cash, amountCharged: '100.00');

        $this->assertSame('100.00', $appointment->refresh()->price);
        $this->assertSame('100.00', $appointment->cashEntry->amount);
    }

    public function test_cortesia_conclui_sem_lancamento_no_caixa(): void
    {
        $appointment = Appointment::factory()->create();

        app(CompleteAppointment::class)->handle($appointment, PaymentMethod::Other, amountCharged: 0);

        $this->assertSame(AppointmentStatus::Completed, $appointment->refresh()->status);
        $this->assertSame(0, CashEntry::count());
    }

    public function test_registra_a_conclusao_no_historico(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Confirmed)->create();
        $staff = User::factory()->create();

        app(CompleteAppointment::class)->handle($appointment, PaymentMethod::Pix, completedBy: $staff);

        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'from_status' => AppointmentStatus::Confirmed->value,
            'to_status' => AppointmentStatus::Completed->value,
            'user_id' => $staff->id,
        ]);
    }

    #[DataProvider('statusQueNaoPodemSerConcluidos')]
    public function test_nao_conclui_nem_movimenta_estoque_e_caixa(AppointmentStatus $status): void
    {
        $product = Product::factory()->withStock(100)->create();
        $service = Service::factory()->create();
        ServiceProduct::factory()->for($service)->for($product)->create(['quantity' => 10]);
        $appointment = Appointment::factory()->for($service)->status($status)->create();

        try {
            app(CompleteAppointment::class)->handle($appointment, PaymentMethod::Pix);
            $this->fail('O agendamento foi concluído.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame('100.000', $product->refresh()->current_stock);
        $this->assertSame(1, StockMovement::count(), 'Só o estoque inicial.');
        $this->assertSame(0, CashEntry::count());
    }

    /**
     * @return array<string, array{AppointmentStatus}>
     */
    public static function statusQueNaoPodemSerConcluidos(): array
    {
        return [
            'já concluído' => [AppointmentStatus::Completed],
            'cancelado' => [AppointmentStatus::Cancelled],
            'faltou' => [AppointmentStatus::NoShow],
        ];
    }

    public function test_falha_no_meio_desfaz_tudo(): void
    {
        $product = Product::factory()->withStock(100)->create();
        $service = Service::factory()->create();
        ServiceProduct::factory()->for($service)->for($product)->create(['quantity' => 10]);
        $appointment = Appointment::factory()->for($service)->create();
        CashEntry::factory()->create(['category' => CashCategory::Service, 'appointment_id' => $appointment->id]);

        try {
            app(CompleteAppointment::class)->handle($appointment, PaymentMethod::Pix);
            $this->fail('A conclusão deveria falhar na entrada duplicada do caixa.');
        } catch (QueryException) {
            // Esperado: índice único de uma entrada de caixa por agendamento.
        }

        $this->assertSame(AppointmentStatus::Scheduled, $appointment->refresh()->status);
        $this->assertSame('100.000', $product->refresh()->current_stock);
        $this->assertSame(0, $appointment->events()->count());
    }
}
