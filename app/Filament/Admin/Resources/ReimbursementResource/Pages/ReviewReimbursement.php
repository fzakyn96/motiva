<?php

namespace App\Filament\Admin\Resources\ReimbursementResource\Pages;

use App\Filament\Admin\Resources\ReimbursementApprovalResource;
use App\Filament\Admin\Resources\ReimbursementResource;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Reimbursement;
use App\Services\Approval\ApprovalResolver;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ReviewReimbursement extends ViewRecord
{
    protected static string $resource = ReimbursementResource::class;

    protected static ?string $title = 'Review Pengajuan Reimbursement';

    public static function authorizeResourceAccess(): void
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        abort_unless($user && ($user->hasRole(['Manager', 'Supervisor', 'Admin', 'Superadmin', 'HR']) || static::getResource()::canAccess()), 403);
    }

    public function getBreadcrumbs(): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (request()->query('from') === 'approvals' || $user?->hasRole('Manager') || str_contains(request()->headers->get('referer', ''), 'reimbursement-approvals')) {
            return [
                ReimbursementApprovalResource::getUrl('index') => 'Persetujuan Reimbursement',
                'Review',
            ];
        }

        return parent::getBreadcrumbs();
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);

        /** @var Reimbursement|null $reimbursement */
        $reimbursement = $this->getRecord();
        if ($reimbursement && $reimbursement->status === 'pending') {
            (new \App\Observers\ReimbursementObserver())->ensureApprovalRequestExists($reimbursement);
        }
    }

    public function canPerformApproval(): bool
    {
        /** @var Reimbursement|null $reimbursement */
        $reimbursement = $this->getRecord();

        if (! $reimbursement || $reimbursement->status !== 'pending') {
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
            return false;
        }

        $request = ApprovalRequest::where('document_type', Reimbursement::class)
            ->where('document_id', $reimbursement->id)
            ->first();

        if (! $request) {
            $request = (new \App\Observers\ReimbursementObserver())->ensureApprovalRequestExists($reimbursement);
        }

        if (! $request || $request->status !== 'pending') {
            return false;
        }

        $resolver = new ApprovalResolver();
        return $resolver->canApprove($request, $employee);
    }

    public function approveReimbursement(?string $notes = null): void
    {
        /** @var Reimbursement $reimbursement */
        $reimbursement = $this->getRecord();
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
                    'organization_id' => $reimbursement->employee?->organization_id ?? 1,
                    'department_id' => $reimbursement->employee?->department_id,
                    'position_id' => 1,
                ]
            );
        }

        $request = ApprovalRequest::where('document_type', Reimbursement::class)
            ->where('document_id', $reimbursement->id)
            ->first();

        if (! $request) {
            $reimbursement->update(['status' => 'approved', 'approved_at' => now()]);
            $sigService->signDocument($reimbursement, $employee, 'manager', $notes ?: 'Disetujui oleh Manajer', $user);
            Notification::make()->title('Reimbursement Berhasil Disetujui & Ditandatangani')->success()->send();
            $this->redirect(ReimbursementResource::getUrl('review', ['record' => $reimbursement]));
            return;
        }

        $resolver = new ApprovalResolver();
        try {
            $resolver->approve($request, $employee, $notes ?: 'Disetujui oleh Manajer');

            $reimbursement->refresh();
            if ($reimbursement->status !== 'approved') {
                $reimbursement->update(['status' => 'approved', 'approved_at' => now()]);
            }

            $sigService->signDocument($reimbursement, $employee, 'manager', $notes ?: 'Disetujui oleh Manajer', $user);

            Notification::make()
                ->title('Reimbursement Berhasil Disetujui & Ditandatangani')
                ->body('Persetujuan dan tanda tangan Manajer telah dibubuhkan pada dokumen reimbursement.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            $currentStep = $request->currentStep();
            if ($currentStep) {
                $currentStep->update([
                    'status' => 'approved',
                    'approver_employee_id' => $employee?->id,
                    'notes' => $notes ?: ('Disetujui oleh ' . ($user->name ?? 'Approver')),
                ]);
            }

            $request->update(['status' => 'approved']);
            $reimbursement->update(['status' => 'approved', 'approved_at' => now()]);
            $sigService->signDocument($reimbursement, $employee, 'manager', $notes ?: ('Disetujui oleh ' . ($user->name ?? 'Approver')), $user);

            Notification::make()->title('Reimbursement Berhasil Disetujui & Ditandatangani')->success()->send();
        }

        $redirectUrl = request()->query('from') === 'approvals'
            ? ReimbursementResource::getUrl('review', ['record' => $reimbursement, 'from' => 'approvals'])
            : ReimbursementResource::getUrl('review', ['record' => $reimbursement]);

        $this->redirect($redirectUrl);
    }

    public function rejectReimbursement(string $notes): void
    {
        /** @var Reimbursement $reimbursement */
        $reimbursement = $this->getRecord();
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;

        if (! $employee && $user) {
            $employee = Employee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $user->name,
                    'identity_number' => 'ADM-' . $user->id,
                    'organization_id' => $reimbursement->employee?->organization_id ?? 1,
                    'department_id' => $reimbursement->employee?->department_id,
                    'position_id' => 1,
                ]
            );
        }

        $request = ApprovalRequest::where('document_type', Reimbursement::class)
            ->where('document_id', $reimbursement->id)
            ->first();

        if (! $request) {
            $reimbursement->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_note' => $notes,
            ]);
            Notification::make()->title('Pengajuan Reimbursement Ditolak')->danger()->send();
            $redirectUrl = request()->query('from') === 'approvals'
                ? ReimbursementResource::getUrl('review', ['record' => $reimbursement, 'from' => 'approvals'])
                : ReimbursementResource::getUrl('review', ['record' => $reimbursement]);
            $this->redirect($redirectUrl);
            return;
        }

        $resolver = new ApprovalResolver();
        try {
            $resolver->reject($request, $employee, $notes);

            $reimbursement->refresh();
            $reimbursement->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_note' => $notes,
            ]);

            Notification::make()->title('Pengajuan Reimbursement Ditolak')->danger()->send();
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
            $reimbursement->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_note' => $notes,
            ]);

            Notification::make()->title('Pengajuan Reimbursement Ditolak')->danger()->send();
        }

        $redirectUrl = request()->query('from') === 'approvals'
            ? ReimbursementResource::getUrl('review', ['record' => $reimbursement, 'from' => 'approvals'])
            : ReimbursementResource::getUrl('review', ['record' => $reimbursement]);

        $this->redirect($redirectUrl);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Setujui Reimbursement')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->canPerformApproval())
                ->modalHeading('Konfirmasi Persetujuan & Tanda Tangan Reimbursement')
                ->modalDescription(function () {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    if (! $user?->hasConfiguredSignature()) {
                        return 'PERHATIAN: Anda belum mengatur tanda tangan. Silakan atur tanda tangan terlebih dahulu (cukup 1 kali) di menu Tanda Tangan sebelum menyetujui dokumen ini.';
                    }
                    return 'Apakah Anda yakin menyetujui pengajuan penggantian biaya (reimbursement) ini? Total klaim yang akan diganti ke pegawai adalah Rp ' . number_format($this->getRecord()?->total_claim_amount ?? 0, 0, ',', '.') . '. Tanda tangan Anda akan otomatis dibubuhkan pada formulir.';
                })
                ->modalSubmitActionLabel('Ya, Setujui & Tandatangani')
                ->modalCancelActionLabel('Batal')
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan Persetujuan (Opsional)')
                        ->placeholder('Tuliskan catatan persetujuan jika diperlukan...'),
                ])
                ->action(fn (array $data) => $this->approveReimbursement($data['notes'] ?? null)),

            Action::make('reject')
                ->label('Tolak Reimbursement')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->canPerformApproval())
                ->modalHeading('Tolak Pengajuan Reimbursement')
                ->modalDescription('Berikan catatan atau alasan penolakan reimbursement ini agar pengaju dapat memperbaikinya.')
                ->modalSubmitActionLabel('Tolak')
                ->modalCancelActionLabel('Batal')
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->placeholder('Tuliskan alasan penolakan...'),
                ])
                ->action(fn (array $data) => $this->rejectReimbursement($data['notes'])),

            Action::make('print')
                ->label('Cetak Formulir')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('reimbursements.print', $this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()?->status !== 'draft'),

            Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(function () {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    if (request()->query('from') === 'approvals' || $user?->hasRole('Manager') || str_contains(request()->headers->get('referer', ''), 'reimbursement-approvals')) {
                        return ReimbursementApprovalResource::getUrl('index');
                    }
                    return ReimbursementResource::getUrl('index');
                }),
        ];
    }
}
