<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * No crea nada por defecto: el primer admin se crea en /instalar
     * o con "php artisan micv:crear-admin".
     * Para datos de ejemplo:  php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
