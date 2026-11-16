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
                ->color('gray')
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
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
            $signerUser = $record->employee?->user ?? $user;
            if ($signerUser && $signerUser->hasConfiguredSignature()) {
                app(\App\Services\Signature\SignatureService::class)->signDocument(
                    $record,
                    $record->employee ?? $user?->employee,
                    'requester',
                    'Menyetujui perhitungan biaya perjalanan dinas sesuai keputusan perusahaan',
                    $signerUser
                );
            }
        }
    }

    protected function getFormActions(): array
    {
        $isDraft = $this->getRecord()?->status === 'draft';

        return [
            Actions\Action::make('save')
                ->label($isDraft ? 'Simpan & Ajukan SPPD' : 'Simpan Perubahan')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->requiresConfirmation($isDraft)
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
                ->action(fn () => $this->save()),
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
