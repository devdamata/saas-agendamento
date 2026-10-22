<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Livro do caixa: tudo que entra e tudo que sai. Linhas são só inseridas;
     * amount é sempre positivo e o sentido vem de type.
     */
    public function up(): void
    {
        Schema::create('cash_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('category');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');

            $table->foreign(['tenant_id', 'appointment_id'])
                ->references(['tenant_id', 'id'])->on('appointments')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'occurred_at']);
        });

        DB::statement("ALTER TABLE cash_entries ADD CONSTRAINT cash_entries_type_check CHECK (type IN ('entrada', 'saida'))");
        DB::statement('ALTER TABLE cash_entries ADD CONSTRAINT cash_entries_amount_check CHECK (amount > 0)');

        // Um atendimento concluído gera uma única entrada no caixa.
        DB::statement('CREATE UNIQUE INDEX cash_entries_appointment_unique ON cash_entries (tenant_id, appointment_id) WHERE appointment_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_entries');
    }
};
