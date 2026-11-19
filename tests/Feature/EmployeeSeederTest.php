<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkArea;
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

        // Verifikasi Division Head PGN & Koordinator/Manager PGN
        $divisionHead = Employee::where('identity_number', 'PGN-001')->first();
        $this->assertNotNull($divisionHead);
        $this->assertEquals('Herry Rachmadi', $divisionHead->name);
        $this->assertTrue($divisionHead->user->hasRole('Division Head'));

        $manager = Employee::where('identity_number', 'PGN-002')->first();
        $this->assertNotNull($manager);
        $this->assertEquals('M Nuramzan Iftari', $manager->name);
        $this->assertEquals($divisionHead->id, $manager->manager_id);
        $this->assertTrue($manager->user->hasRole('Manager'));
        $this->assertEquals('Management Enterprise System Infrastructure Information (MESI)', $manager->department->name);
        $this->assertEquals('MESI', $manager->department->code);
        $this->assertEquals('PT. Perusahaan Gas Negara (Persero) Tbk.', $manager->department->organization->name);
        $this->assertSame(1, Department::count());
        $this->assertSame(80, Employee::where('department_id', $manager->department_id)->count());

        // Verifikasi Business Division Head Perkasa
        $khairul = Employee::where('identity_number', '125000005')->first();
        $this->assertNotNull($khairul);
        $this->assertEquals('Khairul Khitam', $khairul->name);
        $this->assertEquals('Business Division Head', $khairul->position->name);
        $this->assertEquals('PT Permata Karya Jasa', $khairul->organization->name);
        $this->assertTrue($khairul->user->hasRole('Manager'));

        // Verifikasi Koordinator Area Kerja Bojonegara & Medan
        $mahar = Employee::where('identity_number', 'NO-20001')->first();
        $this->assertNotNull($mahar);
        $this->assertEquals('Mahar Prasetyo', $mahar->name);
        $this->assertTrue($mahar->user->hasRole('Manager'));

        $kartini = Employee::where('identity_number', 'PGN-005')->first();
        $this->assertNotNull($kartini);
        $this->assertEquals('Kartini Tetty Erawati Pandiangan', $kartini->name);
        $this->assertEquals($divisionHead->id, $kartini->manager_id);
        $this->assertNotNull($kartini->user);
        $this->assertEquals('kartini.pandiangan', $kartini->user->username);
        $this->assertEquals('kartini.pandiangan@motiva.com', $kartini->user->email);
        $this->assertTrue($kartini->user->hasRole('Manager'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $kartini->user->password));

        // Verifikasi hanya 2 Organisasi (PGN & PT Permata Karya Jasa / Perkasa)
        $this->assertSame(2, Organization::count());
        $this->assertDatabaseMissing('organizations', ['code' => 'MOTIVA']);

        // Verifikasi 5 Master Area Kerja di bawah MESI beserta Koordinatornya
        $this->assertSame(5, WorkArea::where('department_id', $manager->department_id)->count());
        $expectedAreaCoordinators = [
            'JKT' => $manager->id,
            'BJN' => $mahar->id,
            'BTG' => $manager->id,
            'MDN' => $kartini->id,
            'SRB' => $manager->id,
        ];
        foreach ($expectedAreaCoordinators as $areaCode => $expectedCoordinatorId) {
            $area = WorkArea::where('code', $areaCode)->first();
            $this->assertNotNull($area);
            $this->assertEquals($expectedCoordinatorId, $area->coordinator_employee_id);
        }

        // Verifikasi Supervisors PGN
        $spvIkhsan = Employee::where('identity_number', 'PGN-003')->first();
        $this->assertNotNull($spvIkhsan);
        $this->assertEquals('Ikhsan Aditya Wardana', $spvIkhsan->name);
        $this->assertEquals($manager->id, $spvIkhsan->manager_id);
        $this->assertTrue($spvIkhsan->user->hasRole('Supervisor'));

        $spvDidiet = Employee::where('identity_number', 'PGN-004')->first();
        $this->assertNotNull($spvDidiet);
        $this->assertEquals('Didiet Pradityo', $spvDidiet->name);
        $this->assertEquals($manager->id, $spvDidiet->manager_id);
        $this->assertTrue($spvDidiet->user->hasRole('Supervisor'));

        // Verifikasi HR (Maria Ulfah)
        $maria = Employee::where('identity_number', '125970065')->first();
        $this->assertNotNull($maria);
        $this->assertEquals('Maria Ulfah', $maria->name);
        $this->assertTrue($maria->user->hasRole('HR'));

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

        // Verifikasi Staf Jakarta di bawah Didiet Pradityo & Ikhsan Aditya Wardana
        $chairunnisa = Employee::where('identity_number', '125980056')->first();
        $this->assertNotNull($chairunnisa);
        $this->assertEquals('Chairunnisa', $chairunnisa->name);
        $this->assertEquals('Data Spatial Quality Assurance SSWJ AOSS - Regional 1', $chairunnisa->position->name);
        $this->assertEquals('PT Permata Karya Jasa', $chairunnisa->organization->name);
        $this->assertEquals('Jakarta', $chairunnisa->workArea->name);
        $this->assertEquals($spvDidiet->id, $chairunnisa->manager_id);

        $panji = Employee::where('identity_number', '125900071')->first();
        $this->assertNotNull($panji);
        $this->assertEquals('Panji Abyul Novian', $panji->name);
        $this->assertEquals('Manajer Kontrak', $panji->position->name);
        $this->assertEquals($spvIkhsan->id, $panji->manager_id);

        // Verifikasi Staf Area Bojonegara, Bitung, Medan, Surabaya
        $diah = Employee::where('identity_number', '125960268')->first();
        $this->assertNotNull($diah);
        $this->assertEquals('Bojonegara', $diah->workArea->name);
        $this->assertEquals($mahar->id, $diah->manager_id);

        $agus = Employee::where('identity_number', '125890278')->first();
        $this->assertNotNull($agus);
        $this->assertEquals('Bitung', $agus->workArea->name);

        $andriansyah = Employee::where('identity_number', '125910273')->first();
        $this->assertNotNull($andriansyah);
        $this->assertEquals('Medan', $andriansyah->workArea->name);
        $this->assertEquals($kartini->id, $andriansyah->manager_id);

        $imam = Employee::where('identity_number', '126770053')->first();
        $this->assertNotNull($imam);
        $this->assertEquals('Surabaya', $imam->workArea->name);

        // Total data pegawai: 80 pegawai pada unit kerja MESI, seluruhnya memiliki user, username, email, dan password "password"
        $this->assertEquals(80, Employee::count());
        $this->assertSame(0, Employee::whereNull('user_id')->count());
        $this->assertSame(80, Employee::whereHas('user', fn ($q) => $q->whereNotNull('username')->whereNotNull('email'))->count());
    }
}
