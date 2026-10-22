<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Sem WithoutModelEvents: os eventos dos models preenchem o tenant_id e
     * protegem os livros de estoque e caixa.
     */
    public function run(): void
    {
        $this->call(DemoTenantSeeder::class);
    }
}
