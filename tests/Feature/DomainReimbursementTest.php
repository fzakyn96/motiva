<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ReimbursementApprovalResource\Pages\ListReimbursementApprovals;
use App\Filament\Admin\Resources\ReimbursementResource\Pages\CreateReimbursement;
use App\Filament\Admin\Resources\ReimbursementResource\Pages\EditReimbursement;
use App\Filament\Admin\Resources\ReimbursementResource\Pages\ListReimbursements;
use App\Filament\Admin\Resources\ReimbursementResource\Pages\ReviewReimbursement;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use App\Models\ApprovalRequest;
use App\Models\BusinessTrip;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Memo;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Reimbursement;
use App\Models\Sppd;
use App\Models\User;
use App\Services\Approval\Resolvers\ManagerAuthorityResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DomainReimbursementTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected Department $dept;
    protected Position $managerPos;
    protected Position $staffPos;
    protected User $managerUser;
    protected Employee $manager;
    protected User $staffUser;
    protected Employee $staff;
    protected User $hrUser;
    protected Employee $hrEmployee;
    protected Sppd $approvedSppd;

    protected function setUp(): void
    {
        parent::setUp();

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $this->dept = Department::create(['organization_id' => $this->org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $this->managerPos = Position::create(['name' => 'Manager MESI', 'code' => 'MGR', 'level' => 1]);
        $this->staffPos = Position::create(['name' => 'Staff Lapangan', 'code' => 'STF', 'level' => 3]);

        // Manager
        $this->managerUser = User::factory()->create([
            'name' => 'Pak Manajer',
            'signature_type' => 'draw',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'signature_updated_at' => now(),
        ]);
        $this->managerUser->assignRole('Manager');
        $this->manager = Employee::create([
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->managerPos->id,
            'user_id' => $this->managerUser->id,
            'name' => 'Pak Manajer',
            'identity_number' => 'MGR-001',
        ]);

        // Staff (Traveler)
        $this->staffUser = User::factory()->create(['name' => 'Budi Staff']);
        $this->staffUser->assignRole('Staff');
        $this->staff = Employee::create([
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->staffPos->id,
            'user_id' => $this->staffUser->id,
            'manager_id' => $this->manager->id,
            'name' => 'Budi Staff',
            'identity_number' => 'STF-001',
        ]);

        // HR User
        $this->hrUser = User::factory()->create(['name' => 'Ibu HR']);
        $this->hrUser->assignRole('HR');
        $this->hrEmployee = Employee::create([
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->staffPos->id,
            'user_id' => $this->hrUser->id,
            'name' => 'Ibu HR',
            'identity_number' => 'HR-001',
        ]);

        // Approved SPPD
        $trip = BusinessTrip::create([
            'activity_name' => 'Kunjungan Lapangan Surabaya',
            'destination' => 'Surabaya',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-04',
        ]);

        $this->approvedSppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $this->staff->id,
            'sppd_number' => '001/SPPD/MESI/11/2026',
            'status' => 'approved',
        ]);
    }

    public function test_staff_can_create_reimbursement_with_expenses_and_receipt_proofs(): void
    {
        Storage::fake('public');

        $this->actingAs($this->staffUser);

        $reimbursement = Reimbursement::create([
            'employee_id' => $this->staff->id,
            'sppd_id' => $this->approvedSppd->id,
            'title' => 'Reimbursement BBM & Tol Dinas Surabaya',
            'notes' => 'Kuitansi BBM tol Surabaya dan parkir terlampir.',
            'status' => 'draft',
        ]);

        // Expense 1: Klaim pribadi (is_company_paid = false)
        $expense1 = Expense::create([
            'reimbursement_id' => $reimbursement->id,
            'expense_type' => 'Bahan Bakar & Tol',
            'transaction_date' => '2026-11-02',
            'description' => 'Bensin Pertamax Tol Cipali',
            'amount' => 350000,
            'is_company_paid' => false,
            'receipt_path' => 'reimbursement-receipts/kwitansi_bbm.jpg',
        ]);

        // Expense 2: Ditalangi kantor (is_company_paid = true)
        $expense2 = Expense::create([
            'reimbursement_id' => $reimbursement->id,
            'expense_type' => 'Tiket Transportasi',
            'transaction_date' => '2026-11-01',
            'description' => 'Tiket Kereta Argo Bromo (Kantor)',
            'amount' => 600000,
            'is_company_paid' => true,
            'receipt_path' => 'reimbursement-receipts/tiket_kereta.pdf',
        ]);

        // Hitung ulang total
        $reimbursement->recalculateTotals();

        $this->assertDatabaseHas('reimbursements', [
            'id' => $reimbursement->id,
            'employee_id' => $this->staff->id,
            'sppd_id' => $this->approvedSppd->id,
            'total_amount' => 950000, // 350rb + 600rb
            'total_claim_amount' => 350000, // Hanya yang dibayar pegawai
            'status' => 'draft',
        ]);

        $this->assertNotNull($reimbursement->reimbursement_number);
        $this->assertEquals(2, $reimbursement->expenses()->count());
        $this->assertFalse($expense1->is_company_paid);
        $this->assertTrue($expense2->is_company_paid);
        $this->assertTrue($expense2->isPdfReceipt());
    }

    public function test_hr_can_create_reimbursement_on_behalf_of_employee(): void
    {
        $this->actingAs($this->hrUser);

        // HR menginputkan reimbursement untuk Budi Staff
        Livewire::test(CreateReimbursement::class)
            ->fillForm([
                'employee_id' => $this->staff->id,
                'sppd_id' => $this->approvedSppd->id,
                'title' => 'Input Reimbursement oleh HR untuk Budi',
                'notes' => 'Dibantu penginputan oleh HR.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('reimbursements', [
            'employee_id' => $this->staff->id,
            'sppd_id' => $this->approvedSppd->id,
            'title' => 'Input Reimbursement oleh HR untuk Budi',
            'status' => 'draft',
        ]);
    }

    public function test_submitting_reimbursement_creates_approval_request_with_manager_resolver(): void
    {
        $this->actingAs($this->staffUser);

        $reimbursement = Reimbursement::create([
            'employee_id' => $this->staff->id,
            'sppd_id' => $this->approvedSppd->id,
            'title' => 'Klaim Transport Lokal',
            'status' => 'draft',
        ]);

        Expense::create([
            'reimbursement_id' => $reimbursement->id,
            'expense_type' => 'Transportasi Lokal',
            'amount' => 250000,
            'is_company_paid' => false,
            'receipt_path' => 'reimbursement-receipts/taksi.jpg',
        ]);

        // Simulasikan submit ke Manajer (melalui action table atau model update)
        $reimbursement->recalculateTotals();
        $reimbursement->update(['status' => 'pending']);

        // Observer otomatis membuatkan ApprovalRequest
        $approvalRequest = ApprovalRequest::where('document_type', Reimbursement::class)
            ->where('document_id', $reimbursement->id)
            ->first();

        $this->assertNotNull($approvalRequest);
        $this->assertEquals('pending', $approvalRequest->status);
        $this->assertEquals($this->staff->id, $approvalRequest->requester_employee_id);

        // Memastikan step 1 diset untuk ManagerAuthorityResolver
        $currentStep = $approvalRequest->currentStep();
        $this->assertNotNull($currentStep);
        $this->assertEquals(ManagerAuthorityResolver::class, $currentStep->policyStep->resolver_class);
    }

    public function test_manager_can_review_and_approve_reimbursement(): void
    {
        $reimbursement = Reimbursement::create([
            'employee_id' => $this->staff->id,
            'sppd_id' => $this->approvedSppd->id,
            'title' => 'Klaim Hotel Tambahan',
            'status' => 'pending',
        ]);

        Expense::create([
            'reimbursement_id' => $reimbursement->id,
            'expense_type' => 'Penginapan / Hotel',
            'amount' => 500000,
            'is_company_paid' => false,
            'receipt_path' => 'reimbursement-receipts/hotel.pdf',
        ]);

        $reimbursement->recalculateTotals();

        // Login sebagai Manajer
        $this->actingAs($this->managerUser);

        Livewire::test(ReviewReimbursement::class, ['record' => $reimbursement->getKey()])
            ->assertSuccessful()
            ->assertSee('Klaim Hotel Tambahan')
            ->assertSee('Budi Staff')
            ->call('approveReimbursement', 'Disetujui penuh oleh Manajer');

        $reimbursement->refresh();
        $this->assertEquals('approved', $reimbursement->status);
        $this->assertNotNull($reimbursement->approved_at);

        $approvalRequest = ApprovalRequest::where('document_type', Reimbursement::class)
            ->where('document_id', $reimbursement->id)
            ->first();
        $this->assertEquals('approved', $approvalRequest->status);
    }

    public function test_manager_can_reject_reimbursement_with_note(): void
    {
        $reimbursement = Reimbursement::create([
            'employee_id' => $this->staff->id,
            'sppd_id' => $this->approvedSppd->id,
            'title' => 'Klaim Makan Mewah',
            'status' => 'pending',
        ]);

        Expense::create([
            'reimbursement_id' => $reimbursement->id,
            'expense_type' => 'Konsumsi / Makan',
            'amount' => 1500000,
            'is_company_paid' => false,
            'receipt_path' => 'reimbursement-receipts/resto.jpg',
        ]);

        $this->actingAs($this->managerUser);

        Livewire::test(ReviewReimbursement::class, ['record' => $reimbursement->getKey()])
            ->assertSuccessful()
            ->call('rejectReimbursement', 'Nominal konsumsi melebihi plafon standar ST-JTK-05.');

        $reimbursement->refresh();
        $this->assertEquals('rejected', $reimbursement->status);
        $this->assertNotNull($reimbursement->rejected_at);
        $this->assertEquals('Nominal konsumsi melebihi plafon standar ST-JTK-05.', $reimbursement->rejection_note);
    }

    public function test_unauthorized_staff_cannot_approve_reimbursement(): void
    {
        $reimbursement = Reimbursement::create([
            'employee_id' => $this->staff->id,
            'sppd_id' => $this->approvedSppd->id,
            'title' => 'Klaim Sendiri',
            'status' => 'pending',
        ]);

        // Staff login dan mencoba approve miliknya sendiri
        $this->actingAs($this->staffUser);

        $page = new ReviewReimbursement();
        $page->record = $reimbursement;

        // cannot perform approval
        $this->assertFalse($page->canPerformApproval());
    }

    public function test_reimbursement_print_pdf_streams_successfully(): void
    {
        $reimbursement = Reimbursement::create([
            'employee_id' => $this->staff->id,
            'sppd_id' => $this->approvedSppd->id,
            'title' => 'Cetak Formulir Reimbursement',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        Expense::create([
            'reimbursement_id' => $reimbursement->id,
            'expense_type' => 'Bahan Bakar & Tol',
            'amount' => 200000,
            'is_company_paid' => false,
        ]);

        $reimbursement->recalculateTotals();

        $this->actingAs($this->staffUser);

        $response = $this->get(route('reimbursements.print', $reimbursement));
        $response->assertSuccessful();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_reimbursement_approval_list_page_renders_for_manager(): void
    {
        $reimbursement = Reimbursement::create([
            'employee_id' => $this->staff->id,
            'sppd_id' => $this->approvedSppd->id,
            'title' => 'Pengajuan Antrian Persetujuan',
            'status' => 'pending',
        ]);

        $this->actingAs($this->managerUser);

        Livewire::test(ListReimbursementApprovals::class)
            ->assertSuccessful()
            ->assertSee('Pengajuan Antrian Persetujuan');
    }
}
