<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@padeliotunyrai.lt',
            'password' => bcrypt('Admin123!'),
        ]);
    }
}
