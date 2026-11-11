<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus akun admin lama dengan aman (lepaskan relasi employee terlebih dahulu)
        $oldAdminUser = \App\Models\User::where('email', 'admin@motiva.com')->first();
        if ($oldAdminUser) {
            $adminEmployees = \App\Models\Employee::where('user_id', $oldAdminUser->id)->get();
            foreach ($adminEmployees as $emp) {
                \App\Models\Employee::where('manager_id', $emp->id)->update(['manager_id' => null]);
                try {
                    $emp->delete();
                } catch (\Throwable $e) {
                    $emp->update(['user_id' => null]);
                }
            }
            $oldAdminUser->delete();
        }

        // Buat role Superadmin jika belum ada
        $roleSuperadmin = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Superadmin',
            'guard_name' => 'web',
        ]);

        // Buat akun Superadmin (hanya 1)
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'superadmin@motiva.com'],
            [
                'name' => 'Superadmin',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles([$roleSuperadmin]);
    }
}
