<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\SignatureSettingsPage;
use App\Filament\Admin\Resources\MemoResource\Pages\ReviewMemo;
use App\Filament\Admin\Resources\SppdResource\Pages\ReviewSppd;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use App\Models\BusinessTrip;
use App\Models\Department;
use App\Models\DocumentSignature;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Memo;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Reimbursement;
use App\Models\SignatureSetting;
use App\Models\Sppd;
use App\Models\User;
use App\Services\Approval\Resolvers\ManagerAuthorityResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SignatureSystemTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected Department $dept;

    protected Position $managerPos;

    protected Position $staffPos;

    protected User $superadminUser;

    protected User $managerUser;

    protected Employee $managerEmp;

    protected User $staffUser;

    protected Employee $staffEmp;

    protected string $sampleDrawBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $this->dept = Department::create(['organization_id' => $this->org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $this->managerPos = Position::create(['name' => 'Manager MESI', 'code' => 'MGR', 'level' => 1]);
        $this->staffPos = Position::create(['name' => 'Staff MESI', 'code' => 'STF', 'level' => 4]);

        $this->superadminUser = User::factory()->create(['name' => 'Superadmin User']);
        $this->superadminUser->assignRole('Superadmin');

        $this->managerUser = User::factory()->create(['name' => 'Manajer Tanpa TTD']);
        $this->managerUser->assignRole('Manager');
        $this->managerEmp = Employee::create([
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->managerPos->id,
            'user_id' => $this->managerUser->id,
            'name' => 'Manajer Tanpa TTD',
            'identity_number' => 'MGR-SIG-01',
        ]);

        $this->staffUser = User::factory()->create(['name' => 'Staff Pengaju']);
        $this->staffUser->assignRole('Staff');
        $this->staffEmp = Employee::create([
            'organization_id' => $this->org->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->staffPos->id,
            'user_id' => $this->staffUser->id,
            'manager_id' => $this->managerEmp->id,
            'name' => 'Staff Pengaju',
            'identity_number' => 'STF-SIG-01',
        ]);

        $policy = ApprovalPolicy::create([
            'name' => 'Memo Approval Policy',
            'model_type' => Memo::class,
        ]);

        ApprovalPolicyStep::create([
            'approval_policy_id' => $policy->id,
            'step_order' => 1,
            'step_name' => 'Manager Approval',
            'resolver_class' => ManagerAuthorityResolver::class,
        ]);
    }

    public function test_user_can_configure_signature_once_via_draw_or_upload_in_signature_settings_page(): void
    {
        Storage::fake('public');

        $this->assertFalse($this->managerUser->hasConfiguredSignature());

        // 1. User saves drawn signature (goresan)
        Livewire::actingAs($this->managerUser)
            ->test(SignatureSettingsPage::class)
            ->assertSuccessful()
            ->set('signatureType', 'draw')
            ->call('saveUserSignature', $this->sampleDrawBase64);

        $this->managerUser->refresh();
        $this->assertTrue($this->managerUser->hasConfiguredSignature());
        $this->assertEquals('draw', $this->managerUser->signature_type);
        $this->assertEquals($this->sampleDrawBase64, $this->managerUser->getSignatureImageDataUri());

        // 2. User switches to upload signature image
        $fakePng = UploadedFile::fake()->image('ttd.png', 200, 80);

        Livewire::actingAs($this->managerUser)
            ->test(SignatureSettingsPage::class)
            ->set('signatureType', 'upload')
            ->set('uploadedSignatureFile', $fakePng)
            ->call('saveUserSignature')
            ->assertHasNoErrors();

        $this->managerUser->refresh();
        $this->assertTrue($this->managerUser->hasConfiguredSignature());
        $this->assertEquals('upload', $this->managerUser->signature_type);
        $this->assertNotNull($this->managerUser->signature_path);
        $this->assertStringStartsWith('data:image/', (string) $this->managerUser->getSignatureImageDataUri());
    }

    public function test_superadmin_can_configure_system_signature_policy_and_enable_qrcode_mode(): void
    {
        // Non-superadmin cannot save system settings
        Livewire::actingAs($this->managerUser)
            ->test(SignatureSettingsPage::class)
            ->call('saveSystemSettings')
            ->assertForbidden();

        // Superadmin enables QR Code and sets display mode to visual_and_qrcode
        Livewire::actingAs($this->superadminUser)
            ->test(SignatureSettingsPage::class)
            ->set('allowDraw', true)
            ->set('allowUpload', true)
            ->set('allowQrcode', true)
            ->set('documentDisplayMode', 'visual_and_qrcode')
            ->call('saveSystemSettings')
            ->assertSuccessful();

        $setting = SignatureSetting::current();
        $this->assertTrue($setting->allow_qrcode);
        $this->assertEquals('visual_and_qrcode', $setting->document_display_mode);
        $this->assertArrayHasKey('qrcode', $setting->getAllowedMethodsForUser());
    }

    public function test_approver_without_signature_is_blocked_and_reminded_before_approving_memo_sppd_or_reimbursement(): void
    {
        $memo = Memo::create([
            'department_id' => $this->dept->id,
            'creator_employee_id' => $this->staffEmp->id,
            'to_recipient' => 'Manager MESI',
            'agenda' => 'Memo Uji Wajib TTD',
            'content' => 'Isi memo',
            'status' => 'pending',
        ]);

        // Manager has NO signature configured yet
        $this->assertFalse($this->managerUser->hasConfiguredSignature());

        Livewire::actingAs($this->managerUser)
            ->test(ReviewMemo::class, ['record' => $memo->getKey()])
            ->callAction('approve')
            ->assertNotified('Tanda Tangan Belum Diatur');

        // Memo must remain pending because manager has not configured signature
        $memo->refresh();
        $this->assertEquals('pending', $memo->status);
        $this->assertEquals(0, DocumentSignature::count());
    }

    public function test_configured_signature_automatically_stamps_approved_documents_and_verifies_via_public_qr_url(): void
    {
        // Enable both visual signature and QR code in system settings
        SignatureSetting::current()->update([
            'allow_draw' => true,
            'allow_upload' => true,
            'allow_qrcode' => true,
            'document_display_mode' => 'visual_and_qrcode',
        ]);

        // Manager configures signature once
        $this->managerUser->update([
            'signature_type' => 'draw',
            'signature_data' => $this->sampleDrawBase64,
            'signature_updated_at' => now(),
        ]);

        $memo = Memo::create([
            'department_id' => $this->dept->id,
            'creator_employee_id' => $this->staffEmp->id,
            'to_recipient' => 'Manager MESI',
            'agenda' => 'Penugasan Audit Jaringan',
            'content' => 'Isi memo audit jaringan',
            'nomor_memo' => '099/MEMO/MESI/11/2026',
            'date' => '2026-11-11',
            'status' => 'pending',
        ]);

        // Manager approves Memo -> signature is automatically stamped without re-signing
        Livewire::actingAs($this->managerUser)
            ->test(ReviewMemo::class, ['record' => $memo->getKey()])
            ->callAction('approve');

        $memo->refresh();
        $this->assertEquals('approved', $memo->status);

        $docSig = DocumentSignature::where('document_type', Memo::class)
            ->where('document_id', $memo->id)
            ->first();

        $this->assertNotNull($docSig);
        $this->assertEquals('Manajer Tanpa TTD', $docSig->signer_name);
        $this->assertEquals('Manager MESI', $docSig->signer_position);

        // Render Memo Print View -> verify both visual signature and QR code image are stamped in HTML
        $renderedMemoHtml = view('memos.print', [
            'memo' => $memo->load(['creator.position', 'creator.manager.position', 'department']),
            'manager' => $this->managerEmp,
            'managerTitle' => $this->managerPos->name,
        ])->render();

        $this->assertStringContainsString($this->sampleDrawBase64, $renderedMemoHtml);
        $this->assertStringContainsString('alt="QR Verifikasi"', $renderedMemoHtml);

        // Public guest accesses QR verification URL without login
        auth()->logout();
        $verifyResponse = $this->get(route('signatures.verify', ['token' => $docSig->token]));
        $verifyResponse->assertStatus(200);
        $verifyResponse->assertSee('Tanda Tangan Elektronik Terverifikasi');
        $verifyResponse->assertSee('Manajer Tanpa TTD');
        $verifyResponse->assertSee($memo->nomor_memo);
        $verifyResponse->assertSee('Penugasan Audit Jaringan');

        // Invalid token shows failure message
        $invalidResponse = $this->get(route('signatures.verify', ['token' => 'token-palsu-tidak-terdaftar']));
        $invalidResponse->assertStatus(200);
        $invalidResponse->assertSee('Verifikasi Gagal');
    }
}
