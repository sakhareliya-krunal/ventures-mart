<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'sakhareliyakrunal33@gmail.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
            ]
        );

        $user->forceFill(['is_admin' => true])->save();
    }
}
