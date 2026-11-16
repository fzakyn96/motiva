<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_seeded_and_no_admin_account_exists()
    {
        $this->seed(AdminSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        // Hanya ada 1 akun superadmin
        $this->assertDatabaseHas('users', [
            'email' => 'superadmin@motiva.com',
            'name' => 'Superadmin',
        ]);

        $superadmin = User::where('email', 'superadmin@motiva.com')->first();
        $this->assertNotNull($superadmin);
        $this->assertTrue($superadmin->hasRole('Superadmin'));

        // Tidak boleh ada akun admin@motiva.com
        $this->assertDatabaseMissing('users', [
            'email' => 'admin@motiva.com',
        ]);

        $this->assertEquals(1, User::role('Superadmin')->count());
        $this->assertEquals(0, User::role('Admin')->count());
    }

    public function test_sample_employees_seeded_with_proper_hierarchy_and_jakarta_area()
    {
        $this->seed(AdminSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(EmployeeSeeder::class);

        // Verifikasi Division Head & Manager
        $divisionHead = Employee::whereHas('user', fn ($q) => $q->role('Division Head'))->first();
        $this->assertNotNull($divisionHead);

        $manager = Employee::where('identity_number', '125000001')->first();
        $this->assertNotNull($manager);
        $this->assertEquals('M Nuramzan Iftari', $manager->name);
        $this->assertEquals($divisionHead->id, $manager->manager_id);
        $this->assertTrue($manager->user->hasRole('Manager'));
        $this->assertEquals('Jakarta', $manager->department->name);

        // Verifikasi Supervisors
        $spvIkhsan = Employee::where('identity_number', '125000002')->first();
        $this->assertNotNull($spvIkhsan);
        $this->assertEquals('Ikhsan Aditya Wardana', $spvIkhsan->name);
        $this->assertEquals($manager->id, $spvIkhsan->manager_id);
        $this->assertTrue($spvIkhsan->user->hasRole('Supervisor'));

        $spvDidiet = Employee::where('identity_number', '125000003')->first();
        $this->assertNotNull($spvDidiet);
        $this->assertEquals('Didiet Pradityo', $spvDidiet->name);
        $this->assertEquals($manager->id, $spvDidiet->manager_id);
        $this->assertTrue($spvDidiet->user->hasRole('Supervisor'));

        // Verifikasi HR
        $hr = Employee::where('identity_number', '125000004')->first();
        $this->assertNotNull($hr);
        $this->assertTrue($hr->user->hasRole('HR'));

        // Verifikasi Driver (Triyono & Sutikno atasan langsungnya Manager karena supervisor kosong)
        $triyono = Employee::where('identity_number', '125770077')->first();
        $this->assertNotNull($triyono);
        $this->assertEquals('Triyono', $triyono->name);
        $this->assertEquals('Driver', $triyono->position->name);
        $this->assertEquals($manager->id, $triyono->manager_id);
        $this->assertTrue($triyono->user->hasRole('Staff'));

        $sutikno = Employee::where('identity_number', '126910050')->first();
        $this->assertNotNull($sutikno);
        $this->assertEquals($manager->id, $sutikno->manager_id);

        // Verifikasi Staf di bawah Didiet Pradityo (misal Chairunnisa & Fatikunada)
        $chairunnisa = Employee::where('identity_number', '125980056')->first();
        $this->assertNotNull($chairunnisa);
        $this->assertEquals('Chairunnisa', $chairunnisa->name);
        $this->assertEquals('Data Spatial Quality Assurance SSWJ AOSS - Regional 1', $chairunnisa->position->name);
        $this->assertEquals($spvDidiet->id, $chairunnisa->manager_id);

        $fatikunada = Employee::where('identity_number', '125970060')->first();
        $this->assertNotNull($fatikunada);
        $this->assertEquals($spvDidiet->id, $fatikunada->manager_id);

        // Verifikasi Staf di bawah Ikhsan Aditya Wardana (misal Panji & Novi)
        $panji = Employee::where('identity_number', '125900071')->first();
        $this->assertNotNull($panji);
        $this->assertEquals('Panji Abyul Novian', $panji->name);
        $this->assertEquals('Manajer Kontrak', $panji->position->name);
        $this->assertEquals($spvIkhsan->id, $panji->manager_id);

        $novi = Employee::where('identity_number', '125960067')->first();
        $this->assertNotNull($novi);
        $this->assertEquals('Novi Dwi Astuti', $novi->name);
        $this->assertEquals($spvIkhsan->id, $novi->manager_id);

        // Total data pegawai: 32 sampel pegawai + 1 Division Head + 1 Manager + 2 Supervisor + 1 HR = 37 pegawai
        $this->assertEquals(37, Employee::count());
    }
}
