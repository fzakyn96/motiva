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

        // Otomatis isi Tempat Kedudukan
        if (empty($data['origin_location'])) {
            $data['origin_location'] = $employee?->area ?? 'Jakarta';
        }

        // Otomatis isi Lokasi Penugasan dari Memo / Business Trip
        if (empty($data['destination_location'])) {
            $data['destination_location'] = $memo?->location ?? ($trip?->destination ?? '');
        }

        // Otomatis isi Deskripsi Tujuan Penugasan dari Agenda Memo
        if (empty($data['assignment_purpose'])) {
            $data['assignment_purpose'] = $memo?->agenda ?? ($memo?->title ?? ($trip?->activity_name ?? ''));
        }

        // Otomatis isi Tanggal Berangkat dari Memo / Business Trip
        if (! empty($data['departure_date'])) {
            $data['departure_date'] = \Carbon\Carbon::parse($data['departure_date'])->format('Y-m-d');
        } else {
            $rawDate = $memo?->assignment_start_date ?? $trip?->start_date;
            $data['departure_date'] = $rawDate ? \Carbon\Carbon::parse($rawDate)->format('Y-m-d') : null;
        }

        // Otomatis isi Tanggal Kembali dari Memo / Business Trip
        if (! empty($data['return_date'])) {
            $data['return_date'] = \Carbon\Carbon::parse($data['return_date'])->format('Y-m-d');
        } else {
            $rawRet = $memo?->assignment_end_date ?? $trip?->end_date;
            $data['return_date'] = $rawRet ? \Carbon\Carbon::parse($rawRet)->format('Y-m-d') : $data['departure_date'];
        }

        // Otomatis hitung Jumlah Hari
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
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Status otomatis menyesuaikan: bila masih draft, saat disimpan pegawai otomatis menjadi pending
        if (empty($data['status']) || $data['status'] === 'draft') {
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
            if ($user && $user->hasConfiguredSignature()) {
                app(\App\Services\Signature\SignatureService::class)->signDocument(
                    $record,
                    $record->employee ?? $user->employee,
                    'requester',
                    'Diajukan oleh Pegawai',
                    $user
                );
            }
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label(fn () => $this->getRecord()->status === 'draft' ? 'Simpan & Ajukan SPPD' : 'Simpan Perubahan')
                ->icon('heroicon-o-check'),
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
