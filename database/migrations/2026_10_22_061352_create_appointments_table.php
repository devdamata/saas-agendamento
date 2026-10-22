<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('professional_id');
            $table->unsignedBigInteger('service_id');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->timestampTz('blocked_until');
            $table->string('status');
            $table->decimal('price', 10, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'professional_id'])
                ->references(['tenant_id', 'id'])->on('professionals')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'service_id'])
                ->references(['tenant_id', 'id'])->on('services')
                ->restrictOnDelete();

            // Alvo da FK composta de appointment_events; também indexa tenant_id.
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'starts_at']);
            $table->index('client_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE appointments ADD CONSTRAINT appointments_status_check
            CHECK (status IN ('agendado', 'confirmado', 'concluido', 'cancelado', 'faltou'))
        SQL);
        DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_range_check CHECK (ends_at > starts_at AND blocked_until >= ends_at)');

        // Garantia final contra conflito: o mesmo profissional não pode ter dois
        // agendamentos ativos com períodos ocupados (início até fim + buffer) sobrepostos.
        DB::statement(<<<'SQL'
            ALTER TABLE appointments ADD CONSTRAINT appointments_no_overlap
            EXCLUDE USING gist (professional_id WITH =, tstzrange(starts_at, blocked_until) WITH &&)
            WHERE (status IN ('agendado', 'confirmado', 'concluido'))
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
