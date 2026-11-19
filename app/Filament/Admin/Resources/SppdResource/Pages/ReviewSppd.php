<?php

namespace App\Filament\Admin\Resources\SppdResource\Pages;

use App\Filament\Admin\Resources\SppdApprovalResource;
use App\Filament\Admin\Resources\SppdResource;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Sppd;
use App\Services\Approval\ApprovalResolver;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ReviewSppd extends ViewRecord
{
    protected static string $resource = SppdResource::class;

    protected static ?string $title = 'Review Surat Perintah Perjalanan Dinas (SPPD)';

    public static function authorizeResourceAccess(): void
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        abort_unless($user && ($user->hasRole(['Manager', 'Supervisor', 'Admin', 'Superadmin', 'HR']) || static::getResource()::canAccess()), 403);
    }

    public function getBreadcrumbs(): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (request()->query('from') === 'approvals' || $user?->hasAnyRole(['Manager', 'Supervisor']) || str_contains(request()->headers->get('referer', ''), 'sppd-approvals')) {
            return [
                SppdApprovalResource::getUrl('index') => 'Persetujuan SPPD',
                'Review',
            ];
        }

        return parent::getBreadcrumbs();
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);

        /** @var Sppd|null $sppd */
        $sppd = $this->getRecord();
        if ($sppd && $sppd->status === 'pending') {
            (new \App\Observers\SppdObserver())->ensureApprovalRequestExists($sppd);
        }
    }

    public function canPerformApproval(): bool
    {
        /** @var Sppd|null $sppd */
        $sppd = $this->getRecord();

        if (! $sppd || $sppd->status !== 'pending') {
            return false;
        }

        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        // Superadmin and Admin can always approve
        if ($user->hasRole('Superadmin') || $user->hasRole('Admin')) {
            return true;
        }

        $employee = $user->employee;
        if (! $employee) {
            $employee = Employee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $user->name,
                    'identity_number' => 'USR-' . $user->id,
                    'organization_id' => $sppd->employee?->organization_id ?? 1,
                    'department_id' => $sppd->employee?->department_id ?? 1,
                    'position_id' => 1,
                ]
            );
        }

        $request = ApprovalRequest::where('document_type', Sppd::class)
            ->where('document_id', $sppd->id)
            ->first();

        // Self-healing: jika SPPD pending belum memiliki ApprovalRequest di DB, otomatis buatkan sekarang
        if (! $request) {
            $request = (new \App\Observers\SppdObserver())->ensureApprovalRequestExists($sppd);
        }

        if (! $request || $request->status !== 'pending') {
            return false;
        }

        $currentStep = $request->currentStep();
        if (! $currentStep) {
            return false;
        }

        $resolver = new ApprovalResolver();
        return $resolver->canApprove($request, $employee);
    }

    public function getCurrentStepOrder(): ?int
    {
        /** @var Sppd|null $sppd */
        $sppd = $this->getRecord();
        if (! $sppd) {
            return null;
        }

        $request = ApprovalRequest::where('document_type', Sppd::class)
            ->where('document_id', $sppd->id)
            ->first();

        if (! $request && $sppd->status === 'pending') {
            $request = (new \App\Observers\SppdObserver())->ensureApprovalRequestExists($sppd);
        }

        $step = $request?->currentStep();
        return $step && $step->policyStep ? (int) $step->policyStep->step_order : null;
    }

    public function approveSppd(?string $notes = null): void
    {
        if (! $this->canPerformApproval()) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Anda hanya dapat menyetujui SPPD dari area kerja yang sama.')
                ->danger()
                ->send();

            return;
        }

        /** @var Sppd $sppd */
        $sppd = $this->getRecord();
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        $sigService = app(\App\Services\Signature\SignatureService::class);
        if (! $sigService->ensureUserCanSign($user)) {
            return;
        }

        $employee = $user?->employee;

        if (! $employee && $user) {
            $employee = Employee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $user->name,
                    'identity_number' => 'ADM-' . $user->id,
                    'organization_id' => $sppd->employee?->organization_id ?? 1,
                    'department_id' => $sppd->employee?->department_id,
                    'position_id' => 1,
                ]
            );
        }

        $request = ApprovalRequest::where('document_type', Sppd::class)
            ->where('document_id', $sppd->id)
            ->first();

        if (! $request) {
            $sppd->update(['status' => 'approved']);
            $sigService->signDocument($sppd, $employee, 'manager', $notes ?: 'Disetujui melalui Halaman Review', $user);
            Notification::make()->title('SPPD Berhasil Disetujui & Ditandatangani')->success()->send();
            $this->redirect(SppdResource::getUrl('review', ['record' => $sppd]));
            return;
        }

        $currentStep = $request->currentStep();
        $isStep1 = $currentStep && (int) $currentStep->policyStep?->step_order === 1;
        $roleType = $isStep1 ? 'supervisor' : 'manager';

        $resolver = new ApprovalResolver();
        try {
            $resolver->approve($request, $employee, $notes ?: 'Disetujui melalui Halaman Review');

            $sppd->refresh();
            $sigService->signDocument($sppd, $employee, $roleType, $notes ?: 'Disetujui melalui Halaman Review', $user);

            if ($isStep1) {
                Notification::make()
                    ->title('SPPD Disetujui Supervisor')
                    ->body('Tahap 1 selesai dan tanda tangan Anda telah tercatat. SPPD kini diteruskan ke Manajer untuk persetujuan tingkat akhir.')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('SPPD Disetujui Sepenuhnya (Final Approved)')
                    ->body('Persetujuan tingkat akhir dan tanda tangan Manajer telah dibubuhkan. SPPD resmi disetujui.')
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            if ($currentStep) {
                $currentStep->update([
                    'status' => 'approved',
                    'approver_employee_id' => $employee?->id,
                    'notes' => $notes ?: ('Disetujui oleh ' . ($user->name ?? 'Approver')),
                ]);
            }

            // If last step or manually forced
            $next = $request->currentStep();
            if (! $next) {
                $request->update(['status' => 'approved']);
                $sppd->update(['status' => 'approved']);
            }

            $sigService->signDocument($sppd, $employee, $roleType, $notes ?: ('Disetujui oleh ' . ($user->name ?? 'Approver')), $user);

            Notification::make()->title('SPPD Berhasil Diproses & Ditandatangani')->success()->send();
        }

        $redirectUrl = request()->query('from') === 'approvals'
            ? SppdResource::getUrl('review', ['record' => $sppd, 'from' => 'approvals'])
            : SppdResource::getUrl('review', ['record' => $sppd]);

        $this->redirect($redirectUrl);
    }

    public function rejectSppd(string $notes): void
    {
        if (! $this->canPerformApproval()) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Anda hanya dapat menolak SPPD dari area kerja yang sama.')
                ->danger()
                ->send();

            return;
        }

        /** @var Sppd $sppd */
        $sppd = $this->getRecord();
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;

        if (! $employee && $user) {
            $employee = Employee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $user->name,
                    'identity_number' => 'ADM-' . $user->id,
                    'organization_id' => $sppd->employee?->organization_id ?? 1,
                    'department_id' => $sppd->employee?->department_id,
                    'position_id' => 1,
                ]
            );
        }

        $request = ApprovalRequest::where('document_type', Sppd::class)
            ->where('document_id', $sppd->id)
            ->first();

        if (! $request) {
            $sppd->update(['status' => 'rejected']);
            Notification::make()->title('Pengajuan SPPD Ditolak')->danger()->send();
            $redirectUrl = request()->query('from') === 'approvals'
                ? SppdResource::getUrl('review', ['record' => $sppd, 'from' => 'approvals'])
                : SppdResource::getUrl('review', ['record' => $sppd]);
            $this->redirect($redirectUrl);
            return;
        }

        $resolver = new ApprovalResolver();
        try {
            $resolver->reject($request, $employee, $notes);

            $sppd->refresh();
            if ($sppd->status !== 'rejected') {
                $sppd->update(['status' => 'rejected']);
            }

            Notification::make()->title('Pengajuan SPPD Ditolak')->danger()->send();
        } catch (\Exception $e) {
            $currentStep = $request->currentStep();
            if ($currentStep) {
                $currentStep->update([
                    'status' => 'rejected',
                    'approver_employee_id' => $employee?->id,
                    'notes' => $notes,
                ]);
            }
            $request->update(['status' => 'rejected']);
            $sppd->update(['status' => 'rejected']);

            Notification::make()->title('Pengajuan SPPD Ditolak')->danger()->send();
        }

        $redirectUrl = request()->query('from') === 'approvals'
            ? SppdResource::getUrl('review', ['record' => $sppd, 'from' => 'approvals'])
            : SppdResource::getUrl('review', ['record' => $sppd]);

        $this->redirect($redirectUrl);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(fn () => $this->getCurrentStepOrder() === 1 ? 'Setujui' : ($this->getCurrentStepOrder() === 2 ? 'Setujui' : 'Setujui SPPD'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->canPerformApproval())
                ->modalHeading(fn () => $this->getCurrentStepOrder() === 1 ? 'Konfirmasi Persetujuan SPPD oleh Supervisor' : 'Konfirmasi Persetujuan & Tanda Tangan SPPD oleh Manajer')
                ->modalDescription(function () {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    if (! $user?->hasConfiguredSignature()) {
                        return 'PERHATIAN: Anda belum mengatur tanda tangan. Silakan atur tanda tangan terlebih dahulu (cukup 1 kali) di menu Tanda Tangan sebelum dapat menandatangani SPPD ini.';
                    }
                    return $this->getCurrentStepOrder() === 1
                        ? 'Apakah Anda yakin menyetujui pengajuan SPPD ini? Tanda tangan Anda akan dicatat dan SPPD akan dilanjutkan ke Manajer untuk pengesahan final.'
                        : 'Apakah Anda yakin mengesahkan SPPD ini sebagai persetujuan tingkat akhir? Tanda tangan Anda yang telah tersimpan akan otomatis dibubuhkan pada dokumen SPPD.';
                })
                ->modalSubmitActionLabel('Ya, Setujui & Tandatangani')
                ->modalCancelActionLabel('Batal')
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan Persetujuan (Opsional)')
                        ->placeholder('Tuliskan catatan persetujuan jika diperlukan...'),
                ])
                ->action(fn (array $data) => $this->approveSppd($data['notes'] ?? null)),

            Action::make('reject')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->canPerformApproval())
                ->modalHeading('Tolak Pengajuan SPPD')
                ->modalDescription('Berikan catatan atau alasan penolakan SPPD ini.')
                ->modalSubmitActionLabel('Tolak')
                ->modalCancelActionLabel('Batal')
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->placeholder('Tuliskan alasan penolakan...'),
                ])
                ->action(fn (array $data) => $this->rejectSppd($data['notes'])),

            Action::make('employee_approve')
                ->label('Setujui & Tandatangani')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => SppdResource::canEmployeeSign($this->getRecord()))
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-clipboard-document-check')
                ->modalHeading('Konfirmasi Persetujuan & Tanda Tangan SPPD')
                ->modalDescription(function () {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    if (! $user?->hasConfiguredSignature()) {
                        return 'PERHATIAN: Anda belum mengatur tanda tangan digital. Silakan atur tanda tangan terlebih dahulu di menu Pengaturan Tanda Tangan sebelum menyetujui SPPD ini.';
                    }

                    return 'Dengan menekan tombol "Ya, Setujui & Tandatangani", Anda menyetujui penugasan dan perhitungan biaya perjalanan dinas sesuai ketentuan perusahaan. Tanda tangan digital Anda akan otomatis dibubuhkan pada cetakan SPPD.';
                })
                ->modalSubmitActionLabel('Ya, Setujui & Tandatangani')
                ->modalCancelActionLabel('Batal')
                ->action(function (): void {
                    if (SppdResource::performEmployeeSignature($this->getRecord())) {
                        $this->redirect(SppdResource::getUrl('review', ['record' => $this->getRecord()]));
                    }
                }),

            Action::make('print')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('sppds.print', $this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()?->status !== 'draft'),

            Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(function () {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    if (request()->query('from') === 'approvals' || $user?->hasAnyRole(['Manager', 'Supervisor']) || str_contains(request()->headers->get('referer', ''), 'sppd-approvals')) {
                        return SppdApprovalResource::getUrl('index');
                    }
                    return SppdResource::getUrl('index');
                }),
        ];
    }
}
