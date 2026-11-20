<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ReimbursementSettingsPage;
use App\Filament\Admin\Resources\OfficialTravelApprovalResource;
use App\Filament\Admin\Resources\OfficialTravelApprovalResource\Pages\ListOfficialTravelApprovals;
use App\Filament\Admin\Resources\OfficialTravelRecapResource;
use App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\CreateOfficialTravelRecap;
use App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ListOfficialTravelRecaps;
use App\Filament\Admin\Resources\TransportAccommodationApprovalResource;
use App\Filament\Admin\Resources\TransportAccommodationApprovalResource\Pages\ListTransportAccommodationApprovals;
use App\Filament\Admin\Resources\TransportAccommodationRecapResource;
use App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages\CreateTransportAccommodationRecap;
use App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages\ListTransportAccommodationRecaps;
use App\Models\ApprovalRequest;
use App\Models\BusinessTrip;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\ReimbursementSetting;
use App\Models\Sppd;
use App\Models\SppdCost;
use App\Models\TransportAccommodationRecap;
use App\Models\OfficialTravelRecap;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReimbursementRecapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_old_reimbursement_menus_hidden_and_recap_crud_permissions(): void
    {
        $this->assertFalse(class_exists(\App\Filament\Admin\Resources\ReimbursementResource::class));
        $this->assertFalse(class_exists(\App\Filament\Admin\Resources\ReimbursementApprovalResource::class));

        $hr = User::factory()->create();
        $hr->assignRole('HR');
        $this->actingAs($hr);
        $this->assertTrue(TransportAccommodationRecapResource::shouldRegisterNavigation());
        $this->assertTrue(OfficialTravelRecapResource::shouldRegisterNavigation());
        $this->assertTrue(TransportAccommodationRecapResource::canCreate());
        $this->assertTrue(OfficialTravelRecapResource::canCreate());

        $staff = User::factory()->create();
        $staff->assignRole('Staff');
        $this->actingAs($staff);
        $this->assertFalse(TransportAccommodationRecapResource::canViewAny());
        $this->assertFalse(OfficialTravelRecapResource::canViewAny());
    }

    public function test_reimbursement_fee_settings_defaults_and_updates(): void
    {
        $setting = ReimbursementSetting::current();
        $this->assertEquals(0.0, (float) $setting->transport_service_fee_percent);
        $this->assertEquals(5.0, (float) $setting->transport_manfee_percent);
        $this->assertEquals(6.0, (float) $setting->travel_manfee_percent);

        $hr = User::factory()->create();
        $hr->assignRole('HR');
        $this->actingAs($hr);

        Livewire::test(ReimbursementSettingsPage::class)
            ->assertSuccessful()
            ->assertDontSee('Service Fee Transportasi & Akomodasi (%)')
            ->fillForm([
                'transport_manfee_percent' => 7.5,
                'travel_manfee_percent' => 8,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $updated = ReimbursementSetting::current()->fresh();
        $this->assertEquals(7.5, (float) $updated->transport_manfee_percent);
        $this->assertEquals(8.0, (float) $updated->travel_manfee_percent);

        // Set batas maksimal manfee: Transport = 5%, Travel = 6%
        $updated->update([
            'transport_manfee_percent' => 5.0,
            'travel_manfee_percent' => 6.0,
        ]);

        $org = Organization::create(['name' => 'PT. Perusahaan Gas Negara (Persero) Tbk.', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);

        $transportRecap = TransportAccommodationRecap::create([
            'department_id' => $dept->id,
            'period_month' => 'November 2026',
            'period_label' => 'November 2026',
            'work_name' => 'Pekerjaan MESI',
            'manfee_percent' => 5.0,
        ]);
        $transportRecap->items()->create([
            'description' => 'Tiket Pesawat',
            'amount' => 1000000,
            'service_fee' => 0,
            'total_amount' => 1000000,
        ]);
        $transportRecap->recalculateTotals();

        $travelRecap = OfficialTravelRecap::create([
            'department_id' => $dept->id,
            'period_month' => 'November',
            'period_label' => 'November 2026',
            'work_name' => 'Pekerjaan MESI',
            'manfee_percent' => 6.0,
        ]);
        $travelRecap->items()->create([
            'description' => 'SPPD Dinas',
            'amount' => 2000000,
        ]);
        $travelRecap->recalculateTotals();

        // Tidak boleh melebihi batas maksimal pengaturan (5% untuk Transport, 6% untuk Travel)
        Livewire::test(\App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages\EditTransportAccommodationRecap::class, ['record' => $transportRecap->id])
            ->fillForm(['manfee_percent' => 6.0])
            ->call('save')
            ->assertHasFormErrors(['manfee_percent' => 'max']);

        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\EditOfficialTravelRecap::class, ['record' => $travelRecap->id])
            ->fillForm(['manfee_percent' => 7.0])
            ->call('save')
            ->assertHasFormErrors(['manfee_percent' => 'max']);

        // Boleh disesuaikan lebih kecil dari batas maksimal pengaturan (misal 3% untuk Transport, 4% untuk Travel)
        Livewire::test(\App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages\EditTransportAccommodationRecap::class, ['record' => $transportRecap->id])
            ->fillForm(['manfee_percent' => 3.0])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(3.0, (float) $transportRecap->fresh()->manfee_percent);
        $this->assertEquals(30000.0, (float) $transportRecap->fresh()->manfee_amount);

        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\EditOfficialTravelRecap::class, ['record' => $travelRecap->id])
            ->fillForm(['manfee_percent' => 4.0])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(4.0, (float) $travelRecap->fresh()->manfee_percent);
        $this->assertEquals(80000.0, (float) $travelRecap->fresh()->manfee_amount);
    }

    public function test_hr_creates_recaps_with_autofill_and_manager_plus_division_head_approve_and_sign(): void
    {
        Storage::fake('public');

        $orgPkj = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $orgPgn = Organization::create(['name' => 'PT Perusahaan Gas Negara (Persero) Tbk.', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $orgPgn->id, 'name' => 'MESI', 'code' => 'MESI']);

        $divPos = Position::create(['name' => 'Division Head Asset Integrity Management System', 'code' => 'DIV-AIMS', 'level' => 0]);
        $mgrPos = Position::create(['name' => 'Sr. Expert I. Infrastructure Information System', 'code' => 'SR-EXP-IIS', 'level' => 1]);
        $bdhPos = Position::create(['name' => 'Business Division Head', 'code' => 'BDH', 'level' => 1]);
        $stfPos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $dummyPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $herryUser = User::factory()->create([
            'name' => 'Herry Rachmadi',
            'signature_type' => 'draw',
            'signature_data' => $dummyPng,
        ]);
        $herryUser->assignRole('Division Head');
        $herry = Employee::create([
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'position_id' => $divPos->id,
            'user_id' => $herryUser->id,
            'name' => 'Herry Rachmadi',
            'identity_number' => 'DIV-01',
        ]);

        $khairul = Employee::create([
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'position_id' => $bdhPos->id,
            'name' => 'Khairul Khitam',
            'identity_number' => 'BDH-01',
        ]);

        $nuramzanUser = User::factory()->create([
            'name' => 'M. Nuramzan Iftari',
            'signature_type' => 'draw',
            'signature_data' => $dummyPng,
        ]);
        $nuramzanUser->assignRole('Manager');
        $nuramzan = Employee::create([
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $nuramzanUser->id,
            'manager_id' => $herry->id,
            'name' => 'M. Nuramzan Iftari',
            'identity_number' => 'MGR-01',
        ]);

        $hrUser = User::factory()->create(['name' => 'HR Rekap']);
        $hrUser->assignRole('HR');
        Employee::create([
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'user_id' => $hrUser->id,
            'manager_id' => $nuramzan->id,
            'name' => 'HR Rekap',
            'identity_number' => 'HR-02',
        ]);

        $staff = Employee::create([
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'manager_id' => $nuramzan->id,
            'name' => 'Chairunnisa',
            'identity_number' => 'STF-01',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Audit Infrastruktur MESI',
            'destination' => 'Batam',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
        ]);
        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'sppd_number' => '0001/SPPD/MESI/11/2026',
            'status' => 'approved',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Batam',
            'departure_date' => '2026-11-10',
            'return_date' => '2026-11-12',
            'total_days' => 3,
        ]);
        SppdCost::create([
            'sppd_id' => $sppd->id,
            'creator_id' => $hrUser->id,
            'official_days' => 3,
            'transport_ticket_qty' => 1,
            'transport_ticket_rate' => 1500000,
            'transport_ticket_total' => 1500000,
            'subtotal_transport' => 1500000,
            'hotel_qty' => 2,
            'hotel_rate' => 500000,
            'hotel_total' => 1000000,
            'subtotal_per_diem' => 1000000,
            'total_cost' => 2500000,
        ]);

        ReimbursementSetting::current()->update([
            'transport_service_fee_percent' => 10,
            'transport_manfee_percent' => 5,
            'travel_manfee_percent' => 6,
        ]);

        $trip2 = BusinessTrip::create([
            'activity_name' => 'Operasional Cabang Bulan Sama',
            'destination' => 'Surabaya',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-21',
        ]);
        $sppd2 = Sppd::create([
            'business_trip_id' => $trip2->id,
            'employee_id' => $staff->id,
            'sppd_number' => '0002/SPPD/MESI/11/2026',
            'status' => 'approved',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Surabaya',
            'departure_date' => '2026-11-20',
            'return_date' => '2026-11-21',
            'total_days' => 2,
        ]);
        SppdCost::create([
            'sppd_id' => $sppd2->id,
            'creator_id' => $hrUser->id,
            'official_days' => 2,
            'total_cost' => 1000000,
        ]);

        // 1. HR membuat Rekap Tiket & Akomodasi bulanan dari > 1 SPPD dalam bulan yang sama
        $this->actingAs($hrUser);
        Livewire::test(CreateTransportAccommodationRecap::class)
            ->fillForm([
                'business_trip_ids' => [$trip->id, $trip2->id],
                'grouping_mode' => 'combined',
                'items' => [
                    [
                        'sppd_ids' => [$sppd->id, $sppd2->id],
                        'description' => 'Tiket Pesawat Chairunnisa Jakarta-Batam',
                        'amount' => 1500000,
                        'service_fee' => 150000,
                        'evidence_files' => [UploadedFile::fake()->create('invoice-tiket.pdf', 50, 'application/pdf')],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $transportRecap = TransportAccommodationRecap::firstOrFail();
        $this->assertEquals([$trip->id, $trip2->id], $transportRecap->business_trip_ids);
        $this->assertEquals('Pekerjaan MESI', $transportRecap->work_name);
        $this->assertEquals(1650000, (float) $transportRecap->total_cost);
        $this->assertEquals(82500, (float) $transportRecap->manfee_amount);
        $this->assertEquals(1732500, (float) $transportRecap->grand_total);

        // 2. HR membuat Rekap Perjalanan Dinas bulanan dari > 1 SPPD dalam bulan yang sama (auto-isi 2 baris)
        Livewire::test(CreateOfficialTravelRecap::class)
            ->fillForm([
                'business_trip_ids' => [$trip->id, $trip2->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $travelRecap = OfficialTravelRecap::firstOrFail();
        $this->assertEquals([$trip->id, $trip2->id], $travelRecap->business_trip_ids);
        $this->assertEquals(3500000, (float) $travelRecap->total_work_cost);
        $this->assertEquals(210000, (float) $travelRecap->manfee_amount);
        $this->assertEquals(3710000, (float) $travelRecap->grand_total);

        // 3. HR mengajukan review untuk Rekap Transportasi & Rekap Perjalanan Dinas
        Livewire::test(ListTransportAccommodationRecaps::class)
            ->callTableAction('submit_recap', $transportRecap)
            ->assertHasNoTableActionErrors();

        Livewire::test(ListOfficialTravelRecaps::class)
            ->callTableAction('submit_recap', $travelRecap)
            ->assertHasNoTableActionErrors();

        // 4. Manajer memeriksa detail & menandatangani di halaman Review, lalu Division Head menyetujui & menandatangani di halaman Review
        $evidencePath = $transportRecap->items()->firstOrFail()->evidence_files[0] ?? null;
        $this->assertNotNull($evidencePath);

        $this->actingAs($nuramzanUser);
        $this->get(route('attachments.show', ['path' => $evidencePath]))->assertSuccessful();

        Livewire::test(ListTransportAccommodationRecaps::class)
            ->assertTableActionVisible('view', $transportRecap)
            ->assertTableActionHidden('submit_recap', $transportRecap)
            ->assertTableActionDoesNotExist('approve_recap');

        Livewire::test(\App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages\ViewTransportAccommodationRecap::class, ['record' => $transportRecap->id])
            ->assertSuccessful()
            ->assertSee('Tiket Pesawat Chairunnisa Jakarta-Batam')
            ->assertActionVisible('approve_recap')
            ->callAction('approve_recap', data: ['notes' => 'Diperiksa Manajer'])
            ->assertHasNoActionErrors();

        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ViewOfficialTravelRecap::class, ['record' => $travelRecap->id])
            ->assertSuccessful()
            ->assertActionVisible('approve_recap')
            ->callAction('approve_recap', data: ['notes' => 'Diperiksa Manajer'])
            ->assertHasNoActionErrors();

        $sppdCost = $travelRecap->items()->firstOrFail()->sppdCost;

        $this->actingAs($nuramzanUser);
        $this->get(route('sppd-costs.print', $sppdCost))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($herryUser);
        $this->get(route('sppd-costs.print', $sppdCost))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('attachments.show', ['path' => $evidencePath]))->assertSuccessful();

        Livewire::test(\App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages\ViewTransportAccommodationRecap::class, ['record' => $transportRecap->id])
            ->assertSuccessful()
            ->assertActionVisible('approve_recap')
            ->callAction('approve_recap', data: ['notes' => 'Disetujui Division Head'])
            ->assertHasNoActionErrors();

        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ViewOfficialTravelRecap::class, ['record' => $travelRecap->id])
            ->assertSuccessful()
            ->assertSee('Rincian Komponen Biaya SPPD')
            ->assertSee('Tiket Transportasi')
            ->assertSee('Lihat Rincian SPPD (PDF)')
            ->assertActionVisible('approve_recap')
            ->callAction('approve_recap', data: ['notes' => 'Disetujui Division Head'])
            ->assertHasNoActionErrors();

        Livewire::test(ListOfficialTravelRecaps::class)
            ->assertTableActionVisible('view', $travelRecap)
            ->assertTableActionHidden('submit_recap', $travelRecap)
            ->filterTable('periode_bulan', ['month' => 11, 'year' => 2026])
            ->assertCanSeeTableRecords([$travelRecap]);

        $this->assertDatabaseHas('document_signatures', [
            'document_type' => TransportAccommodationRecap::class,
            'document_id' => $transportRecap->id,
            'user_id' => $herryUser->id,
            'role_type' => 'division_head',
        ]);

        $this->assertDatabaseHas('document_signatures', [
            'document_type' => OfficialTravelRecap::class,
            'document_id' => $travelRecap->id,
            'user_id' => $herryUser->id,
            'role_type' => 'division_head',
        ]);

        $this->get(route('recaps.transport.print', $transportRecap))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get(route('recaps.travel.print', $travelRecap))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_recap_approvals_appear_in_persetujuan_navigation_group_and_can_be_approved(): void
    {
        $this->assertSame('Persetujuan', TransportAccommodationApprovalResource::getNavigationGroup());
        $this->assertSame('Persetujuan', OfficialTravelApprovalResource::getNavigationGroup());

        $orgPgn = Organization::create(['name' => 'PT Perusahaan Gas Negara (Persero) Tbk.', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $orgPgn->id, 'name' => 'Operasi', 'code' => 'OPS']);
        $divPos = Position::create(['name' => 'Division Head', 'code' => 'DIV', 'level' => 0]);
        $mgrPos = Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);
        $stfPos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $dummyPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        $herryUser = User::factory()->create([
            'name' => 'Herry Rachmadi',
            'signature_type' => 'draw',
            'signature_data' => $dummyPng,
        ]);
        $herryUser->assignRole('Division Head');
        $herry = Employee::create([
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'position_id' => $divPos->id,
            'user_id' => $herryUser->id,
            'name' => 'Herry Rachmadi',
            'identity_number' => 'DIV-02',
        ]);

        $nuramzanUser = User::factory()->create([
            'name' => 'M. Nuramzan Iftari',
            'signature_type' => 'draw',
            'signature_data' => $dummyPng,
        ]);
        $nuramzanUser->assignRole('Manager');
        $nuramzan = Employee::create([
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $nuramzanUser->id,
            'manager_id' => $herry->id,
            'name' => 'M. Nuramzan Iftari',
            'identity_number' => 'MGR-02',
        ]);

        $staffUser = User::factory()->create(['name' => 'Staff User']);
        $staffUser->assignRole('Staff');
        $staff = Employee::create([
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'user_id' => $staffUser->id,
            'manager_id' => $nuramzan->id,
            'name' => 'Staff User',
            'identity_number' => 'STF-02',
        ]);

        $hrUser = User::factory()->create(['name' => 'HR Rekap']);
        $hrUser->assignRole('HR');
        Employee::create([
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'user_id' => $hrUser->id,
            'manager_id' => $nuramzan->id,
            'name' => 'HR Rekap',
            'identity_number' => 'HR-02',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Inspeksi Lapangan',
            'destination' => 'Surabaya',
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-07',
        ]);
        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'sppd_number' => '0002/SPPD/MESI/11/2026',
            'status' => 'approved',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Surabaya',
            'departure_date' => '2026-11-05',
            'return_date' => '2026-11-07',
            'total_days' => 3,
        ]);
        SppdCost::create([
            'sppd_id' => $sppd->id,
            'creator_id' => $hrUser->id,
            'official_days' => 3,
            'transport_ticket_qty' => 1,
            'transport_ticket_rate' => 1200000,
            'transport_ticket_total' => 1200000,
            'subtotal_transport' => 1200000,
            'hotel_qty' => 2,
            'hotel_rate' => 450000,
            'hotel_total' => 900000,
            'subtotal_accommodation' => 900000,
            'allowance_qty' => 3,
            'allowance_rate' => 250000,
            'allowance_total' => 750000,
            'subtotal_allowance' => 750000,
            'total_cost' => 2850000,
        ]);

        $this->actingAs($hrUser);

        Livewire::test(CreateTransportAccommodationRecap::class)
            ->fillForm([
                'business_trip_ids' => [$trip->id],
                'grouping_mode' => 'combined',
                'items' => [
                    [
                        'sppd_ids' => [$sppd->id],
                        'description' => 'Tiket & Hotel Surabaya',
                        'amount' => 2100000,
                        'service_fee' => 210000,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateOfficialTravelRecap::class)
            ->fillForm([
                'business_trip_ids' => [$trip->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $transportRecap = TransportAccommodationRecap::firstOrFail();
        $travelRecap = OfficialTravelRecap::firstOrFail();

        Livewire::test(ListTransportAccommodationRecaps::class)
            ->callTableAction('submit_recap', $transportRecap)
            ->assertHasNoTableActionErrors();

        Livewire::test(ListOfficialTravelRecaps::class)
            ->callTableAction('submit_recap', $travelRecap)
            ->assertHasNoTableActionErrors();

        $transportApproval = ApprovalRequest::where('document_type', TransportAccommodationRecap::class)
            ->where('document_id', $transportRecap->id)
            ->firstOrFail();
        $travelApproval = ApprovalRequest::where('document_type', OfficialTravelRecap::class)
            ->where('document_id', $travelRecap->id)
            ->firstOrFail();

        $this->actingAs($staffUser);
        $this->assertFalse(TransportAccommodationApprovalResource::canViewAny());
        $this->assertFalse(OfficialTravelApprovalResource::canViewAny());

        $this->actingAs($nuramzanUser);
        $this->assertTrue(TransportAccommodationApprovalResource::canViewAny());
        $this->assertTrue(OfficialTravelApprovalResource::canViewAny());
        $this->assertSame('1', TransportAccommodationApprovalResource::getNavigationBadge());
        $this->assertSame('1', OfficialTravelApprovalResource::getNavigationBadge());

        Livewire::test(ListTransportAccommodationApprovals::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$transportApproval])
            ->assertTableActionVisible('view_detail', $transportApproval)
            ->assertTableActionDoesNotExist('approve');

        Livewire::test(\App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages\ViewTransportAccommodationRecap::class, ['record' => $transportRecap->id])
            ->callAction('approve_recap', data: ['notes' => 'OK Manager'])
            ->assertHasNoActionErrors();

        Livewire::test(ListOfficialTravelApprovals::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$travelApproval])
            ->assertTableActionVisible('view_detail', $travelApproval)
            ->assertTableActionDoesNotExist('approve');

        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ViewOfficialTravelRecap::class, ['record' => $travelRecap->id])
            ->callAction('approve_recap', data: ['notes' => 'OK Manager'])
            ->assertHasNoActionErrors();

        $this->actingAs($herryUser);
        Livewire::test(ListTransportAccommodationApprovals::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$transportApproval])
            ->assertTableActionVisible('view_detail', $transportApproval)
            ->assertTableActionDoesNotExist('approve');

        Livewire::test(\App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages\ViewTransportAccommodationRecap::class, ['record' => $transportRecap->id])
            ->callAction('approve_recap', data: ['notes' => 'OK Division Head'])
            ->assertHasNoActionErrors();

        Livewire::test(ListOfficialTravelApprovals::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$travelApproval])
            ->assertTableActionVisible('view_detail', $travelApproval)
            ->assertTableActionDoesNotExist('approve');

        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ViewOfficialTravelRecap::class, ['record' => $travelRecap->id])
            ->callAction('approve_recap', data: ['notes' => 'OK Division Head'])
            ->assertHasNoActionErrors();

        $this->assertSame('approved', $transportApproval->fresh()->status);
        $this->assertSame('approved', $travelApproval->fresh()->status);
        $this->assertNull(TransportAccommodationApprovalResource::getNavigationBadge());
        $this->assertNull(OfficialTravelApprovalResource::getNavigationBadge());
    }

    public function test_business_division_head_can_approve_recaps_like_manager_and_renders_as_prepared_by(): void
    {
        $orgPkj = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $orgPgn = Organization::create(['name' => 'PT Perusahaan Gas Negara (Persero) Tbk.', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $orgPgn->id, 'name' => 'MESI', 'code' => 'MESI']);

        $divPos = Position::create(['name' => 'Division Head Asset Integrity Management System', 'code' => 'DIV-AIMS', 'level' => 0]);
        $bdhPos = Position::create(['name' => 'Business Division Head', 'code' => 'BDH', 'level' => 1]);
        $mgrPos = Position::create(['name' => 'Sr. Expert I. Infrastructure Information System', 'code' => 'MGR', 'level' => 1]);
        $staffPos = Position::create(['name' => 'Staff', 'code' => 'STAFF', 'level' => 3]);

        $dummyPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $herryUser = User::factory()->create([
            'name' => 'Herry Rachmadi',
            'signature_type' => 'draw',
            'signature_data' => $dummyPng,
        ]);
        $herryUser->assignRole('Division Head');
        $herryEmp = Employee::create([
            'user_id' => $herryUser->id,
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'position_id' => $divPos->id,
            'identity_number' => '125000000',
            'name' => 'Herry Rachmadi',
        ]);

        $khairulUser = User::factory()->create([
            'name' => 'Khairul Khitam',
            'signature_type' => 'draw',
            'signature_data' => $dummyPng,
        ]);
        $khairulUser->assignRole('Manager');
        Employee::create([
            'user_id' => $khairulUser->id,
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'position_id' => $bdhPos->id,
            'identity_number' => '125000005',
            'name' => 'Khairul Khitam',
        ]);

        $nuramzanUser = User::factory()->create([
            'name' => 'M Nuramzan Iftari',
            'signature_type' => 'draw',
            'signature_data' => $dummyPng,
        ]);
        $nuramzanUser->assignRole('Manager');
        $nuramzanEmp = Employee::create([
            'user_id' => $nuramzanUser->id,
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'manager_id' => $herryEmp->id,
            'identity_number' => '125000001',
            'name' => 'M Nuramzan Iftari',
        ]);

        $workArea = \App\Models\WorkArea::create([
            'department_id' => $dept->id,
            'code' => 'REG1',
            'name' => 'Regional 1',
            'coordinator_employee_id' => $nuramzanEmp->id,
        ]);

        $hrUser = User::factory()->create(['name' => 'HR User']);
        $hrUser->assignRole('HR');
        $hrEmp = Employee::create([
            'user_id' => $hrUser->id,
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'work_area_id' => $workArea->id,
            'position_id' => $staffPos->id,
            'identity_number' => '125000004',
            'name' => 'HR User',
        ]);

        $this->actingAs($hrUser);

        $trip = BusinessTrip::create([
            'activity_name' => 'Kunjungan Lapangan',
            'destination' => 'Jakarta',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'status' => 'completed',
            'created_by' => $hrUser->id,
        ]);
        Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $hrEmp->id,
            'sppd_number' => '0099/SPPD/MESI/11/2026',
            'status' => 'approved',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Batam',
            'departure_date' => '2026-11-01',
            'return_date' => '2026-11-02',
            'total_days' => 2,
        ]);

        $transportRecap = TransportAccommodationRecap::create([
            'department_id' => $dept->id,
            'business_trip_id' => $trip->id,
            'period_month' => 'November 2026',
            'period_label' => 'November 2026',
            'work_name' => 'Pekerjaan MESI',
            'created_by' => $hrUser->id,
        ]);

        $travelRecap = OfficialTravelRecap::create([
            'department_id' => $dept->id,
            'business_trip_id' => $trip->id,
            'period_month' => 'November 2026',
            'period_label' => 'November 2026',
            'work_name' => 'Pekerjaan MESI',
            'created_by' => $hrUser->id,
        ]);

        $this->assertSame('Khairul Khitam', $transportRecap->prepared_by_name);
        $this->assertSame('Business Division Head', $transportRecap->prepared_by_position);
        $this->assertSame('Khairul Khitam', $travelRecap->prepared_by_name);
        $this->assertSame('Business Division Head', $travelRecap->prepared_by_position);

        // HR mengajukan rekap (Khairul Khitam tidak perlu mengajukan)
        Livewire::test(ListTransportAccommodationRecaps::class)
            ->callTableAction('submit_recap', $transportRecap)
            ->assertHasNoTableActionErrors();

        Livewire::test(ListOfficialTravelRecaps::class)
            ->callTableAction('submit_recap', $travelRecap)
            ->assertHasNoTableActionErrors();

        // Step 1: M Nuramzan Iftari (Koordinator Area Kerja / Manager PGN) memeriksa & menandatangani terlebih dahulu (kolom Diperiksa oleh)
        $this->actingAs($nuramzanUser);
        Livewire::test(\App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages\ViewTransportAccommodationRecap::class, ['record' => $transportRecap->id])
            ->callAction('approve_recap', data: ['notes' => 'Diperiksa Koordinator Area Kerja'])
            ->assertHasNoActionErrors();

        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ViewOfficialTravelRecap::class, ['record' => $travelRecap->id])
            ->callAction('approve_recap', data: ['notes' => 'Diperiksa Koordinator Area Kerja'])
            ->assertHasNoActionErrors();

        // Step 2: Khairul Khitam (Division Head Perkasa) menyetujui & menandatangani Rekap (kolom Disusun oleh)
        $this->actingAs($khairulUser);
        $this->assertFalse(\App\Filament\Admin\Resources\MemoApprovalResource::canViewAny());
        $this->assertFalse(\App\Filament\Admin\Resources\SppdApprovalResource::canViewAny());
        $this->assertFalse(\App\Filament\Admin\Resources\SppdCostApprovalResource::canViewAny());
        $this->assertTrue(TransportAccommodationApprovalResource::canViewAny());
        $this->assertTrue(OfficialTravelApprovalResource::canViewAny());

        Livewire::test(\App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages\ViewTransportAccommodationRecap::class, ['record' => $transportRecap->id])
            ->callAction('approve_recap', data: ['notes' => 'OK Division Head Perkasa'])
            ->assertHasNoActionErrors();

        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ViewOfficialTravelRecap::class, ['record' => $travelRecap->id])
            ->callAction('approve_recap', data: ['notes' => 'OK Division Head Perkasa'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('document_signatures', [
            'document_type' => TransportAccommodationRecap::class,
            'document_id' => $transportRecap->id,
            'user_id' => $khairulUser->id,
            'role_type' => 'requester',
        ]);
        $this->assertDatabaseHas('document_signatures', [
            'document_type' => OfficialTravelRecap::class,
            'document_id' => $travelRecap->id,
            'user_id' => $khairulUser->id,
            'role_type' => 'requester',
        ]);

        // Step 3: Herry Rachmadi (Division Head PGN) menyetujui & menandatangani (kolom Disetujui oleh)
        $this->actingAs($herryUser);
        Livewire::test(\App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages\ViewTransportAccommodationRecap::class, ['record' => $transportRecap->id])
            ->callAction('approve_recap', data: ['notes' => 'Disetujui Division Head PGN'])
            ->assertHasNoActionErrors();

        Livewire::test(\App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages\ViewOfficialTravelRecap::class, ['record' => $travelRecap->id])
            ->callAction('approve_recap', data: ['notes' => 'Disetujui Division Head PGN'])
            ->assertHasNoActionErrors();

        // Verify print views render Khairul Khitam / Business Division Head in Disusun oleh
        $transportHtml = view('recaps.transport-print', [
            'trip' => $transportRecap->fresh(['items', 'department', 'businessTrip']),
            'summary' => [
                'rows' => [],
                'subtotal_amount' => 0.0,
                'total_service_fee' => 0.0,
                'total_cost' => 0.0,
                'manfee_percent' => 5.0,
                'manfee_amount' => 0.0,
                'grand_total' => 0.0,
            ],
            'signatories' => $transportRecap->resolveSignatories(),
            'step0Approved' => true,
            'step1Approved' => true,
            'step2Approved' => true,
            'periodMonth' => $transportRecap->period_month,
            'periodLabel' => $transportRecap->period_label,
            'workName' => $transportRecap->work_name,
        ])->render();

        $travelHtml = view('recaps.travel-print', [
            'trip' => $travelRecap->fresh(['items', 'department', 'businessTrip']),
            'summary' => [
                'rows' => [],
                'total_work_cost' => 0.0,
                'manfee_percent' => 6.0,
                'manfee_amount' => 0.0,
                'grand_total' => 0.0,
            ],
            'signatories' => $travelRecap->resolveSignatories(),
            'step0Approved' => true,
            'step1Approved' => true,
            'step2Approved' => true,
            'periodMonth' => $travelRecap->period_month,
            'periodLabel' => $travelRecap->period_label,
            'workName' => $travelRecap->work_name,
        ])->render();

        $this->assertStringContainsString('Khairul Khitam', $transportHtml);
        $this->assertStringContainsString('Business Division Head', $transportHtml);
        $this->assertStringContainsString('Khairul Khitam', $travelHtml);
        $this->assertStringContainsString('Business Division Head', $travelHtml);
    }

    public function test_multi_area_recap_requires_all_area_managers_approval_and_isolates_hr_by_work_area(): void
    {
        $orgPkj = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $orgPgn = Organization::create(['name' => 'PT Perusahaan Gas Negara (Persero) Tbk.', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $orgPgn->id, 'name' => 'MESI', 'code' => 'MESI']);

        $divPos = Position::create(['name' => 'Division Head AIMS', 'code' => 'DIV-AIMS', 'level' => 0]);
        $bdhPos = Position::create(['name' => 'Business Division Head', 'code' => 'BDH-PKJ', 'level' => 1]);
        $mgrPos = Position::create(['name' => 'Manager Area', 'code' => 'MGR', 'level' => 1]);
        $spvPos = Position::create(['name' => 'Supervisor', 'code' => 'SPV', 'level' => 2]);
        $stfPos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $dummyPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $divUser = User::factory()->create(['name' => 'Herry Rachmadi', 'signature_type' => 'draw', 'signature_data' => $dummyPng]);
        $divUser->assignRole('Division Head');
        $divEmp = Employee::create([
            'user_id' => $divUser->id,
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'position_id' => $divPos->id,
            'identity_number' => 'PGN-001',
            'name' => 'Herry Rachmadi',
        ]);

        $bdhUser = User::factory()->create(['name' => 'Khairul Khitam', 'signature_type' => 'draw', 'signature_data' => $dummyPng]);
        $bdhUser->assignRole('Manager');
        Employee::create([
            'user_id' => $bdhUser->id,
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'position_id' => $bdhPos->id,
            'identity_number' => '125000005',
            'name' => 'Khairul Khitam',
        ]);

        // Manager Jakarta & Bojonegara
        $mgrJktUser = User::factory()->create(['name' => 'M Nuramzan Iftari', 'signature_type' => 'draw', 'signature_data' => $dummyPng]);
        $mgrJktUser->assignRole('Manager');
        $mgrJktEmp = Employee::create([
            'user_id' => $mgrJktUser->id,
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'manager_id' => $divEmp->id,
            'identity_number' => 'PGN-002',
            'name' => 'M Nuramzan Iftari',
        ]);

        $mgrBjnUser = User::factory()->create(['name' => 'Mahar Prasetyo', 'signature_type' => 'draw', 'signature_data' => $dummyPng]);
        $mgrBjnUser->assignRole('Manager');
        $mgrBjnEmp = Employee::create([
            'user_id' => $mgrBjnUser->id,
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'manager_id' => $divEmp->id,
            'identity_number' => 'NO-20001',
            'name' => 'Mahar Prasetyo',
        ]);

        $areaJkt = \App\Models\WorkArea::create([
            'department_id' => $dept->id,
            'code' => 'JKT',
            'name' => 'Jakarta',
            'coordinator_employee_id' => $mgrJktEmp->id,
        ]);
        $areaBjn = \App\Models\WorkArea::create([
            'department_id' => $dept->id,
            'code' => 'BJN',
            'name' => 'Bojonegara',
            'coordinator_employee_id' => $mgrBjnEmp->id,
        ]);
        $mgrJktEmp->update(['work_area_id' => $areaJkt->id]);
        $mgrBjnEmp->update(['work_area_id' => $areaBjn->id]);

        // Supervisor Jakarta (Area JKT punya SPV, Area BJN TIDAK punya SPV)
        $spvJktUser = User::factory()->create(['name' => 'Didiet Pradityo', 'signature_type' => 'draw', 'signature_data' => $dummyPng]);
        $spvJktUser->assignRole('Supervisor');
        $spvJktEmp = Employee::create([
            'user_id' => $spvJktUser->id,
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaJkt->id,
            'position_id' => $spvPos->id,
            'manager_id' => $mgrJktEmp->id,
            'identity_number' => 'PGN-004',
            'name' => 'Didiet Pradityo',
        ]);

        // HR Jakarta & HR Bojonegara
        $hrJktUser = User::factory()->create(['name' => 'Maria Ulfah']);
        $hrJktUser->assignRole('HR');
        $hrJktEmp = Employee::create([
            'user_id' => $hrJktUser->id,
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaJkt->id,
            'position_id' => $stfPos->id,
            'manager_id' => $spvJktEmp->id,
            'identity_number' => 'HR-JKT',
            'name' => 'Maria Ulfah',
        ]);

        $hrBjnUser = User::factory()->create(['name' => 'HR Bojonegara']);
        $hrBjnUser->assignRole('HR');
        $hrBjnEmp = Employee::create([
            'user_id' => $hrBjnUser->id,
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaBjn->id,
            'position_id' => $stfPos->id,
            'manager_id' => $mgrBjnEmp->id,
            'identity_number' => 'HR-BJN',
            'name' => 'HR Bojonegara',
        ]);

        $staffJkt = Employee::create([
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaJkt->id,
            'position_id' => $stfPos->id,
            'manager_id' => $spvJktEmp->id,
            'identity_number' => 'STF-JKT',
            'name' => 'Chairunnisa',
        ]);

        $staffBjn = Employee::create([
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaBjn->id,
            'position_id' => $stfPos->id,
            'manager_id' => $mgrBjnEmp->id,
            'identity_number' => 'STF-BJN',
            'name' => 'Diah Nur Cahyani',
        ]);

        // 1. Verifikasi format nomor dokumen 0000/MESI/KODEAREA/BULAN/TAHUN & isolasi data HR antar area
        $memoJkt = \App\Models\Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $hrJktEmp->id,
            'to_recipient' => 'Manager Jakarta',
            'agenda' => 'Dinas Area Jakarta',
            'location' => 'Bandung',
            'date' => '2026-11-10',
            'assignment_start_date' => '2026-11-10',
            'assignment_end_date' => '2026-11-12',
            'content' => 'Dinas JKT',
            'participants' => [$staffJkt->id],
            'status' => 'approved',
        ]);
        $memoBjn = \App\Models\Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $hrBjnEmp->id,
            'to_recipient' => 'Manager Bojonegara',
            'agenda' => 'Dinas Area Bojonegara',
            'location' => 'Cilegon',
            'date' => '2026-11-11',
            'assignment_start_date' => '2026-11-11',
            'assignment_end_date' => '2026-11-13',
            'content' => 'Dinas BJN',
            'participants' => [$staffBjn->id],
            'status' => 'approved',
        ]);

        $this->assertMatchesRegularExpression('#^\d{4}/MESI/JKT/\d{2}/\d{4}$#', (string) $memoJkt->nomor_memo);
        $this->assertMatchesRegularExpression('#^\d{4}/MESI/BJN/\d{2}/\d{4}$#', (string) $memoBjn->nomor_memo);

        $sppdJkt = Sppd::where('employee_id', $staffJkt->id)->firstOrFail();
        $sppdBjn = Sppd::where('employee_id', $staffBjn->id)->firstOrFail();

        // Area JKT memiliki SPV -> SPPD melalui 2 step (SPV -> Manager), dan cetakan SPPD menampilkan "Mengetahui,"
        $sppdJkt->update(['status' => 'pending']);
        $reqSppdJkt = $sppdJkt->approvalRequests()->firstOrFail();
        $this->assertCount(2, $reqSppdJkt->steps);
        (new \App\Services\Approval\ApprovalResolver())->approve($reqSppdJkt, $spvJktEmp, 'OK SPV JKT');
        (new \App\Services\Approval\ApprovalResolver())->approve($reqSppdJkt->fresh(), $mgrJktEmp, 'OK MGR JKT');
        $sppdJkt->refresh();
        $this->assertMatchesRegularExpression('#^\d{4}/MESI/JKT/\d{2}/\d{4}$#', (string) $sppdJkt->sppd_number);

        $jktPrintHtml = view('sppds.print', [
            'sppd' => $sppdJkt,
            'signer' => $mgrJktEmp,
            'signerName' => $mgrJktEmp->name,
            'signerPosition' => $mgrJktEmp->position->name,
        ])->render();
        $this->assertStringContainsString('Mengetahui,', $jktPrintHtml);
        $this->assertStringContainsString('Didiet Pradityo', $jktPrintHtml);

        // Area BJN TIDAK memiliki SPV -> SPPD langsung 1 step ke Manager BJN, dan cetakan SPPD TIDAK menampilkan "Mengetahui,"
        $sppdBjn->update(['status' => 'pending']);
        $reqSppdBjn = $sppdBjn->approvalRequests()->firstOrFail();
        $this->assertCount(1, $reqSppdBjn->steps);
        (new \App\Services\Approval\ApprovalResolver())->approve($reqSppdBjn, $mgrBjnEmp, 'OK MGR BJN');
        $sppdBjn->refresh();
        $this->assertMatchesRegularExpression('#^\d{4}/MESI/BJN/\d{2}/\d{4}$#', (string) $sppdBjn->sppd_number);

        $bjnPrintHtml = view('sppds.print', [
            'sppd' => $sppdBjn,
            'signer' => $mgrBjnEmp,
            'signerName' => $mgrBjnEmp->name,
            'signerPosition' => $mgrBjnEmp->position->name,
        ])->render();
        $this->assertStringNotContainsString('Mengetahui,', $bjnPrintHtml);

        // 2. Isolasi data HR: HR JKT tidak bisa melihat Memo, SPPD, atau Biaya SPPD milik Area BJN
        $this->actingAs($hrJktUser);
        $this->assertTrue(\App\Filament\Admin\Resources\MemoResource::canView($memoJkt));
        $this->assertFalse(\App\Filament\Admin\Resources\MemoResource::canView($memoBjn));
        $this->assertTrue(\App\Filament\Admin\Resources\SppdResource::canView($sppdJkt));
        $this->assertFalse(\App\Filament\Admin\Resources\SppdResource::canView($sppdBjn));
        $this->assertSame([$sppdJkt->id], \App\Filament\Admin\Resources\SppdCostResource::getEloquentQuery()->pluck('id')->all());

        // 3. Biaya SPPD disetujui di masing-masing area -> Sinkronisasi per HR area masuk ke 1 Rekap Perjalanan Dinas yang sama
        $costJkt = SppdCost::create([
            'sppd_id' => $sppdJkt->id,
            'creator_id' => $hrJktUser->id,
            'official_days' => 3,
            'total_cost' => 1500000,
            'status' => 'approved',
        ]);
        $costBjn = SppdCost::create([
            'sppd_id' => $sppdBjn->id,
            'creator_id' => $hrBjnUser->id,
            'official_days' => 3,
            'total_cost' => 2000000,
            'status' => 'approved',
        ]);

        // HR Jakarta sinkronisasi bulan November 2026 -> hanya menyinkronkan pegawai Jakarta
        $recapAfterJkt = OfficialTravelRecap::syncForMonth(11, 2026, $hrJktUser->id, $areaJkt->id);
        $this->assertNotNull($recapAfterJkt);
        $this->assertMatchesRegularExpression('#^\d{4}/MESI/JKT/\d{2}/\d{4}$#', (string) $recapAfterJkt->recap_number);
        $this->assertCount(1, $recapAfterJkt->items);

        // HR Bojonegara belum melihat rekap sebelum ada data Bojonegara di dalamnya
        $this->actingAs($hrBjnUser);
        $this->assertFalse(OfficialTravelRecapResource::canView($recapAfterJkt));

        // HR Bojonegara sinkronisasi bulan November 2026 -> tergabung ke 1 dokumen rekap yang sama tanpa menghapus item Jakarta
        $recapAfterBjn = OfficialTravelRecap::syncForMonth(11, 2026, $hrBjnUser->id, $areaBjn->id);
        $this->assertSame($recapAfterJkt->id, $recapAfterBjn->id);
        $this->assertCount(2, $recapAfterBjn->items()->get());
        $this->assertEquals(3500000.0, (float) $recapAfterBjn->fresh()->total_work_cost);

        // 4. Manajer seluruh area dapat melihat seluruh rekap, dan ke-2 Manajer area wajib menyetujui sebelum lanjut ke BDH & Division Head PGN
        $approvalReq = $recapAfterBjn->ensureApprovalRequestExists($hrJktEmp);
        // 2 Manager steps (JKT + BJN) + 1 BDH step + 1 Division Head step = 4 steps
        $this->assertCount(4, $approvalReq->steps);

        $this->actingAs($mgrJktUser);
        $this->assertContains($approvalReq->id, OfficialTravelApprovalResource::getEloquentQuery()->pluck('id')->all());
        (new \App\Services\Approval\ApprovalResolver())->approve($approvalReq, $mgrJktEmp, 'Disetujui Manager JKT');

        // Manager JKT tidak boleh menyetujui step Manager ke-2 (milik Manager BJN)
        $this->assertFalse((new \App\Services\Approval\ApprovalResolver())->canApprove($approvalReq->fresh(), $mgrJktEmp));
        $this->assertTrue((new \App\Services\Approval\ApprovalResolver())->canApprove($approvalReq->fresh(), $mgrBjnEmp));

        $this->actingAs($mgrBjnUser);
        $this->assertContains($approvalReq->id, OfficialTravelApprovalResource::getEloquentQuery()->pluck('id')->all());
        (new \App\Services\Approval\ApprovalResolver())->approve($approvalReq->fresh(), $mgrBjnEmp, 'Disetujui Manager BJN');

        // Lanjut ke Business Division Head Perkasa lalu Division Head PGN
        (new \App\Services\Approval\ApprovalResolver())->approve($approvalReq->fresh(), $bdhUser->employee, 'Disetujui BDH Perkasa');
        (new \App\Services\Approval\ApprovalResolver())->approve($approvalReq->fresh(), $divEmp, 'Disetujui Division Head PGN');

        $this->assertSame('approved', $approvalReq->fresh()->status);

        // Pastikan tanda tangan kedua Manajer area tercatat terpisah dan tampil di PDF
        $this->assertDatabaseHas('document_signatures', [
            'document_type' => OfficialTravelRecap::class,
            'document_id' => $recapAfterBjn->id,
            'employee_id' => $mgrJktEmp->id,
            'role_type' => 'manager',
        ]);
        $this->assertDatabaseHas('document_signatures', [
            'document_type' => OfficialTravelRecap::class,
            'document_id' => $recapAfterBjn->id,
            'employee_id' => $mgrBjnEmp->id,
            'role_type' => 'manager',
        ]);

        $pdfResponse = $this->actingAs($divUser)->get(route('recaps.travel.print', $recapAfterBjn));
        $pdfResponse->assertSuccessful();
    }
}
