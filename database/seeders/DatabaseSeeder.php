<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'demo@venturesmart.example'],
            [
                'name' => 'Demo Shopper',
                'password' => 'password',
            ]
        );

        $this->call([
            AdminUserSeeder::class,
            CatalogSeeder::class,
            ProductContentEnrichmentSeeder::class,
            ProductReviewSeeder::class,
            BlogPostSeeder::class,
        ]);
    }
}
