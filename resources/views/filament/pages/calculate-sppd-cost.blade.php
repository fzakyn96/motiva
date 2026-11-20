<x-filament-panels::page>
    <style>
        .sppd-info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        @media (min-width: 640px) {
            .sppd-info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (min-width: 1024px) {
            .sppd-info-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>

    {{-- Header Info SPPD menggunakan Native Filament Section --}}
    <x-filament::section
        icon="heroicon-o-document-text"
        icon-color="primary"
        collapsible
    >
        <x-slot name="heading">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
                <span style="margin-right: 0.25rem;">Dokumen SPPD: <span class="font-mono">{{ $record->sppd_number }}</span></span>
                @if ($record->businessTrip?->memo?->nomor_memo)
                    <x-filament::badge color="info" icon="heroicon-m-document-text" size="md">
                        No. Memo: {{ $record->businessTrip->memo->nomor_memo }}
                    </x-filament::badge>
                @endif
                <x-filament::badge color="success" icon="heroicon-m-check-circle" size="md">
                    SPPD Disetujui Manajer
                </x-filament::badge>
            </div>
        </x-slot>

        <x-slot name="description">
            Ringkasan identitas pegawai, lokasi tujuan, dan jadwal perjalanan dinas yang menjadi dasar perhitungan biaya.
        </x-slot>

        {{-- Grid Info (4 Kolom pada Desktop, 2 pada Tablet, 1 pada HP) --}}
        <div class="sppd-info-grid">
            {{-- Kolom 1: Pegawai --}}
            <div>
                <div class="flex items-center text-xs font-medium text-gray-500 dark:text-gray-400" style="margin-bottom: 0.375rem;">
                    <x-heroicon-m-user class="text-gray-400 dark:text-gray-500 shrink-0" style="width: 1rem; height: 1rem; margin-right: 0.5rem;" />
                    <span>Pegawai yang Ditugaskan</span>
                </div>
                <div class="text-sm font-bold text-gray-950 dark:text-white" style="margin-bottom: 0.25rem;">
                    {{ $record->employee?->name ?? '-' }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                    {{ $record->employee?->identity_number ? "NIP: {$record->employee->identity_number}" : '' }}
                    @if ($record->employee?->position)
                        &bull; {{ $record->employee->position->name }}
                    @endif
                    @if ($record->employee?->department)
                        &bull; {{ $record->employee->department->name }}
                    @endif
                </div>
            </div>

            {{-- Kolom 2: Rute & Lokasi --}}
            <div>
                <div class="flex items-center text-xs font-medium text-gray-500 dark:text-gray-400" style="margin-bottom: 0.375rem;">
                    <x-heroicon-m-map-pin class="text-gray-400 dark:text-gray-500 shrink-0" style="width: 1rem; height: 1rem; margin-right: 0.5rem;" />
                    <span>Rute & Lokasi Penugasan</span>
                </div>
                <div class="text-sm font-bold text-gray-950 dark:text-white break-words" style="margin-bottom: 0.25rem;">
                    {{ $record->destination_location ?? '-' }}
                </div>
                @if ($record->origin_location)
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Berangkat dari: {{ $record->origin_location }}
                    </div>
                @endif
            </div>

            {{-- Kolom 3: Jadwal Dinas --}}
            <div>
                <div class="flex items-center text-xs font-medium text-gray-500 dark:text-gray-400" style="margin-bottom: 0.375rem;">
                    <x-heroicon-m-calendar-days class="text-gray-400 dark:text-gray-500 shrink-0" style="width: 1rem; height: 1rem; margin-right: 0.5rem;" />
                    <span>Jadwal Dinas</span>
                </div>
                <div class="text-sm font-bold text-gray-950 dark:text-white" style="margin-bottom: 0.25rem;">
                    {{ $record->departure_date ? \Carbon\Carbon::parse($record->departure_date)->format('d M Y') : '-' }} s/d {{ $record->return_date ? \Carbon\Carbon::parse($record->return_date)->format('d M Y') : '-' }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Durasi: <strong class="text-gray-900 dark:text-white">{{ $record->total_days }} Hari</strong> Kalender
                </div>
            </div>

            {{-- Kolom 4: Agenda Penugasan --}}
            <div>
                <div class="flex items-center text-xs font-medium text-gray-500 dark:text-gray-400" style="margin-bottom: 0.375rem;">
                    <x-heroicon-m-briefcase class="text-gray-400 dark:text-gray-500 shrink-0" style="width: 1rem; height: 1rem; margin-right: 0.5rem;" />
                    <span>Maksud / Agenda Penugasan</span>
                </div>
                <div class="text-sm font-medium text-gray-900 dark:text-gray-200 leading-snug break-words">
                    {{ $record->assignment_purpose ?: ($record->businessTrip?->memo?->agenda ?? '-') }}
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- Form Perhitungan Biaya --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-top: 1.5rem;">
            <x-filament::button type="submit" size="md" icon="heroicon-o-check">
                Simpan Perhitungan Biaya
            </x-filament::button>

            <x-filament::button color="gray" size="md" :href="\App\Filament\Admin\Resources\SppdCostResource::getUrl('index')" tag="a" icon="heroicon-o-arrow-left">
                Batal
            </x-filament::button>

            @if ($record->cost)
                <x-filament::button color="info" size="md" :href="route('sppd-costs.print', $record->cost)" tag="a" target="_blank" icon="heroicon-o-printer">
                    Cetak
                </x-filament::button>
            @endif
        </div>
    </form>
</x-filament-panels::page>
