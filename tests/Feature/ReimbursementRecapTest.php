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
}
