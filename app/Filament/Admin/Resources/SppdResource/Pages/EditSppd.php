<?php

namespace App\Filament\Admin\Resources\SppdResource\Pages;

use App\Filament\Admin\Resources\SppdResource;
use App\Models\Sppd;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSppd extends EditRecord
{
    protected static string $resource = SppdResource::class;

    protected static ?string $title = 'Formulir Perintah Perjalanan Dinas (SPPD)';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Sppd $sppd */
        $sppd = $this->getRecord();
        $memo = $sppd->businessTrip?->memo;
        $trip = $sppd->businessTrip;
        $employee = $sppd->employee;

        // Otomatis isi data faktual dari Pegawai / Memo / Business Trip
        if (empty($data['origin_location'])) {
            $data['origin_location'] = $employee?->area ?? 'Jakarta';
        }

        if (empty($data['destination_location'])) {
            $data['destination_location'] = $memo?->location ?? ($trip?->destination ?? '');
        }

        if (empty($data['assignment_purpose'])) {
            $data['assignment_purpose'] = $memo?->agenda ?? ($memo?->title ?? ($trip?->activity_name ?? ''));
        }

        if (! empty($data['departure_date'])) {
            $data['departure_date'] = \Carbon\Carbon::parse($data['departure_date'])->format('Y-m-d');
        } else {
            $rawDate = $memo?->assignment_start_date ?? $trip?->start_date;
            $data['departure_date'] = $rawDate ? \Carbon\Carbon::parse($rawDate)->format('Y-m-d') : null;
        }

        if (! empty($data['return_date'])) {
            $data['return_date'] = \Carbon\Carbon::parse($data['return_date'])->format('Y-m-d');
        } else {
            $rawRet = $memo?->assignment_end_date ?? $trip?->end_date;
            $data['return_date'] = $rawRet ? \Carbon\Carbon::parse($rawRet)->format('Y-m-d') : $data['departure_date'];
        }

        if (empty($data['departure_time'])) {
            $data['departure_time'] = '08:00';
        }

        if (empty($data['return_time'])) {
            $data['return_time'] = '17:00';
        }

        if (empty($data['total_days']) && ! empty($data['departure_date']) && ! empty($data['return_date'])) {
            $start = \Carbon\Carbon::parse($data['departure_date']);
            $end = \Carbon\Carbon::parse($data['return_date']);
            if ($end->greaterThanOrEqualTo($start)) {
                $data['total_days'] = $start->diffInDays($end) + 1;
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Tombol Kembali di bagian atas formulir
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('info')
                ->url(fn () => static::getResource()::getUrl('index')),

            // Tombol Cetak SPPD hanya muncul jika formulir sudah disimpan dan diajukan (status bukan draft)
            Actions\Action::make('print')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('sppds.print', $this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()->status !== 'draft'),

            Actions\DeleteAction::make()
                ->label('Hapus')
                ->icon('heroicon-o-trash')
                ->visible(fn () => SppdResource::canDelete($this->getRecord())),
        ];
    }

    /**
     * @var list<int|string>
     */
    protected array $selectedSiblingSppdIds = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->selectedSiblingSppdIds = $this->data['apply_to_sibling_sppd_ids'] ?? [];

        $currentStatus = $this->getRecord()?->status;

        // Status otomatis menyesuaikan: bila masih draft, saat disimpan otomatis menjadi pending
        if (empty($currentStatus) || $currentStatus === 'draft') {
            $data['status'] = 'pending';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Sppd $record */
        $record = $this->getRecord();
        if ($record->status === 'pending') {
            (new \App\Observers\SppdObserver())->ensureApprovalRequestExists($record);

            $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
            if ($user && (int) $record->employee?->user_id === (int) $user->id && $user->hasConfiguredSignature()) {
                app(\App\Services\Signature\SignatureService::class)->signDocument(
                    $record,
                    $record->employee,
                    'requester',
                    'Menyetujui perhitungan biaya perjalanan dinas sesuai keputusan perusahaan',
                    $user
                );
            }
        }

        if (
            ! empty($this->selectedSiblingSppdIds)
            && \App\Models\SppdSetting::current()->isHrFiller()
            && (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->hasAnyRole(['HR', 'Admin', 'Superadmin'])
        ) {
            $this->appliedSiblingCount = SppdResource::applyFormToSiblings($record, $this->selectedSiblingSppdIds);
        }
    }

    protected int $appliedSiblingCount = 0;

    protected function getSavedNotification(): ?\Filament\Notifications\Notification
    {
        if ($this->appliedSiblingCount > 0) {
            return \Filament\Notifications\Notification::make()
                ->title('SPPD Berhasil Disimpan & Diajukan')
                ->body("Isian form SPPD berhasil diterapkan ke {$this->appliedSiblingCount} pegawai lain pada Memo yang sama.")
                ->success();
        }

        return parent::getSavedNotification();
    }

    protected function getFormActions(): array
    {
        $record = $this->getRecord();
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $isDraft = $record?->status === 'draft';
        $isAssignedEmployee = $user && $record && (int) $record->employee?->user_id === (int) $user->id;
        $requiresEmployeeConfirmation = $isDraft
            && \App\Models\SppdSetting::current()->isStaffFiller()
            && $isAssignedEmployee;

        $saveAction = Actions\Action::make('save')
            ->label($isDraft ? 'Simpan & Ajukan SPPD' : 'Simpan Perubahan')
            ->icon('heroicon-o-check')
            ->color('primary');

        if ($requiresEmployeeConfirmation) {
            $saveAction
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-clipboard-document-check')
                ->modalHeading('Konfirmasi Persetujuan Perhitungan Biaya SPPD')
                ->modalDescription(function (): string {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $base = 'Dengan menekan tombol "Setuju", Anda menyatakan menyetujui perhitungan biaya perjalanan dinas sesuai keputusan dan ketentuan perusahaan yang berlaku.';
                    if (! $user?->hasConfiguredSignature()) {
                        return $base . ' (Catatan: Anda belum mengatur tanda tangan digital di menu Pengaturan Tanda Tangan, sehingga tanda tangan pada formulir perhitungan biaya belum dapat ditampilkan otomatis).';
                    }
                    return $base . ' Tanda tangan digital Anda akan otomatis dibubuhkan pada Formulir Perhitungan Biaya Perjalanan Dinas.';
                })
                ->modalSubmitActionLabel('Setuju')
                ->modalCancelActionLabel('Batal')
                ->action(fn () => $this->save());
        } else {
            $saveAction->submit('save');
        }

        return [
            $saveAction,
            $this->getCancelFormAction()
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
