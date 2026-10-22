<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quais serviços cada profissional atende. As FKs compostas impedem
     * ligar profissional e serviço de salões diferentes.
     */
    public function up(): void
    {
        Schema::create('professional_service', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('professional_id');
            $table->unsignedBigInteger('service_id');

            $table->primary(['tenant_id', 'professional_id', 'service_id']);
            $table->foreign(['tenant_id', 'professional_id'])
                ->references(['tenant_id', 'id'])->on('professionals')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'service_id'])
                ->references(['tenant_id', 'id'])->on('services')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_service');
    }
};
