<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SppdCostResource\Pages\CalculateSppdCost;
use App\Filament\Admin\Resources\SppdCostResource\Pages\ListSppdCosts;
use App\Models\BusinessTrip;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Memo;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Sppd;
use App\Models\SppdCost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DomainSppdCostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_approved_sppd_appears_in_cost_list_without_create_button(): void
    {
        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $pos = Position::create(['name' => 'Staff Lapangan', 'code' => 'STF', 'level' => 4]);

        $hrUser = User::factory()->create(['name' => 'HR User']);
        $hrUser->assignRole('HR');

        $staffUser = User::factory()->create();
        $staffUser->assignRole('Staff');
        $staff = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'user_id' => $staffUser->id,
            'name' => 'Chairunnisa',
            'identity_number' => 'STF-001',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Site Visit Proyek',
            'destination' => 'Surabaya',
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-19',
        ]);

        // SPPD yang sudah disetujui Manajer (status: approved)
        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'sppd_number' => '0001/SPPD/MESI/11/2026',
            'status' => 'approved',
            'departure_date' => '2026-11-15',
            'return_date' => '2026-11-19',
            'total_days' => 5,
            'origin_location' => 'Jakarta',
            'destination_location' => 'Surabaya',
            'assignment_purpose' => 'Site Visit Proyek',
        ]);

        $this->actingAs($hrUser);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // SPPD yang disetujui manajer langsung tampil di list perhitungan biaya SPPD
        Livewire::test(ListSppdCosts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$sppd])
            ->assertSeeHtml('0001/SPPD/MESI/11/2026')
            ->assertSeeHtml('Chairunnisa')
            ->assertSeeHtml('Belum Dihitung')
            ->assertTableActionExists('calculate', record: $sppd)
            ->assertActionDoesNotExist('create');
    }

    public function test_hr_can_calculate_and_save_sppd_cost(): void
    {
        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $pos = Position::create(['name' => 'Staff Lapangan', 'code' => 'STF', 'level' => 4]);

        $hrUser = User::factory()->create(['name' => 'HR User']);
        $hrUser->assignRole('HR');

        $staffUser = User::factory()->create();
        $staffUser->assignRole('Staff');
        $staff = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'user_id' => $staffUser->id,
            'name' => 'Chairunnisa',
            'identity_number' => 'STF-001',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Site Visit Proyek',
            'destination' => 'Surabaya',
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-19',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'sppd_number' => '0001/SPPD/MESI/11/2026',
            'status' => 'approved',
            'departure_date' => '2026-11-15',
            'return_date' => '2026-11-19',
            'total_days' => 5,
            'origin_location' => 'Jakarta',
            'destination_location' => 'Surabaya',
            'assignment_purpose' => 'Site Visit Proyek',
        ]);

        $this->actingAs($hrUser);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // HR membuka form hitung biaya untuk SPPD yang disetujui
        Livewire::test(CalculateSppdCost::class, ['record' => $sppd->id])
            ->assertActionExists('back')
            ->assertSet('data.official_days', 5)
            ->assertSet('data.meal_rate', 145000)
            ->assertSet('data.pocket_money_rate', 120000)
            ->assertSet('data.local_transit_rate', 75000)
            ->assertSet('data.transport_terminal_rate', 100000)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.admin.resources.sppd-costs.index'));

        $this->assertDatabaseHas('sppd_costs', [
            'sppd_id' => $sppd->id,
            'official_days' => 5,
            'meal_rate' => 145000,
            'pocket_money_rate' => 120000,
        ]);

        $sppd->refresh();
        $this->assertNotNull($sppd->cost);
        $this->assertGreaterThan(0, $sppd->cost->total_cost);

        // Setelah disimpan, data di tabel otomatis memiliki status "Sudah Dihitung" dan tombol "Cetak"
        Livewire::test(ListSppdCosts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$sppd])
            ->assertSeeHtml('Sudah Dihitung')
            ->assertTableActionExists('print', record: $sppd)
            ->assertTableActionExists('edit_cost', record: $sppd);
    }

    public function test_sppd_cost_can_be_printed_as_pdf_fm_bs_02(): void
    {
        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $pos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('HR');

        $emp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'user_id' => $hrUser->id,
            'name' => 'Staff Test',
            'identity_number' => '12345',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Dinas Test',
            'destination' => 'Bandung',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-24',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $emp->id,
            'sppd_number' => '0002/SPPD/MESI/11/2026',
            'status' => 'approved',
            'total_days' => 5,
        ]);

        $cost = new SppdCost([
            'sppd_id' => $sppd->id,
            'creator_id' => $hrUser->id,
            'base_type' => 'office_base_stay',
            'official_days' => 5,
            'transport_terminal_qty' => 4,
            'transport_terminal_rate' => 100000,
            'meal_qty' => 5,
            'meal_rate' => 145000,
            'local_transit_qty' => 1,
            'local_transit_rate' => 75000,
            'pocket_money_qty' => 5,
            'pocket_money_rate' => 120000,
        ]);
        $cost->recalculateTotals();
        $cost->save();

        // 4 x 100.000 = 400.000
        // 5 x 145.000 = 725.000
        // 1 x 75.000 = 75.000
        // 5 x 120.000 = 600.000
        // Total = 400.000 + 1.400.000 = 1.800.000
        $this->assertEquals(1800000, $cost->total_cost);

        $response = $this->actingAs($hrUser)->get(route('sppd-costs.print', $cost));
        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_sppd_cost_list_shows_in_navigation_for_hr(): void
    {
        $hrUser = User::factory()->create();
        $hrUser->assignRole('HR');

        $this->actingAs($hrUser);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        Livewire::test(ListSppdCosts::class)
            ->assertSuccessful()
            ->assertSeeHtml('Daftar Perhitungan Biaya SPPD');
    }
}
