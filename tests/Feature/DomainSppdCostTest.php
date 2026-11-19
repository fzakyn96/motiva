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
        \Illuminate\Support\Facades\Storage::fake('public');

        // Tanpa evidence untuk komponen bernilai > 0, validasi menolak penyimpanan
        Livewire::test(CalculateSppdCost::class, ['record' => $sppd->id])
            ->assertActionExists('back')
            ->assertSet('data.official_days', 5)
            ->assertSet('data.meal_rate', 145000)
            ->assertSet('data.pocket_money_rate', 120000)
            ->assertSet('data.local_transit_rate', 75000)
            ->assertSet('data.transport_terminal_rate', 100000)
            ->call('save')
            ->assertHasFormErrors(['general_evidence'])
            ->fillForm([
                'general_evidence' => [
                    \Illuminate\Http\UploadedFile::fake()->image('tiket.jpg'),
                    \Illuminate\Http\UploadedFile::fake()->create('rekap-biaya.pdf', 100, 'application/pdf'),
                ],
            ])
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
        $this->assertNotEmpty($sppd->cost->general_evidence);

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

    public function test_sppd_cost_requires_manager_approval_and_syncs_to_monthly_official_travel_recap(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'MTV']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Jakarta', 'code' => 'JKT']);
        $mgrPos = Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);
        $stfPos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $dummyPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        $mgrUser = User::factory()->create([
            'name' => 'M Nuramzan Iftari',
            'signature_type' => 'draw',
            'signature_data' => $dummyPng,
        ]);
        $mgrUser->assignRole('Manager');
        $mgrEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $mgrUser->id,
            'name' => 'M Nuramzan Iftari',
            'identity_number' => 'MGR-COST-1',
        ]);

        $hrUser = User::factory()->create(['name' => 'HR Cost']);
        $hrUser->assignRole('HR');
        Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'user_id' => $hrUser->id,
            'manager_id' => $mgrEmp->id,
            'name' => 'HR Cost',
            'identity_number' => 'HR-COST-1',
        ]);

        $staff1 = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'manager_id' => $mgrEmp->id,
            'name' => 'Pegawai Satu',
            'identity_number' => 'STF-COST-1',
        ]);
        $staff2 = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'manager_id' => $mgrEmp->id,
            'name' => 'Pegawai Dua',
            'identity_number' => 'STF-COST-2',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Audit November',
            'destination' => 'Bandung',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
        ]);

        $sppd1 = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff1->id,
            'sppd_number' => '001/SPPD/JKT/11/2026',
            'status' => 'approved',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Bandung',
            'departure_date' => '2026-11-10',
            'return_date' => '2026-11-12',
            'total_days' => 3,
        ]);
        $sppd2 = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff2->id,
            'sppd_number' => '002/SPPD/JKT/11/2026',
            'status' => 'approved',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Bandung',
            'departure_date' => '2026-11-10',
            'return_date' => '2026-11-12',
            'total_days' => 3,
        ]);

        $this->actingAs($hrUser);
        \Illuminate\Support\Facades\Storage::fake('public');

        // 1. HR calculates & saves SPPD 1 & SPPD 2 costs -> automatically submitted as pending
        Livewire::test(CalculateSppdCost::class, ['record' => $sppd1->id])
            ->fillForm([
                'meal_qty' => 3,
                'meal_rate' => 150000,
                'general_evidence' => [\Illuminate\Http\UploadedFile::fake()->create('bukti1.pdf', 50, 'application/pdf')],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(CalculateSppdCost::class, ['record' => $sppd2->id])
            ->fillForm([
                'meal_qty' => 3,
                'meal_rate' => 100000,
                'general_evidence' => [\Illuminate\Http\UploadedFile::fake()->create('bukti2.pdf', 50, 'application/pdf')],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $cost1 = $sppd1->cost()->firstOrFail();
        $cost2 = $sppd2->cost()->firstOrFail();
        $this->assertSame('pending', $cost1->status);
        $this->assertSame('pending', $cost2->status);

        $req1 = \App\Models\ApprovalRequest::where('document_type', SppdCost::class)->where('document_id', $cost1->id)->firstOrFail();
        $req2 = \App\Models\ApprovalRequest::where('document_type', SppdCost::class)->where('document_id', $cost2->id)->firstOrFail();

        // Unapproved SPPD costs should NOT be synced yet
        $this->assertNull(\App\Models\OfficialTravelRecap::syncForMonth(11, 2026, $hrUser->id));

        // 2. Manager approves SPPD 1 & SPPD 2 costs (bulk approve) via Persetujuan Biaya SPPD
        $this->actingAs($mgrUser);
        $this->assertSame('Persetujuan', \App\Filament\Admin\Resources\SppdCostApprovalResource::getNavigationGroup());

        Livewire::test(\App\Filament\Admin\Resources\SppdCostApprovalResource\Pages\ListSppdCostApprovals::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$req1, $req2])
            ->callTableBulkAction('bulk_approve', [$req1, $req2], data: ['notes' => 'Disetujui Manajer'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('approved', $cost1->fresh()->status);
        $this->assertSame('approved', $cost2->fresh()->status);
        $this->assertDatabaseHas('document_signatures', [
            'document_type' => SppdCost::class,
            'document_id' => $cost1->id,
            'user_id' => $mgrUser->id,
            'role_type' => 'manager',
        ]);

        // 3. HR clicks "Buat / Sinkronkan Rekap Bulan Ini" -> gathers both Manager-approved SPPD costs
        $this->actingAs($hrUser);
        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ListOfficialTravelRecaps::class)
            ->callAction('sync_monthly_recap', data: ['month' => 11, 'year' => 2026])
            ->assertHasNoActionErrors();

        $recap = \App\Models\OfficialTravelRecap::where('period_month', 'November')->where('period_label', 'November 2026')->firstOrFail();
        $this->assertSame('Pekerjaan ' . $dept->name, $recap->work_name);
        $this->assertSame('November', $recap->period_month);
        $this->assertSame('November 2026', $recap->period_label);
        $this->assertCount(2, $recap->items);
        $this->assertEquals((float) $cost1->total_cost + (float) $cost2->total_cost, (float) $recap->total_work_cost);

        // Verify EditOfficialTravelRecap hides Nama Pekerjaan, Bulan Periode (Kolom Tabel), Judul Periode, and shows Unit Kerja + Management Fee
        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\EditOfficialTravelRecap::class, ['record' => $recap->id])
            ->assertSuccessful()
            ->assertDontSee('Nama Pekerjaan')
            ->assertDontSee('Bulan Periode (Kolom Tabel)')
            ->assertDontSee('Judul Periode')
            ->assertSee('Unit Kerja')
            ->assertSee('Persentase Management Fee (%)');

        // Calling sync multiple times must NOT duplicate recaps
        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ListOfficialTravelRecaps::class)
            ->callAction('sync_monthly_recap', data: ['month' => 11, 'year' => 2026])
            ->assertHasNoActionErrors();
        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ListOfficialTravelRecaps::class)
            ->callAction('sync_monthly_recap', data: ['month' => 11, 'year' => 2026])
            ->assertHasNoActionErrors();

        $this->assertSame(1, \App\Models\OfficialTravelRecap::where('period_label', 'November 2026')->count());

        // 4. Multi-month catch-up: approved SPPD cost from 2 months ago (September 2026) is synced via sync_all_months
        $oldSppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff1->id,
            'sppd_number' => '090/SPPD/JKT/09/2026',
            'status' => 'approved',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Surabaya',
            'departure_date' => '2026-09-10',
            'return_date' => '2026-09-12',
            'total_days' => 3,
        ]);
        $oldCost = SppdCost::create([
            'sppd_id' => $oldSppd->id,
            'creator_id' => $hrUser->id,
            'official_days' => 3,
            'meal_qty' => 3,
            'meal_rate' => 120000,
            'meal_total' => 360000,
            'subtotal_per_diem' => 360000,
            'total_cost' => 360000,
            'status' => 'approved',
        ]);

        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ListOfficialTravelRecaps::class)
            ->callAction('sync_monthly_recap', data: ['sync_all_months' => true])
            ->assertHasNoActionErrors();

        $oldRecap = \App\Models\OfficialTravelRecap::where('period_month', 'September')->where('period_label', 'September 2026')->firstOrFail();
        $this->assertSame('Pekerjaan ' . $dept->name, $oldRecap->work_name);
        $this->assertCount(1, $oldRecap->items);
        $this->assertEquals(360000, (float) $oldRecap->total_work_cost);
    }
}
