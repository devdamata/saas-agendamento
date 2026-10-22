<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * current_stock é cache do livro stock_movements: só é alterado junto
     * com um movimento, na mesma transação (RecordStockMovement).
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit');
            $table->decimal('current_stock', 12, 3)->default(0);
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->decimal('cost', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Alvo das FKs compostas (tenant_id, product_id); também indexa tenant_id.
            $table->unique(['tenant_id', 'id']);
        });

        DB::statement("ALTER TABLE products ADD CONSTRAINT products_unit_check CHECK (unit IN ('un', 'ml', 'g'))");
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_minimum_stock_check CHECK (minimum_stock >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_cost_check CHECK (cost IS NULL OR cost >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
