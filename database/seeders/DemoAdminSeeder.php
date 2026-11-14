<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => config('demo.admin.email')],
            [
                'name' => 'Demo Admin SirkulaFood',
                'role' => 'SirkulaFood_admin',
                'password' => Hash::make((string) config('demo.admin.password')),
                'email_verified_at' => now(),
            ]
        );
    }
}
