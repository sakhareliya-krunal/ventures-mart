<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => env('LOCAL_ADMIN_EMAIL', 'sakhareliyakrunal33@gmail.com')],
            [
                'name' => 'Admin',
                'password' => env('LOCAL_ADMIN_PASSWORD', 'password'),
            ]
        );

        $user->forceFill(['is_admin' => true])->save();
    }
}
