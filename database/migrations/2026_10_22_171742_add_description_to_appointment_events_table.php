<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detalhe do evento, como o horário anterior numa remarcação.
     */
    public function up(): void
    {
        Schema::table('appointment_events', function (Blueprint $table) {
            $table->string('description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointment_events', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
