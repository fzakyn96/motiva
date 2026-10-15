<x-filament-panels::page>
    <div class="space-y-8 w-full">
        {{-- Status Alert Banner (Prominent, High-Contrast & Legible) --}}
        @if ($memo->status === 'pending')
            <div class="rounded-2xl border-2 border-amber-400/80 bg-amber-50/90 p-5 shadow-sm dark:border-amber-500/40 dark:bg-amber-950/30">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-amber-500/20 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400">
                            <x-heroicon-o-clock class="h-7 w-7" />
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h3 class="text-lg font-bold text-amber-950 dark:text-amber-200">
                                    Menunggu Review & Persetujuan Manajer
                                </h3>
                                <x-filament::badge color="warning" size="md">
                                    Pending Review
                                </x-filament::badge>
                            </div>
                            <p class="mt-1 text-sm font-medium text-amber-800/90 dark:text-amber-300/90">
                                Memo internal ini diajukan dan memerlukan peninjauan serta pengesahan Manajer sebelum surat tugas resmi dapat diproses.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($memo->status === 'approved')
            <div class="rounded-2xl border-2 border-emerald-400/80 bg-emerald-50/90 p-5 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-950/30">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400">
                            <x-heroicon-o-check-badge class="h-7 w-7" />
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h3 class="text-lg font-bold text-emerald-950 dark:text-emerald-200">
                                    Memo Telah Disetujui
                                </h3>
                                <x-filament::badge color="success" size="md">
                                    Disetujui (Approved)
                                </x-filament::badge>
                            </div>
                            <p class="mt-1 text-sm font-medium text-emerald-800/90 dark:text-emerald-300/90">
                                Dokumen memo ini telah disahkan secara resmi. Surat penugasan dan dokumen SPPD peserta telah aktif dan siap diproses.
                            </p>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <x-filament::button
                            tag="a"
                            href="{{ route('memos.print', $memo) }}"
                            target="_blank"
                            color="info"
                            icon="heroicon-o-printer"
                            size="md"
                        >
                            Cetak Dokumen PDF
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @elseif ($memo->status === 'rejected')
            @php
                $rejectStep = $approvalRequest?->steps->where('status', 'rejected')->last();
            @endphp
            <div class="rounded-2xl border-2 border-rose-400/80 bg-rose-50/90 p-5 shadow-sm dark:border-rose-500/40 dark:bg-rose-950/30">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-rose-500/20 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400">
                        <x-heroicon-o-x-circle class="h-7 w-7" />
                    </div>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h3 class="text-lg font-bold text-rose-950 dark:text-rose-200">
                                Pengajuan Memo Ditolak
                            </h3>
                            <x-filament::badge color="danger" size="md">
                                Ditolak (Rejected)
                            </x-filament::badge>
                        </div>
                        <p class="mt-1 text-sm font-medium text-rose-800/90 dark:text-rose-300/90">
                            Pengajuan memo ini telah ditolak oleh pemeriksa.
                        </p>
                        @if ($rejectStep && $rejectStep->notes)
                            <div class="mt-3 rounded-xl border border-rose-300 bg-white/80 p-3.5 text-sm font-medium text-rose-900 shadow-xs dark:border-rose-800/60 dark:bg-rose-900/30 dark:text-rose-200">
                                <span class="font-bold">Alasan Penolakan:</span> "{{ $rejectStep->notes }}"
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Official Memo Sheet Container --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-10">
            {{-- Document Header Pertamina --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b-2 border-gray-900 pb-6 dark:border-gray-700">
                <div>
                    <span class="text-2xl font-light italic tracking-tight text-gray-600 dark:text-gray-300">
                        Energy for Life
                    </span>
                </div>
                <div class="text-left sm:text-right">
                    <div class="text-2xl font-black tracking-widest text-red-600 dark:text-red-500">
                        PERTAMINA
                    </div>
                    <div class="text-sm font-extrabold tracking-wider text-gray-900 dark:text-gray-200">
                        GAS NEGARA
                    </div>
                </div>
            </div>

            {{-- Title & Memo Number Section --}}
            <div class="mt-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-black tracking-wider text-gray-950 dark:text-white">
                        MEMO
                    </h1>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Nomor:
                        </span>
                        <span class="rounded-lg bg-gray-100 px-3 py-1 font-mono text-sm font-bold text-gray-900 border border-gray-300 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700">
                            {{ $memo->nomor_memo ?? '-' }}
                        </span>
                    </div>
                </div>
                <div class="text-left sm:text-right">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 block">
                        Tanggal Terbit
                    </span>
                    <span class="mt-1 inline-flex items-center gap-1.5 text-base font-bold text-gray-900 dark:text-gray-100">
                        <x-heroicon-m-calendar class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                        {{ $memo->date ? \Carbon\Carbon::parse($memo->date)->locale('id')->translatedFormat('l, d F Y') : '-' }}
                    </span>
                </div>
            </div>

            {{-- Structured Information Cards Grid (High Legibility & Modern Aesthetic) --}}
            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Kepada --}}
                <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-5 dark:border-gray-800 dark:bg-gray-800/40">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <x-heroicon-m-user class="h-4 w-4 text-primary-500" />
                        Kepada (Yth)
                    </div>
                    <div class="mt-2 text-base font-bold text-gray-950 dark:text-white">
                        {{ $memo->to_recipient }}
                    </div>
                </div>

                {{-- Dari (Pengaju) --}}
                <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-5 dark:border-gray-800 dark:bg-gray-800/40">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <x-heroicon-m-user-circle class="h-4 w-4 text-emerald-500" />
                        Dari (Pengaju)
                    </div>
                    <div class="mt-2 text-base font-bold text-gray-950 dark:text-white">
                        {{ $memo->creator?->name ?? 'Administrator' }}
                        @if ($memo->creator?->position)
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                &bull; {{ $memo->creator->position->name }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Unit Kerja / Departemen --}}
                <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-5 dark:border-gray-800 dark:bg-gray-800/40">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <x-heroicon-m-building-office-2 class="h-4 w-4 text-amber-500" />
                        Unit Kerja / Departemen
                    </div>
                    <div class="mt-2 text-base font-bold text-gray-950 dark:text-white">
                        {{ $memo->department?->name ?? ($memo->creator?->department?->name ?? '-') }}
                    </div>
                </div>

                {{-- Perihal / Agenda --}}
                <div class="rounded-xl border border-primary-200 bg-primary-50/60 p-5 dark:border-primary-800/50 dark:bg-primary-950/20">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-primary-700 dark:text-primary-400">
                        <x-heroicon-m-tag class="h-4 w-4" />
                        Perihal / Agenda
                    </div>
                    <div class="mt-2 text-base font-extrabold text-primary-900 dark:text-primary-200">
                        {{ $memo->agenda ?? ($memo->title ?? '-') }}
                    </div>
                </div>
            </div>

            {{-- Uraian Isi Memo --}}
            <div class="mt-8 border-t border-gray-200 pt-6 dark:border-gray-800">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-2 mb-3">
                    <x-heroicon-m-document-text class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                    Uraian Isi Memo
                </h3>
                <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-6 dark:border-gray-800 dark:bg-gray-800/30">
                    <div class="prose dark:prose-invert max-w-none text-base leading-relaxed text-gray-900 dark:text-gray-100">
                        {!! $memo->content !!}
                    </div>
                </div>
            </div>

            {{-- Rincian Pelaksanaan Penugasan (If applicable) --}}
            @if ($memo->assignment_start_date || $memo->location || !empty($participantsList))
                <div class="mt-8 border-t border-gray-200 pt-6 dark:border-gray-800">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2 mb-4">
                        <x-heroicon-m-briefcase class="h-5 w-5 text-primary-500" />
                        Rincian Pelaksanaan Tugas Lapangan
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if ($memo->assignment_start_date)
                            <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-gray-800/40">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-calendar-days class="h-5 w-5 text-primary-500" />
                                    Waktu Penugasan
                                </div>
                                <div class="mt-2 text-base font-bold text-gray-950 dark:text-white">
                                    {{ \Carbon\Carbon::parse($memo->assignment_start_date)->locale('id')->translatedFormat('d F Y') }}
                                    @if ($memo->assignment_end_date && $memo->assignment_start_date != $memo->assignment_end_date)
                                        s/d {{ \Carbon\Carbon::parse($memo->assignment_end_date)->locale('id')->translatedFormat('d F Y') }}
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($memo->location)
                            <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-gray-800/40">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-map-pin class="h-5 w-5 text-rose-500" />
                                    Tempat / Lokasi Kegiatan
                                </div>
                                <div class="mt-2 text-base font-bold text-gray-950 dark:text-white">
                                    {{ $memo->location }}
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Participants Table --}}
                    @if (!empty($participantsList))
                        <div class="mt-6">
                            <div class="flex items-center gap-2 mb-3">
                                <x-heroicon-m-users class="h-5 w-5 text-primary-500" />
                                <span class="text-sm font-bold text-gray-900 dark:text-white">
                                    Daftar Pegawai yang Ditugaskan ({{ count($participantsList) }} Orang)
                                </span>
                            </div>
                            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800 shadow-2xs">
                                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-gray-800">
                                    <thead class="bg-gray-100/80 dark:bg-gray-800">
                                        <tr>
                                            <th class="w-16 px-4 py-3.5 font-bold text-gray-800 dark:text-gray-200">No</th>
                                            <th class="px-5 py-3.5 font-bold text-gray-800 dark:text-gray-200">Nama Pegawai & NIP</th>
                                            <th class="px-5 py-3.5 font-bold text-gray-800 dark:text-gray-200">Jabatan & Unit Kerja</th>
                                            <th class="px-5 py-3.5 font-bold text-gray-800 dark:text-gray-200">Dokumen SPPD</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900/60">
                                        @foreach ($participantsList as $index => $item)
                                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/50 transition-colors">
                                                <td class="px-4 py-4 font-bold text-gray-500 dark:text-gray-400">
                                                    {{ $index + 1 }}
                                                </td>
                                                <td class="px-5 py-4">
                                                    <div class="text-base font-bold text-gray-950 dark:text-white">
                                                        {{ $item['employee']->name }}
                                                    </div>
                                                    <div class="mt-0.5 inline-block rounded bg-gray-100 px-2 py-0.5 font-mono text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                                        NIP: {{ $item['employee']->identity_number }}
                                                    </div>
                                                </td>
                                                <td class="px-5 py-4">
                                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        {{ $item['employee']->position?->name ?? 'Staff' }}
                                                    </div>
                                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-0.5">
                                                        {{ $item['employee']->department?->name ?? '-' }}
                                                    </div>
                                                </td>
                                                <td class="px-5 py-4">
                                                    @if ($item['sppd'])
                                                        <x-filament::badge color="info" icon="heroicon-m-document-text" size="md">
                                                            {{ $item['sppd']->sppd_number }}
                                                        </x-filament::badge>
                                                    @else
                                                        <span class="text-xs text-gray-400 italic">Otomatis dibuat saat disetujui</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Jejak / Riwayat Persetujuan Section --}}
        @if ($approvalRequest && $approvalRequest->steps->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-8">
                <h3 class="text-lg font-bold text-gray-950 dark:text-white flex items-center gap-2 mb-6">
                    <x-heroicon-o-shield-check class="h-6 w-6 text-primary-500" />
                    Riwayat & Jejak Persetujuan
                </h3>

                <div class="space-y-6">
                    {{-- Step: Pembuatan --}}
                    <div class="flex items-start gap-4">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950/80 dark:text-emerald-400 ring-4 ring-emerald-50 dark:ring-emerald-950/30">
                            <x-heroicon-m-check class="h-4 w-4" />
                        </div>
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="text-base font-bold text-gray-900 dark:text-white">
                                    Dokumen Memo Diajukan
                                </span>
                                <x-filament::badge color="success" size="sm">Diajukan</x-filament::badge>
                            </div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">
                                Diajukan oleh <span class="font-bold text-gray-800 dark:text-gray-200">{{ $memo->creator?->name ?? 'Pembuat' }}</span> &bull; {{ $memo->created_at ? \Carbon\Carbon::parse($memo->created_at)->locale('id')->translatedFormat('d F Y, H:i') : '-' }} WIB
                            </p>
                        </div>
                    </div>

                    {{-- Step: Persetujuan Manajer --}}
                    @foreach ($approvalRequest->steps as $step)
                        <div class="flex items-start gap-4">
                            @if ($step->status === 'approved')
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950/80 dark:text-emerald-400 ring-4 ring-emerald-50 dark:ring-emerald-950/30">
                                    <x-heroicon-m-check class="h-4 w-4" />
                                </div>
                            @elseif ($step->status === 'rejected')
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950/80 dark:text-rose-400 ring-4 ring-rose-50 dark:ring-rose-950/30">
                                    <x-heroicon-m-x-mark class="h-4 w-4" />
                                </div>
                            @else
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-950/80 dark:text-amber-400 ring-4 ring-amber-50 dark:ring-amber-950/30">
                                    <x-heroicon-m-clock class="h-4 w-4" />
                                </div>
                            @endif

                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <span class="text-base font-bold text-gray-900 dark:text-white">
                                        {{ $step->policyStep?->step_name ?? 'Persetujuan Manajer' }}
                                    </span>
                                    @if ($step->status === 'approved')
                                        <x-filament::badge color="success" size="sm">Disetujui</x-filament::badge>
                                    @elseif ($step->status === 'rejected')
                                        <x-filament::badge color="danger" size="sm">Ditolak</x-filament::badge>
                                    @else
                                        <x-filament::badge color="warning" size="sm">Menunggu Persetujuan</x-filament::badge>
                                    @endif
                                </div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">
                                    @if ($step->approver)
                                        Pemeriksa: <span class="font-bold text-gray-800 dark:text-gray-200">{{ $step->approver->name }}</span> ({{ $step->approver->position?->name ?? 'Manajer' }})
                                    @else
                                        Pemeriksa: <span class="font-bold text-gray-800 dark:text-gray-200">Manajer Unit Kerja Terkait</span>
                                    @endif
                                    @if ($step->updated_at && $step->status !== 'pending')
                                        &bull; {{ \Carbon\Carbon::parse($step->updated_at)->locale('id')->translatedFormat('d F Y, H:i') }} WIB
                                    @endif
                                </p>
                                @if ($step->notes)
                                    <div class="mt-2.5 rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                        <span class="font-bold">Catatan / Alasan:</span> {{ $step->notes }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Bottom Action Decision Bar (Spacious & Clear) --}}
        @if ($canApprove && $memo->status === 'pending')
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white/95 p-5 shadow-lg backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
                <div>
                    <h4 class="text-base font-bold text-gray-950 dark:text-white">
                        Keputusan Persetujuan Manajer
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Tentukan persetujuan untuk pengajuan memo internal ini.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <x-filament::button
                        wire:click="mountAction('reject')"
                        color="danger"
                        icon="heroicon-o-x-circle"
                        size="md"
                    >
                        Tolak Memo
                    </x-filament::button>

                    <x-filament::button
                        wire:click="mountAction('approve')"
                        color="success"
                        icon="heroicon-o-check-circle"
                        size="md"
                    >
                        Setujui Memo
                    </x-filament::button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
