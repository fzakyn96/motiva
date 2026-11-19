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
            <div class="flex flex-col" style="gap: 0.5rem;">
                <span class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Selamat Datang, {{ $employee?->name ?? $user?->name ?? 'Pengguna' }}
                </span>

                <div class="flex flex-wrap items-center" style="gap: 0.5rem;">
                    <x-filament::badge color="primary" size="sm" icon="heroicon-m-shield-check">
                        {{ !empty($d['isBusinessDivisionHead']) ? 'Business Division Head (Perkasa)' : ($roles ?: 'User') }}
                    </x-filament::badge>

                    @if ($employee?->organization)
                        <x-filament::badge color="gray" size="sm" icon="heroicon-m-building-library">
                            {{ $employee->organization->name }}
                        </x-filament::badge>
                    @endif

                    @if ($employee?->workArea)
                        <x-filament::badge color="success" size="sm" icon="heroicon-m-map-pin">
                            Area Kerja {{ $employee->workArea->name }}
                        </x-filament::badge>
                    @endif

                    @if ($employee?->department)
                        <x-filament::badge color="info" size="sm" icon="heroicon-m-building-office-2">
                            Unit {{ $employee->department->name }}
                        </x-filament::badge>
                    @endif

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
            </div>
        </x-slot>

        <x-slot name="description">
            @if ($d['isHrOrAdmin'])
                <!-- Pusat Kendali Eksekutif & Operasional SDM — Pantau seluruh antrian persetujuan, kalkulasi biaya SPPD, klaim reimbursement, dan data kepegawaian secara real-time. -->
            @elseif ($d['isManager'])
                <!-- Dashboard Eksekutif Manajer — Tinjau dan sahkan permohonan Memo, SPPD, serta klaim Reimbursement unit kerja Anda dengan pembubuhan tanda tangan otomatis. -->
            @elseif ($d['isSupervisor'])
                <!-- Dashboard Eksekutif Supervisor — Pantau penugasan tim dan verifikasi pengajuan Surat Perintah Perjalanan Dinas (SPPD) Tahap 1. -->
            @else
                <!-- Portal Perjalanan Dinas Pegawai — Lengkapi formulir SPPD penugasan Anda dan ajukan klaim penggantian biaya (Reimbursement). -->
            @endif
        </x-slot>

        @if ($user?->hasRole('Superadmin'))
            <x-filament::grid
                :default="1"
                :sm="2"
                :lg="3"
                class="gap-3"
                style="gap: 0.75rem;"
            >
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\MemoResource::getUrl('create')"
                    color="primary"
                    size="sm"
                    icon="heroicon-m-plus"
                    class="w-full"
                >
                    Buat Memo Dinas
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\SppdCostResource::getUrl('index')"
                    color="warning"
                    size="sm"
                    icon="heroicon-m-calculator"
                    class="w-full"
                >
                    Hitung Biaya SPPD
                    @if ($d['uncalculatedSppdCost'] > 0)
                        ({{ $d['uncalculatedSppdCost'] }})
                    @endif
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\TransportAccommodationRecapResource::getUrl('index')"
                    color="info"
                    size="sm"
                    icon="heroicon-m-ticket"
                    class="w-full"
                >
                    Rekap Tiket &amp; Akomodasi
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\OfficialTravelRecapResource::getUrl('index')"
                    color="success"
                    size="sm"
                    icon="heroicon-m-document-chart-bar"
                    class="w-full"
                >
                    Rekap Perjalanan Dinas
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\MemoApprovalResource::getUrl('index')"
                    color="primary"
                    size="sm"
                    icon="heroicon-m-document-check"
                    class="w-full"
                >
                    Persetujuan Memo ({{ $d['pendingMemo'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\SppdApprovalResource::getUrl('index')"
                    color="info"
                    size="sm"
                    icon="heroicon-m-clipboard-document-check"
                    class="w-full"
                >
                    Persetujuan SPPD ({{ $d['pendingSppd'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\SppdCostApprovalResource::getUrl('index')"
                    color="danger"
                    size="sm"
                    icon="heroicon-m-calculator"
                    class="w-full"
                >
                    Persetujuan Biaya SPPD ({{ $d['pendingSppdCost'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\TransportAccommodationApprovalResource::getUrl('index')"
                    color="success"
                    size="sm"
                    icon="heroicon-m-check-badge"
                    class="w-full"
                >
                    Persetujuan Rekap Tiket ({{ $d['pendingTransportRecap'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\OfficialTravelApprovalResource::getUrl('index')"
                    color="indigo"
                    size="sm"
                    icon="heroicon-m-check-badge"
                    class="w-full"
                >
                    Persetujuan Rekap Dinas ({{ $d['pendingTravelRecap'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Pages\SppdSettingsPage::getUrl()"
                    color="gray"
                    size="sm"
                    icon="heroicon-m-cog-6-tooth"
                    class="w-full"
                >
                    Pengaturan SPPD
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Pages\ReimbursementSettingsPage::getUrl()"
                    color="gray"
                    size="sm"
                    icon="heroicon-m-percent-badge"
                    class="w-full"
                >
                    Pengaturan Fee Rekap
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Pages\SignatureSettingsPage::getUrl()"
                    :color="$d['hasSignature'] ? 'teal' : 'danger'"
                    size="sm"
                    icon="heroicon-m-pencil-square"
                    class="w-full"
                >
                    {{ $d['hasSignature'] ? 'Pengaturan Tanda Tangan' : 'Atur Tanda Tangan' }}
                </x-filament::button>
            </x-filament::grid>
        @elseif ($d['isHrOrAdmin'])
            <x-filament::grid
                :default="1"
                :sm="2"
                :lg="3"
                class="gap-3"
                style="gap: 0.75rem;"
            >
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\MemoResource::getUrl('create')"
                    color="primary"
                    size="sm"
                    icon="heroicon-m-plus"
                    class="w-full"
                >
                    Buat Memo Dinas
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\SppdCostResource::getUrl('index')"
                    color="warning"
                    size="sm"
                    icon="heroicon-m-calculator"
                    class="w-full"
                >
                    Hitung Biaya SPPD
                    @if ($d['uncalculatedSppdCost'] > 0)
                        ({{ $d['uncalculatedSppdCost'] }})
                    @endif
                </x-filament::button>

                @if ($user?->hasAnyRole(['Superadmin', 'Admin']))
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\MemoApprovalResource::getUrl('index')"
                        color="info"
                        size="sm"
                        icon="heroicon-m-document-check"
                        class="w-full"
                    >
                        Persetujuan Memo ({{ $d['pendingMemo'] }})
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\SppdApprovalResource::getUrl('index')"
                        color="indigo"
                        size="sm"
                        icon="heroicon-m-clipboard-document-check"
                        class="w-full"
                    >
                        Persetujuan SPPD ({{ $d['pendingSppd'] }})
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\TransportAccommodationApprovalResource::getUrl('index')"
                        color="success"
                        size="sm"
                        icon="heroicon-m-check-badge"
                        class="w-full"
                    >
                        Persetujuan Rekap ({{ $d['pendingRecap'] }})
                    </x-filament::button>
                @else
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\TransportAccommodationRecapResource::getUrl('index')"
                        color="info"
                        size="sm"
                        icon="heroicon-m-ticket"
                        class="w-full"
                    >
                        Rekap Tiket &amp; Akomodasi
                    </x-filament::button>

                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Admin\Resources\OfficialTravelRecapResource::getUrl('index')"
                        color="success"
                        size="sm"
                        icon="heroicon-m-document-chart-bar"
                        class="w-full"
                    >
                        Rekap Perjalanan Dinas
                    </x-filament::button>
                @endif

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Pages\SignatureSettingsPage::getUrl()"
                    :color="$d['hasSignature'] ? 'teal' : 'danger'"
                    size="sm"
                    icon="heroicon-m-pencil-square"
                    class="w-full"
                >
                    {{ $d['hasSignature'] ? 'Pengaturan Tanda Tangan' : 'Atur Tanda Tangan' }}
                </x-filament::button>
            </x-filament::grid>
        @elseif ($d['isDivisionHead'])
            <x-filament::grid
                :default="1"
                :sm="2"
                :xl="3"
                class="gap-3"
                style="gap: 0.75rem;"
            >
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\TransportAccommodationApprovalResource::getUrl('index')"
                    :color="$d['pendingTransportRecap'] > 0 ? 'warning' : 'success'"
                    size="sm"
                    icon="heroicon-m-ticket"
                    class="w-full"
                >
                    Persetujuan Rekap Tiket ({{ $d['pendingTransportRecap'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\OfficialTravelApprovalResource::getUrl('index')"
                    :color="$d['pendingTravelRecap'] > 0 ? 'warning' : 'info'"
                    size="sm"
                    icon="heroicon-m-document-chart-bar"
                    class="w-full"
                >
                    Persetujuan Rekap Dinas ({{ $d['pendingTravelRecap'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Pages\SignatureSettingsPage::getUrl()"
                    :color="$d['hasSignature'] ? 'teal' : 'danger'"
                    size="sm"
                    icon="heroicon-m-pencil-square"
                    class="w-full"
                >
                    {{ $d['hasSignature'] ? 'Pengaturan Tanda Tangan' : 'Atur Tanda Tangan' }}
                </x-filament::button>
            </x-filament::grid>
        @elseif ($d['isManager'])
            <x-filament::grid
                :default="1"
                :sm="2"
                :lg="3"
                class="gap-3"
                style="gap: 0.75rem;"
            >
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\MemoApprovalResource::getUrl('index')"
                    color="primary"
                    size="sm"
                    icon="heroicon-m-document-check"
                    class="w-full"
                >
                    Persetujuan Memo ({{ $d['pendingMemo'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\SppdApprovalResource::getUrl('index')"
                    color="info"
                    size="sm"
                    icon="heroicon-m-clipboard-document-check"
                    class="w-full"
                >
                    Persetujuan SPPD ({{ $d['pendingSppd'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\SppdCostApprovalResource::getUrl('index')"
                    color="danger"
                    size="sm"
                    icon="heroicon-m-calculator"
                    class="w-full"
                >
                    Persetujuan Biaya SPPD ({{ $d['pendingSppdCost'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\TransportAccommodationApprovalResource::getUrl('index')"
                    color="success"
                    size="sm"
                    icon="heroicon-m-ticket"
                    class="w-full"
                >
                    Rekap Tiket &amp; Akomodasi ({{ $d['pendingTransportRecap'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\OfficialTravelApprovalResource::getUrl('index')"
                    color="indigo"
                    size="sm"
                    icon="heroicon-m-document-chart-bar"
                    class="w-full"
                >
                    Rekap Perjalanan Dinas ({{ $d['pendingTravelRecap'] }})
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Pages\SignatureSettingsPage::getUrl()"
                    :color="$d['hasSignature'] ? 'teal' : 'danger'"
                    size="sm"
                    icon="heroicon-m-pencil-square"
                    class="w-full"
                >
                    {{ $d['hasSignature'] ? 'Pengaturan Tanda Tangan' : 'Atur Tanda Tangan' }}
                </x-filament::button>
            </x-filament::grid>
        @elseif ($d['isSupervisor'])
            <x-filament::grid
                :default="1"
                :sm="2"
                class="gap-3"
                style="gap: 0.75rem;"
            >
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\SppdApprovalResource::getUrl('index')"
                    :color="$d['pendingSppd'] > 0 ? 'warning' : 'info'"
                    size="sm"
                    icon="heroicon-m-clipboard-document-check"
                    class="w-full"
                >
                    Verifikasi Persetujuan SPPD ({{ $d['pendingSppd'] }} Menunggu)
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Pages\SignatureSettingsPage::getUrl()"
                    :color="$d['hasSignature'] ? 'teal' : 'danger'"
                    size="sm"
                    icon="heroicon-m-pencil-square"
                    class="w-full"
                >
                    {{ $d['hasSignature'] ? 'Pengaturan Tanda Tangan' : 'Atur Tanda Tangan (Wajib 1x)' }}
                </x-filament::button>
            </x-filament::grid>
        @else
            <x-filament::grid
                :default="1"
                :sm="3"
                class="gap-3"
                style="gap: 0.75rem;"
            >
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Resources\SppdResource::getUrl('index')"
                    :color="$d['draftSppdForStaff'] > 0 ? 'warning' : 'primary'"
                    size="sm"
                    icon="heroicon-m-paper-airplane"
                    class="w-full"
                >
                    SPPD Saya
                    @if ($d['draftSppdForStaff'] > 0)
                        ({{ $d['draftSppdForStaff'] }} Perlu Dilengkapi)
                    @endif
                </x-filament::button>

                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Admin\Pages\SignatureSettingsPage::getUrl()"
                    :color="$d['hasSignature'] ? 'info' : 'danger'"
                    size="sm"
                    icon="heroicon-m-pencil-square"
                    class="w-full"
                >
                    {{ $d['hasSignature'] ? 'Pengaturan Tanda Tangan' : 'Atur Tanda Tangan (Wajib 1x)' }}
                </x-filament::button>
            </x-filament::grid>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
