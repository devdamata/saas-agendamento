<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ficha técnica: quanto de cada produto um atendimento do serviço consome.
     */
    public function up(): void
    {
        Schema::create('service_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            $table->foreign(['tenant_id', 'service_id'])
                ->references(['tenant_id', 'id'])->on('services')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'product_id'])
                ->references(['tenant_id', 'id'])->on('products')
                ->cascadeOnDelete();
            $table->unique(['tenant_id', 'service_id', 'product_id']);
            $table->index(['tenant_id', 'product_id']);
        });

        DB::statement('ALTER TABLE service_products ADD CONSTRAINT service_products_quantity_check CHECK (quantity > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_products');
    }
};
