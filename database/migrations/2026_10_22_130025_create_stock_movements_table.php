<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Livro de movimentação do estoque. Linhas são só inseridas; quantity tem
     * sinal (entrada > 0, consumo < 0, ajuste ≠ 0).
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('type');
            $table->decimal('quantity', 12, 3);
            $table->decimal('balance_after', 12, 3);
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');

            $table->foreign(['tenant_id', 'product_id'])
                ->references(['tenant_id', 'id'])->on('products')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'appointment_id'])
                ->references(['tenant_id', 'id'])->on('appointments')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'product_id', 'created_at']);
            $table->index(['tenant_id', 'appointment_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (
                (type = 'entrada' AND quantity > 0)
                OR (type = 'consumo' AND quantity < 0)
                OR (type = 'ajuste' AND quantity <> 0)
            )
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
