<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Auth\Login;
use App\Filament\Admin\Resources\DepartmentResource\Pages\ManageDepartments;
use App\Filament\Admin\Resources\EmployeeResource\Pages\ManageEmployees;
use App\Filament\Admin\Resources\PositionResource\Pages\ManagePositions;
use App\Filament\Admin\Resources\UserResource\Pages\ManageUsers;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use App\Services\MasterData\CsvImportExportService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataUsernameImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_navigation_groups_data_pegawai_and_hak_akses_are_collapsed(): void
    {
        $groups = collect(Filament::getPanel('admin')->getNavigationGroups());

        $this->assertCount(7, $groups);
        foreach ($groups as $group) {
            $this->assertTrue($group->isCollapsed(), "Navigation group {$group->getLabel()} should be collapsed.");
        }
    }

    public function test_user_can_login_with_username_or_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'username' => 'budi.santoso',
            'email' => 'budi@motiva.test',
            'password' => Hash::make('secret123'),
        ]);
        $user->assignRole('Staff');

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'budi.santoso',
                'password' => 'secret123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_superadmin_can_manage_username_in_user_resource(): void
    {
        $superadmin = User::factory()->create(['username' => 'superadmin']);
        $superadmin->assignRole('Superadmin');

        $this->actingAs($superadmin);

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: [
                'name' => 'Akun Baru',
                'username' => 'akun.baru',
                'email' => 'akun.baru@motiva.test',
                'password' => 'password123',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'akun.baru@motiva.test',
            'username' => 'akun.baru',
        ]);
    }

    public function test_import_and_export_actions_exist_and_csv_service_imports_master_data(): void
    {
        $superadmin = User::factory()->create(['username' => 'superadmin']);
        $superadmin->assignRole('Superadmin');
        $this->actingAs($superadmin);

        Livewire::test(ManageDepartments::class)
            ->assertActionExists('export')
            ->assertActionExists('import')
            ->mountAction('import')
            ->assertSee('Download Template Unit Kerja (.xls)');

        Livewire::test(ManagePositions::class)
            ->assertActionExists('export')
            ->assertActionExists('import')
            ->mountAction('import')
            ->assertSee('Download Template Jabatan (.xls)');

        Livewire::test(ManageEmployees::class)
            ->assertActionExists('export')
            ->assertActionExists('import')
            ->mountAction('import')
            ->assertSee('Download Template Import Pegawai (.xls)');

        Livewire::test(ManageUsers::class)->assertSuccessful();
        Livewire::test(\App\Filament\Admin\Resources\RoleResource\Pages\ManageRoles::class)->assertSuccessful();
        Livewire::test(\App\Filament\Admin\Resources\PermissionResource\Pages\ManagePermissions::class)->assertSuccessful();

        Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);

        Storage::fake('local');
        $service = app(CsvImportExportService::class);

        Storage::disk('local')->put('imports/depts.csv', "kode,nama_unit_kerja,organisasi\nIT,Teknologi Informasi,PT Permata Karya Jasa\n");
        $deptStats = $service->importDepartments('imports/depts.csv');
        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $deptStats);
        $this->assertDatabaseHas('departments', ['code' => 'IT', 'name' => 'Teknologi Informasi']);

        Storage::disk('local')->put('imports/positions.csv', "kode,nama_jabatan,level\nMGR-IT,Manajer IT,1\n");
        $posStats = $service->importPositions('imports/positions.csv');
        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $posStats);
        $this->assertDatabaseHas('positions', ['code' => 'MGR-IT', 'name' => 'Manajer IT', 'level' => 1]);

        $targetUser = User::factory()->create(['email' => 'siti@motiva.test', 'username' => null]);
        Storage::disk('local')->put('imports/employees.csv', "nip,nama_pegawai,kode_unit_kerja,kode_jabatan,atasan_langsung,email,username\nPKJ-901,Siti Aminah,IT,MGR-IT,-,siti@motiva.test,siti.aminah\n");
        $empStats = $service->importEmployees('imports/employees.csv');
        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $empStats);

        $this->assertDatabaseHas('employees', [
            'identity_number' => 'PKJ-901',
            'name' => 'Siti Aminah',
            'user_id' => $targetUser->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'username' => 'siti.aminah',
        ]);

        // Test XLS template generation & import round-trip
        $xlsResponse = $service->streamXlsDownload(
            'template-import-pegawai.xls',
            ['nip', 'nama_pegawai', 'kode_unit_kerja', 'kode_jabatan', 'atasan_langsung', 'email', 'username'],
            [['PKJ-902', 'Andi Wijaya', 'IT', 'MGR-IT', '-', '', '']]
        );
        ob_start();
        $xlsResponse->sendContent();
        $xlsContent = ob_get_clean();

        Storage::disk('local')->put('imports/employees.xls', (string) $xlsContent);
        $xlsStats = $service->importEmployees('imports/employees.xls');
        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $xlsStats);
        $this->assertDatabaseHas('employees', [
            'identity_number' => 'PKJ-902',
            'name' => 'Andi Wijaya',
        ]);
    }
}

