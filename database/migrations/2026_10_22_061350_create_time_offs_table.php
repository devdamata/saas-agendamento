<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Folgas e bloqueios pontuais na agenda do profissional.
     */
    public function up(): void
    {
        Schema::create('time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('professional_id');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'professional_id'])
                ->references(['tenant_id', 'id'])->on('professionals')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'professional_id', 'starts_at']);
        });

        DB::statement('ALTER TABLE time_offs ADD CONSTRAINT time_offs_range_check CHECK (ends_at > starts_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_offs');
    }
};
