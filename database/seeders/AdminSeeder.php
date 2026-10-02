<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'admin@motiva.com'],
            [
                'name' => 'Super Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
            ]
        );

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin']);
        $user->assignRole($role);
    }
}
