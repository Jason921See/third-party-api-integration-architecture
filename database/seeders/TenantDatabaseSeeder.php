<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        // Artisan::call('passport:client', [
        //     '--personal'       => true,
        //     '--name'           => 'Personal Access Client',
        //     '--no-interaction' => true,
        // ]);

        $this->call([
            IntegrationProviderSeeder::class,
        ]);
    }
}
