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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 63)->unique();
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->string('plan')->default('free');
            $table->timestamps();
        });

        // O slug vira subdomínio: só letras minúsculas, números e hífen,
        // e nunca um subdomínio reservado.
        DB::statement(<<<'SQL'
            ALTER TABLE tenants ADD CONSTRAINT tenants_slug_format_check
            CHECK (slug ~ '^[a-z0-9]([a-z0-9-]*[a-z0-9])?$' AND slug NOT IN ('app', 'admin', 'www', 'api'))
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
