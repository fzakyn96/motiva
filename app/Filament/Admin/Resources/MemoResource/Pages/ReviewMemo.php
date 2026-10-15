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

    protected static ?string $title = 'Review Memo';

    public function getBreadcrumbs(): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (request()->query('from') === 'approvals' || $user?->hasRole('Manager') || str_contains(request()->headers->get('referer', ''), 'memo-approvals')) {
            return [
                \App\Filament\Admin\Resources\MemoApprovalResource::getUrl('index') => 'Persetujuan Memo',
                'Review',
            ];
        }

        return parent::getBreadcrumbs();
    }

    public function canPerformApproval(): bool
    {
        /** @var Memo|null $memo */
        $memo = $this->getRecord();

        if (! $memo || $memo->status !== 'pending') {
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

        // If user is a Manager or has approve_memo permission
        if ($user->hasRole('Manager') || $user->can('approve_memo')) {
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
            return true;
        }

        if ($request->status !== 'pending') {
            return false;
        }

        $resolver = new ApprovalResolver();
        return $resolver->canApprove($request, $employee);
    }

    public function approveMemo(): void
    {
        /** @var Memo $memo */
        $memo = $this->getRecord();
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;

        if (! $employee && $user) {
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

        $request = ApprovalRequest::where('document_type', Memo::class)
            ->where('document_id', $memo->id)
            ->first();

        if (! $request) {
            $memo->update(['status' => 'approved']);
            Notification::make()->title('Memo Berhasil Disetujui')->success()->send();
            $this->redirect(MemoResource::getUrl('review', ['record' => $memo]));
            return;
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
            $currentStep = $request->currentStep();
            if ($currentStep) {
                $currentStep->update([
                    'status' => 'approved',
                    'approver_employee_id' => $employee?->id,
                    'notes' => 'Disetujui oleh ' . ($user->name ?? 'Manajer'),
                ]);
            }
            $request->update(['status' => 'approved']);
            $memo->update(['status' => 'approved']);

            Notification::make()->title('Memo Berhasil Disetujui')->success()->send();
        }

        $redirectUrl = request()->query('from') === 'approvals'
            ? MemoResource::getUrl('review', ['record' => $memo, 'from' => 'approvals'])
            : MemoResource::getUrl('review', ['record' => $memo]);

        $this->redirect($redirectUrl);
    }

    public function rejectMemo(string $notes): void
    {
        /** @var Memo $memo */
        $memo = $this->getRecord();
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;

        if (! $employee && $user) {
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

        $request = ApprovalRequest::where('document_type', Memo::class)
            ->where('document_id', $memo->id)
            ->first();

        if (! $request) {
            $memo->update(['status' => 'rejected']);
            Notification::make()->title('Memo Berhasil Ditolak')->success()->send();
            $redirectUrl = request()->query('from') === 'approvals'
                ? MemoResource::getUrl('review', ['record' => $memo, 'from' => 'approvals'])
                : MemoResource::getUrl('review', ['record' => $memo]);
            $this->redirect($redirectUrl);
            return;
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
            $currentStep = $request->currentStep();
            if ($currentStep) {
                $currentStep->update([
                    'status' => 'rejected',
                    'approver_employee_id' => $employee?->id,
                    'notes' => $notes,
                ]);
            }
            $request->update(['status' => 'rejected']);
            $memo->update(['status' => 'rejected']);

            Notification::make()->title('Memo Berhasil Ditolak')->success()->send();
        }

        $redirectUrl = request()->query('from') === 'approvals'
            ? MemoResource::getUrl('review', ['record' => $memo, 'from' => 'approvals'])
            : MemoResource::getUrl('review', ['record' => $memo]);

        $this->redirect($redirectUrl);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Setujui Memo')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                // ->size(ActionSize::Large)
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
                // ->size(ActionSize::Large)
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
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('memos.print', $this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()?->status === 'approved'),

            Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(function () {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    if (request()->query('from') === 'approvals' || $user?->hasRole('Manager') || str_contains(request()->headers->get('referer', ''), 'memo-approvals')) {
                        return \App\Filament\Admin\Resources\MemoApprovalResource::getUrl('index');
                    }
                    return MemoResource::getUrl('index');
                }),
        ];
    }
}
