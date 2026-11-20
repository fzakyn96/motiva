<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Widgets\DashboardWelcomeBannerWidget;
use App\Filament\Admin\Widgets\ExecutiveCostSummaryWidget;
use App\Filament\Admin\Widgets\ExecutiveOverviewWidget;
use App\Filament\Admin\Widgets\ExecutivePendingApprovalsWidget;
use App\Filament\Admin\Widgets\ExecutivePipelineChartWidget;
use App\Filament\Admin\Widgets\ExecutiveRecentTripsWidget;
use App\Filament\Admin\Widgets\StaffOverviewWidget;
use App\Filament\Admin\Widgets\StaffRecentTripsWidget;
use App\Models\BusinessTrip;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Memo;
use App\Models\OfficialTravelRecap;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Sppd;
use App\Models\SppdCost;
use App\Models\TransportAccommodationRecap;
use App\Models\TransportAccommodationRecapItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExecutiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected Department $dept;
    protected Position $managerPos;
    protected Position $supervisorPos;
    protected Position $staffPos;
    protected User $managerUser;
    protected Employee $manager;
    protected User $supervisorUser;
    protected Employee $supervisor;
    protected User $staffUser;
    protected Employee $staff;

    protected function setUp(): void
    {
        parent::setUp();

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $this->dept = Department::create(['organization_id' => $this->org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $this->managerPos = Position::create(['name' => 'Manager MESI', 'code' => 'MGR', 'level' => 1]);
        $this->supervisorPos = Position::create(['name' => 'Supervisor MESI', 'code' => 'SPV', 'level' => 2]);
        $this->staffPos = Position::create(['name' => 'Staff Lapangan', 'code' => 'STF', 'level' => 3]);

        // Manager
        $this->managerUser = User::factory()->create(['name' => 'Bapak Manager']);
        $this->managerUser->assignRole('Manager');
        $this->manager = Employee::create([
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->managerPos->id,
            'user_id' => $this->managerUser->id,
            'name' => 'Bapak Manager',
            'identity_number' => 'MGR-001',
        ]);

        // Supervisor
        $this->supervisorUser = User::factory()->create(['name' => 'Bapak Supervisor']);
        $this->supervisorUser->assignRole('Supervisor');
        $this->supervisor = Employee::create([
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->supervisorPos->id,
            'user_id' => $this->supervisorUser->id,
            'manager_id' => $this->manager->id,
            'name' => 'Bapak Supervisor',
            'identity_number' => 'SPV-001',
        ]);

        // Staff
        $this->staffUser = User::factory()->create(['name' => 'Rian Traveler']);
        $this->staffUser->assignRole('Staff');
        $this->staff = Employee::create([
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->staffPos->id,
            'user_id' => $this->staffUser->id,
            'manager_id' => $this->supervisor->id,
            'name' => 'Rian Traveler',
            'identity_number' => 'STF-001',
        ]);
    }

    public function test_manager_can_render_executive_dashboard_and_widgets(): void
    {
        $this->actingAs($this->managerUser);

        Livewire::test(Dashboard::class)
            ->assertSuccessful();

        Livewire::test(DashboardWelcomeBannerWidget::class)
            ->assertSuccessful()
            ->assertSee('Persetujuan Biaya SPPD');

        Livewire::test(ExecutiveOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Antrian Persetujuan')
            ->assertSee('Perjalanan Dinas (SPPD)')
            ->assertSee('Realisasi Biaya SPPD')
            ->assertSee('Rekap Tiket & Akomodasi')
            ->assertSee('Rekap Perjalanan Dinas');

        Livewire::test(ExecutivePendingApprovalsWidget::class)
            ->assertSuccessful();

        Livewire::test(ExecutiveCostSummaryWidget::class)
            ->assertSuccessful();

        Livewire::test(ExecutivePipelineChartWidget::class)
            ->assertSuccessful();

        Livewire::test(DashboardWelcomeBannerWidget::class)
            ->assertSuccessful()
            ->assertSee('Bapak Manager');

        Livewire::test(ExecutiveRecentTripsWidget::class)
            ->assertSuccessful();
    }

    public function test_staff_can_render_personal_dashboard_and_widgets(): void
    {
        $this->staffUser->update([
            'signature_type' => 'draw',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'signature_updated_at' => now(),
        ]);

        $this->actingAs($this->staffUser);

        Livewire::test(Dashboard::class)
            ->assertSuccessful();

        Livewire::test(DashboardWelcomeBannerWidget::class)
            ->assertSuccessful()
            ->assertSee('Rian Traveler');

        Livewire::test(StaffOverviewWidget::class)
            ->assertSuccessful()
            ->assertDontSee('Memo Penugasan Saya')
            ->assertSee('Total SPPD Saya')
            ->assertSee('SPPD Disetujui')
            ->assertDontSee('Klaim Reimbursement Saya')
            ->assertSee('Tanda Tangan Digital')
            ->assertSee('Metode: Gambar Langsung');

        Livewire::test(StaffRecentTripsWidget::class)
            ->assertSuccessful();
    }

    public function test_supervisor_can_render_executive_dashboard_and_widgets(): void
    {
        $this->actingAs($this->supervisorUser);

        Livewire::test(Dashboard::class)
            ->assertSuccessful();

        Livewire::test(ExecutiveOverviewWidget::class)
            ->assertSuccessful();

        Livewire::test(ExecutivePendingApprovalsWidget::class)
            ->assertSuccessful();
    }

    public function test_hr_can_render_executive_dashboard_and_widgets(): void
    {
        $hrUser = User::factory()->create(['name' => 'Ibu HRD']);
        $hrUser->assignRole('HR');

        $this->actingAs($hrUser);

        Livewire::test(Dashboard::class)
            ->assertSuccessful();

        Livewire::test(DashboardWelcomeBannerWidget::class)
            ->assertSuccessful()
            ->assertSee('Ibu HRD')
            ->assertSee('Hitung Biaya SPPD');

        Livewire::test(ExecutiveOverviewWidget::class)
            ->assertSuccessful();

        Livewire::test(ExecutivePipelineChartWidget::class)
            ->assertSuccessful();

        Livewire::test(ExecutiveRecentTripsWidget::class)
            ->assertSuccessful();
    }

    public function test_executive_dashboard_reflects_full_pipeline_metrics(): void
    {
        $this->actingAs($this->managerUser);

        // 1. Memo Pending
        $memo = Memo::create([
            'creator_employee_id' => $this->staff->id,
            'department_id' => $this->dept->id,
            'title' => 'Memo Proyek Cilegon',
            'content' => 'Rincian penugasan dinas proyek di Cilegon.',
            'date' => '2026-11-01',
            'status' => 'pending',
            'total_travelers' => 2,
        ]);

        // 2. Business Trip & SPPD Approved with Cost
        $trip = BusinessTrip::create([
            'memo_id' => $memo->id,
            'activity_name' => 'Site Commissioning Cilegon',
            'destination' => 'Cilegon',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-05',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $this->staff->id,
            'sppd_number' => '001/SPPD/MESI/11/2026',
            'status' => 'approved',
            'total_days' => 4,
        ]);

        SppdCost::create([
            'sppd_id' => $sppd->id,
            'base_type' => 'office_base_stay',
            'official_days' => 4,
            'subtotal_transport' => 500000,
            'subtotal_per_diem' => 1500000,
            'subtotal_other' => 200000,
            'total_cost' => 2200000,
        ]);

        // 3. Rekap Tiket & Akomodasi Pending Approval
        $recap = TransportAccommodationRecap::create([
            'department_id' => $this->dept->id,
            'business_trip_id' => $trip->id,
            'business_trip_ids' => [$trip->id],
            'work_name' => 'Rekap Tiket Cilegon',
            'period_label' => 'November 2026',
            'period_month' => '2026-11',
            'grouping_mode' => 'combined',
            'creator_id' => $this->managerUser->id,
        ]);
        TransportAccommodationRecapItem::create([
            'transport_accommodation_recap_id' => $recap->id,
            'sppd_id' => $sppd->id,
            'sppd_ids' => [$sppd->id],
            'item_category' => 'transport',
            'description' => 'Tiket Cilegon',
            'amount' => 1500000,
            'service_fee' => 150000,
            'total_amount' => 1650000,
        ]);
        $recap->recalculateTotals();
        $recap->ensureApprovalRequestExists($this->staff);

        // Verifikasi Overview Widget
        Livewire::test(ExecutiveOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Rp 2.200.000') // Biaya SPPD
            ->assertSee('Rp 1.732.500'); // Grand Total Rekap (1.650.000 + 5% manfee 82.500)

        // Verifikasi Pending Approvals Table Widget
        Livewire::test(ExecutivePendingApprovalsWidget::class)
            ->assertSuccessful()
            ->assertDontSee('Nilai Biaya / Klaim')
            ->assertSee('Memo Proyek Cilegon')
            ->assertSee('Rekap Tiket Cilegon')
            ->assertSee('Rian Traveler');

        // Verifikasi Recent Trips Table Widget
        Livewire::test(ExecutiveRecentTripsWidget::class)
            ->assertSuccessful()
            ->assertSee('001/SPPD/MESI/11/2026')
            ->assertSee('Cilegon')
            ->assertSee('Rp 2.200.000');
    }

    public function test_division_head_can_render_executive_dashboard_and_widgets(): void
    {
        $divUser = User::factory()->create(['name' => 'Herry Division Head']);
        $divUser->assignRole('Division Head');
        Employee::create([
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->managerPos->id,
            'user_id' => $divUser->id,
            'name' => 'Herry Division Head',
            'identity_number' => 'DIV-999',
        ]);

        $this->actingAs($divUser);

        Livewire::test(Dashboard::class)
            ->assertSuccessful();

        Livewire::test(DashboardWelcomeBannerWidget::class)
            ->assertSuccessful()
            ->assertSee('Herry Division Head')
            ->assertSee('Persetujuan Rekap Tiket')
            ->assertSee('Persetujuan Rekap Dinas');

        Livewire::test(ExecutiveOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Antrian Persetujuan')
            ->assertSee('Rekap Tiket & Akomodasi')
            ->assertSee('Rekap Perjalanan Dinas')
            ->assertDontSee(\App\Filament\Admin\Resources\SppdApprovalResource::getUrl('index'))
            ->assertDontSee(\App\Filament\Admin\Resources\SppdCostApprovalResource::getUrl('index'));

        Livewire::test(ExecutiveCostSummaryWidget::class)->assertSuccessful();
        Livewire::test(ExecutivePipelineChartWidget::class)->assertSuccessful();
        Livewire::test(ExecutivePendingApprovalsWidget::class)->assertSuccessful();
        Livewire::test(ExecutiveRecentTripsWidget::class)->assertSuccessful();
    }

    public function test_superadmin_has_exclusive_full_access_and_complete_dashboard(): void
    {
        $superUser = User::factory()->create(['name' => 'Superuser Motiva']);
        $superUser->assignRole('Superadmin');

        $this->actingAs($superUser);

        $this->assertTrue(\App\Filament\Admin\Resources\MemoResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\SppdResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\SppdCostResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\SppdRateStandardResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\TransportAccommodationRecapResource::shouldRegisterNavigation());
        $this->assertTrue(\App\Filament\Admin\Resources\OfficialTravelRecapResource::shouldRegisterNavigation());
        $this->assertTrue(\App\Filament\Admin\Resources\MemoApprovalResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\SppdApprovalResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\SppdCostApprovalResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\TransportAccommodationApprovalResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\OfficialTravelApprovalResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Clusters\DataPegawai::canAccess());
        $this->assertTrue(\App\Filament\Admin\Clusters\HakAkses::canAccess());
        $this->assertTrue(\App\Filament\Admin\Pages\SppdSettingsPage::canAccess());
        $this->assertTrue(\App\Filament\Admin\Pages\ReimbursementSettingsPage::canAccess());
        $this->assertTrue(\App\Filament\Admin\Pages\SignatureSettingsPage::canAccess());

        Livewire::test(Dashboard::class)->assertSuccessful();

        Livewire::test(DashboardWelcomeBannerWidget::class)
            ->assertSuccessful()
            ->assertSee('Buat Memo Dinas')
            ->assertSee('Hitung Biaya SPPD')
            ->assertSee('Rekap Tiket & Akomodasi')
            ->assertSee('Rekap Perjalanan Dinas')
            ->assertSee('Persetujuan Memo')
            ->assertSee('Persetujuan SPPD')
            ->assertSee('Persetujuan Biaya SPPD')
            ->assertSee('Persetujuan Rekap Tiket')
            ->assertSee('Persetujuan Rekap Dinas')
            ->assertSee('Pengaturan SPPD')
            ->assertSee('Pengaturan Fee Rekap');

        Livewire::test(ExecutiveOverviewWidget::class)->assertSuccessful();
        Livewire::test(ExecutiveCostSummaryWidget::class)->assertSuccessful();
        Livewire::test(ExecutivePipelineChartWidget::class)->assertSuccessful();
        Livewire::test(ExecutivePendingApprovalsWidget::class)->assertSuccessful();
        Livewire::test(ExecutiveRecentTripsWidget::class)->assertSuccessful();
    }
}
