<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'waseem.iqbal@avaib.com'],   // apni email likhein
            [
                'role' => \App\Enums\Role::Admin,
                'password'    => Hash::make('Password@123!'),  // apna password likhein
            ]
        );
    }
}
