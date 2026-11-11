<x-filament-widgets::widget>
    @php
        $d = $this->getBannerData();
        /** @var \App\Models\User|null $user */
        $user = $d['user'];
        $employee = $d['employee'];
        $roles = $user ? $user->getRoleNames()->implode(', ') : 'Pengguna';
    @endphp

    <x-filament::section
        icon="heroicon-o-sparkles"
        icon-color="primary"
    >
        <x-slot name="heading">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.625rem;">
                <span style="margin-right: 0.25rem;">Selamat Datang, {{ $employee?->name ?? $user?->name ?? 'Pengguna' }}</span>

                <x-filament::badge color="primary" size="sm" icon="heroicon-m-shield-check">
                    {{ $roles ?: 'User' }}
                </x-filament::badge>

                @if ($employee?->department)
                    <x-filament::badge color="info" size="sm" icon="heroicon-m-building-office-2">
                        Unit {{ $employee->department->name }}
                    </x-filament::badge>
                @endif
            </div>
        </x-slot>

        <x-slot name="headerEnd">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
                @if ($d['hasSignature'])
                    <x-filament::badge color="success" size="sm" icon="heroicon-m-check-badge">
                        Tanda Tangan Digital Aktif
                    </x-filament::badge>
                @else
                    <x-filament::badge color="warning" size="sm" icon="heroicon-m-exclamation-triangle">
                        Tanda Tangan Belum Diatur
                    </x-filament::badge>
                @endif
            </div>
        </x-slot>

        <x-slot name="description">
            @if ($d['isHrOrAdmin'])
                Pusat Kendali Eksekutif & Operasional SDM — Pantau seluruh antrian persetujuan, kalkulasi biaya SPPD, klaim reimbursement, dan data kepegawaian secara real-time.
            @elseif ($d['isManager'])
                Dashboard Eksekutif Manajer — Tinjau dan sahkan permohonan Memo, SPPD, serta klaim Reimbursement unit kerja Anda dengan pembubuhan tanda tangan otomatis.
            @elseif ($d['isSupervisor'])
                Dashboard Eksekutif Supervisor — Pantau penugasan tim dan verifikasi pengajuan Surat Perintah Perjalanan Dinas (SPPD) Tahap 1.
            @else
                Portal Perjalanan Dinas Pegawai — Kelola pengajuan Memo, lengkapi formulir SPPD penugasan Anda, dan ajukan klaim penggantian biaya (Reimbursement).
            @endif
        </x-slot>

        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
            {{-- Pintasan Cepat Berdasarkan Peran --}}
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
                @if ($d['isHrOrAdmin'])
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\MemoResource::getUrl('create')"
                        color="primary"
                        size="sm"
                        icon="heroicon-m-plus"
                    >
                        Buat Memo Dinas
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\SppdCostResource::getUrl('index')"
                        :color="$d['uncalculatedSppdCost'] > 0 ? 'warning' : 'gray'"
                        :outlined="$d['uncalculatedSppdCost'] === 0"
                        size="sm"
                        icon="heroicon-m-calculator"
                    >
                        Hitung Biaya SPPD
                        @if ($d['uncalculatedSppdCost'] > 0)
                            ({{ $d['uncalculatedSppdCost'] }} Belum Dihitung)
                        @endif
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\MemoApprovalResource::getUrl('index')"
                        :color="$d['pendingMemo'] > 0 ? 'warning' : 'gray'"
                        outlined
                        size="sm"
                        icon="heroicon-m-document-check"
                    >
                        Persetujuan Memo ({{ $d['pendingMemo'] }})
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\SppdApprovalResource::getUrl('index')"
                        :color="$d['pendingSppd'] > 0 ? 'info' : 'gray'"
                        outlined
                        size="sm"
                        icon="heroicon-m-clipboard-document-check"
                    >
                        Persetujuan SPPD ({{ $d['pendingSppd'] }})
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\ReimbursementApprovalResource::getUrl('index')"
                        :color="$d['pendingReimb'] > 0 ? 'success' : 'gray'"
                        outlined
                        size="sm"
                        icon="heroicon-m-check-badge"
                    >
                        Persetujuan Reimb ({{ $d['pendingReimb'] }})
                    </x-filament::button>
                @elseif ($d['isManager'])
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\MemoApprovalResource::getUrl('index')"
                        :color="$d['pendingMemo'] > 0 ? 'warning' : 'gray'"
                        :outlined="$d['pendingMemo'] === 0"
                        size="sm"
                        icon="heroicon-m-document-check"
                    >
                        Persetujuan Memo ({{ $d['pendingMemo'] }} Pending)
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\SppdApprovalResource::getUrl('index')"
                        :color="$d['pendingSppd'] > 0 ? 'info' : 'gray'"
                        :outlined="$d['pendingSppd'] === 0"
                        size="sm"
                        icon="heroicon-m-clipboard-document-check"
                    >
                        Persetujuan SPPD ({{ $d['pendingSppd'] }} Pending)
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\ReimbursementApprovalResource::getUrl('index')"
                        :color="$d['pendingReimb'] > 0 ? 'success' : 'gray'"
                        :outlined="$d['pendingReimb'] === 0"
                        size="sm"
                        icon="heroicon-m-check-badge"
                    >
                        Persetujuan Reimbursement ({{ $d['pendingReimb'] }} Pending)
                    </x-filament::button>
                @elseif ($d['isSupervisor'])
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\SppdApprovalResource::getUrl('index')"
                        :color="$d['pendingSppd'] > 0 ? 'warning' : 'primary'"
                        size="sm"
                        icon="heroicon-m-clipboard-document-check"
                    >
                        Verifikasi Persetujuan SPPD ({{ $d['pendingSppd'] }} Menunggu)
                    </x-filament::button>
                @else
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\MemoResource::getUrl('create')"
                        color="primary"
                        size="sm"
                        icon="heroicon-m-plus"
                    >
                        Buat Memo Penugasan
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\SppdResource::getUrl('index')"
                        :color="$d['draftSppdForStaff'] > 0 ? 'warning' : 'gray'"
                        :outlined="$d['draftSppdForStaff'] === 0"
                        size="sm"
                        icon="heroicon-m-paper-airplane"
                    >
                        SPPD Saya
                        @if ($d['draftSppdForStaff'] > 0)
                            ({{ $d['draftSppdForStaff'] }} Perlu Dilengkapi)
                        @endif
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\ReimbursementResource::getUrl('create')"
                        color="info"
                        outlined
                        size="sm"
                        icon="heroicon-m-banknotes"
                    >
                        Ajukan Reimbursement
                    </x-filament::button>
                @endif
            </div>

            {{-- Tombol Pengaturan Tanda Tangan --}}
            <div style="display: flex; align-items: center;">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Pages\SignatureSettingsPage::getUrl()"
                    :color="$d['hasSignature'] ? 'gray' : 'warning'"
                    :outlined="$d['hasSignature']"
                    size="sm"
                    icon="heroicon-m-pencil-square"
                >
                    {{ $d['hasSignature'] ? 'Pengaturan Tanda Tangan' : 'Atur Tanda Tangan (Wajib 1x)' }}
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
