<?php

namespace App\Filament\Admin\Resources\MemoResource\Pages;

use App\Filament\Admin\Resources\MemoResource;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Memo;
use App\Services\Approval\ApprovalResolver;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\ActionSize;

class ReviewMemo extends ViewRecord
{
    protected static string $resource = MemoResource::class;

    protected static ?string $title = 'Review Memo & Persetujuan';

    protected static string $view = 'filament.admin.resources.memo-resource.pages.review-memo';

    public function canPerformApproval(): bool
    {
        /** @var Memo $memo */
        $memo = $this->record;

        if ($memo->status !== 'pending') {
            return false;
        }

        $user = auth()->user();
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

        $request = ApprovalRequest::where('document_type', Memo::class)
            ->where('document_id', $memo->id)
            ->first();

        if (! $request) {
            return $user->hasRole('Manager');
        }

        if ($request->status !== 'pending') {
            return false;
        }

        $resolver = new ApprovalResolver();
        return $resolver->canApprove($request, $employee) || $user->hasRole('Manager');
    }

    public function approveMemo(): void
    {
        /** @var Memo $memo */
        $memo = $this->record;
        $user = auth()->user();
        $employee = $user?->employee;

        $request = ApprovalRequest::where('document_type', Memo::class)
            ->where('document_id', $memo->id)
            ->first();

        if (! $request) {
            $memo->update(['status' => 'approved']);
            Notification::make()->title('Memo Berhasil Disetujui')->success()->send();
            return;
        }

        if (! $employee) {
            $employee = Employee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $user->name,
                    'identity_number' => 'ADM-' . $user->id,
                    'organization_id' => $memo->creator?->organization_id ?? 1,
                    'department_id' => $memo->department_id,
                    'position_id' => 1,
                ]
            );
        }

        $resolver = new ApprovalResolver();
        try {
            $resolver->approve($request, $employee, 'Disetujui melalui Halaman Review');
            
            $memo->refresh();
            if ($memo->status !== 'approved') {
                $memo->update(['status' => 'approved']);
            }

            Notification::make()->title('Memo Berhasil Disetujui')->success()->send();
        } catch (\Exception $e) {
            if ($user->hasRole('Superadmin') || $user->hasRole('Admin') || $user->hasRole('Manager')) {
                $currentStep = $request->currentStep();
                if ($currentStep) {
                    $currentStep->update([
                        'status' => 'approved',
                        'approver_employee_id' => $employee->id,
                        'notes' => 'Disetujui oleh ' . ($user->name ?? 'Manajer'),
                    ]);
                }
                $request->update(['status' => 'approved']);
                $memo->update(['status' => 'approved']);

                Notification::make()->title('Memo Berhasil Disetujui')->success()->send();
                return;
            }

            Notification::make()->title('Gagal Menyetujui Memo')->body($e->getMessage())->danger()->send();
        }
    }

    public function rejectMemo(string $notes): void
    {
        /** @var Memo $memo */
        $memo = $this->record;
        $user = auth()->user();
        $employee = $user?->employee;

        $request = ApprovalRequest::where('document_type', Memo::class)
            ->where('document_id', $memo->id)
            ->first();

        if (! $request) {
            $memo->update(['status' => 'rejected']);
            Notification::make()->title('Memo Berhasil Ditolak')->success()->send();
            return;
        }

        if (! $employee) {
            $employee = Employee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $user->name,
                    'identity_number' => 'ADM-' . $user->id,
                    'organization_id' => $memo->creator?->organization_id ?? 1,
                    'department_id' => $memo->department_id,
                    'position_id' => 1,
                ]
            );
        }

        $resolver = new ApprovalResolver();
        try {
            $resolver->reject($request, $employee, $notes);

            $memo->refresh();
            if ($memo->status !== 'rejected') {
                $memo->update(['status' => 'rejected']);
            }

            Notification::make()->title('Memo Berhasil Ditolak')->success()->send();
        } catch (\Exception $e) {
            if ($user->hasRole('Superadmin') || $user->hasRole('Admin') || $user->hasRole('Manager')) {
                $currentStep = $request->currentStep();
                if ($currentStep) {
                    $currentStep->update([
                        'status' => 'rejected',
                        'approver_employee_id' => $employee->id,
                        'notes' => $notes,
                    ]);
                }
                $request->update(['status' => 'rejected']);
                $memo->update(['status' => 'rejected']);

                Notification::make()->title('Memo Berhasil Ditolak')->success()->send();
                return;
            }

            Notification::make()->title('Gagal Menolak Memo')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getViewData(): array
    {
        /** @var Memo $memo */
        $memo = $this->record;
        $memo->loadMissing(['department', 'creator.position', 'creator.department']);

        $approvalRequest = ApprovalRequest::with([
            'steps.approver.position',
            'steps.policyStep',
            'requester.position',
            'requester.department'
        ])
            ->where('document_type', Memo::class)
            ->where('document_id', $memo->id)
            ->first();

        $participantsList = [];
        if (! empty($memo->participants) && is_array($memo->participants)) {
            foreach ($memo->participants as $participant) {
                $emp = is_numeric($participant)
                    ? Employee::with(['position', 'department'])->find($participant)
                    : Employee::with(['position', 'department'])
                        ->where('name', $participant)
                        ->orWhere('identity_number', $participant)
                        ->first();

                if ($emp) {
                    $sppd = \App\Models\Sppd::whereHas('businessTrip', fn ($q) => $q->where('memo_id', $memo->id))
                        ->where('employee_id', $emp->id)
                        ->first();

                    $participantsList[] = [
                        'employee' => $emp,
                        'sppd' => $sppd,
                    ];
                }
            }
        }

        return [
            'memo' => $memo,
            'approvalRequest' => $approvalRequest,
            'participantsList' => $participantsList,
            'canApprove' => $this->canPerformApproval(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Setujui Memo')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->size(ActionSize::Large)
                ->visible(fn () => $this->canPerformApproval())
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Persetujuan Memo')
                ->modalDescription('Apakah Anda yakin menyetujui memo ini? Setelah disetujui, nomor dokumen dan tanda tangan resmi akan disahkan.')
                ->modalSubmitActionLabel('Ya, Setujui')
                ->modalCancelActionLabel('Batal')
                ->action(fn () => $this->approveMemo()),

            Action::make('reject')
                ->label('Tolak Memo')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->size(ActionSize::Large)
                ->visible(fn () => $this->canPerformApproval())
                ->modalHeading('Tolak Pengajuan Memo')
                ->modalDescription('Berikan catatan atau alasan penolakan memo ini.')
                ->modalSubmitActionLabel('Tolak Memo')
                ->modalCancelActionLabel('Batal')
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->placeholder('Tuliskan alasan penolakan...'),
                ])
                ->action(fn (array $data) => $this->rejectMemo($data['notes'])),

            Action::make('print')
                ->label('Cetak PDF')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->size(ActionSize::Large)
                ->url(fn () => route('memos.print', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->status === 'approved'),

            Action::make('back')
                ->label('Kembali ke Daftar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MemoResource::getUrl('index')),
        ];
    }
}
