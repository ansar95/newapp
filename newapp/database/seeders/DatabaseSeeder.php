<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleUserSeeder::class,
            ProjectManagementSeeder::class,
            AssetSeeder::class,
            BmhpSeeder::class,
            BillingSeeder::class,
            DocumentSeeder::class,
        ]);
    }
}
