<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jornada semanal do profissional, no horário local do salão. Vários
     * intervalos no mesmo dia representam pausas (ex.: almoço).
     */
    public function up(): void
    {
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('professional_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->foreign(['tenant_id', 'professional_id'])
                ->references(['tenant_id', 'id'])->on('professionals')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'professional_id', 'weekday']);
        });

        // weekday segue o Carbon: 0 = domingo ... 6 = sábado.
        DB::statement('ALTER TABLE working_hours ADD CONSTRAINT working_hours_weekday_check CHECK (weekday BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE working_hours ADD CONSTRAINT working_hours_range_check CHECK (ends_at > starts_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('working_hours');
    }
};
