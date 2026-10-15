<x-filament-panels::page>
    <div class="space-y-6 max-w-5xl mx-auto">
        {{-- Status Alert Banner (Clean & Simple) --}}
        @if ($memo->status === 'pending')
            <div class="flex items-center justify-between rounded-xl border border-amber-300/80 bg-amber-50/80 px-5 py-3.5 dark:border-amber-500/30 dark:bg-amber-500/10">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/20 text-amber-600 dark:text-amber-400">
                        <x-heroicon-m-clock class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                                Menunggu Review & Persetujuan Manajer
                            </span>
                            <x-filament::badge color="warning" size="sm">Pending</x-filament::badge>
                        </div>
                        <p class="text-xs text-amber-700/90 dark:text-amber-300/80 mt-0.5">
                            Silakan periksa rincian memo dan agenda di bawah sebelum memberikan persetujuan.
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($memo->status === 'approved')
            <div class="flex items-center justify-between rounded-xl border border-emerald-300/80 bg-emerald-50/80 px-5 py-3.5 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">
                        <x-heroicon-m-check-badge class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">
                                Memo Telah Disetujui
                            </span>
                            <x-filament::badge color="success" size="sm">Approved</x-filament::badge>
                        </div>
                        <p class="text-xs text-emerald-700/90 dark:text-emerald-300/80 mt-0.5">
                            Memo ini telah disahkan secara resmi dan surat tugas/SPPD terkait telah siap diproses.
                        </p>
                    </div>
                </div>
                <div>
                    <x-filament::button
                        tag="a"
                        href="{{ route('memos.print', $memo) }}"
                        target="_blank"
                        color="info"
                        icon="heroicon-o-printer"
                        size="sm"
                    >
                        Cetak PDF
                    </x-filament::button>
                </div>
            </div>
        @elseif ($memo->status === 'rejected')
            @php
                $rejectStep = $approvalRequest?->steps->where('status', 'rejected')->last();
            @endphp
            <div class="rounded-xl border border-rose-300/80 bg-rose-50/80 p-4 dark:border-rose-500/30 dark:bg-rose-500/10">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-500/20 text-rose-600 dark:text-rose-400">
                        <x-heroicon-m-x-circle class="h-5 w-5" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-rose-900 dark:text-rose-200">
                                Memo Ditolak
                            </span>
                            <x-filament::badge color="danger" size="sm">Rejected</x-filament::badge>
                        </div>
                        @if ($rejectStep && $rejectStep->notes)
                            <p class="text-xs text-rose-700 dark:text-rose-300/90 mt-1">
                                <span class="font-semibold">Alasan Penolakan:</span> "{{ $rejectStep->notes }}"
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Official Memo Sheet (Clean & Informative) --}}
        <x-filament::section>
            {{-- Header Pertamina --}}
            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-white/10">
                <span class="text-lg italic font-light text-gray-500 dark:text-gray-400">Energy for Life</span>
                <div class="text-right">
                    <div class="text-base font-black tracking-wider text-red-600 dark:text-red-500">PERTAMINA</div>
                    <div class="text-xs font-bold tracking-tight text-gray-700 dark:text-gray-300">GAS NEGARA</div>
                </div>
            </div>

            {{-- Title & Memo Number --}}
            <div class="mt-4 flex flex-col sm:flex-row sm:items-baseline justify-between gap-2 pb-4 border-b border-gray-200 dark:border-white/10">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-wide text-gray-900 dark:text-white">
                        MEMO
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Nomor: <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ $memo->nomor_memo ?? '-' }}</span>
                    </p>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Tanggal:</span>
                    {{ $memo->date ? \Carbon\Carbon::parse($memo->date)->locale('id')->translatedFormat('l, d F Y') : '-' }}
                </div>
            </div>

            {{-- Structured Memo Information Table --}}
            <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10">
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10 text-xs sm:text-sm">
                        <tr class="bg-gray-50/50 dark:bg-white/[0.02]">
                            <td class="w-36 sm:w-44 px-4 py-2.5 font-medium text-gray-500 dark:text-gray-400">Kepada (Yth)</td>
                            <td class="w-4 py-2.5 text-gray-400">:</td>
                            <td class="px-4 py-2.5 font-semibold text-gray-900 dark:text-white">{{ $memo->to_recipient }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 font-medium text-gray-500 dark:text-gray-400">Dari (Pengaju)</td>
                            <td class="py-2.5 text-gray-400">:</td>
                            <td class="px-4 py-2.5 font-semibold text-gray-900 dark:text-white">
                                {{ $memo->creator?->name ?? 'Administrator' }}
                                @if ($memo->creator?->position)
                                    <span class="font-normal text-gray-500 dark:text-gray-400 text-xs">
                                        ({{ $memo->creator->position->name }})
                                    </span>
                                @endif
                            </td>
                        </tr>
                        <tr class="bg-gray-50/50 dark:bg-white/[0.02]">
                            <td class="px-4 py-2.5 font-medium text-gray-500 dark:text-gray-400">Unit Kerja / Dept</td>
                            <td class="py-2.5 text-gray-400">:</td>
                            <td class="px-4 py-2.5 text-gray-900 dark:text-white">
                                {{ $memo->department?->name ?? ($memo->creator?->department?->name ?? '-') }}
                            </td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 font-medium text-gray-500 dark:text-gray-400">Perihal / Agenda</td>
                            <td class="py-2.5 text-gray-400">:</td>
                            <td class="px-4 py-2.5 font-bold text-primary-600 dark:text-primary-400">
                                {{ $memo->agenda ?? ($memo->title ?? '-') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Content Section --}}
            <div class="mt-6">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">
                    Uraian Isi Memo
                </h3>
                <div class="prose max-w-none text-sm sm:text-base leading-relaxed text-gray-800 dark:prose-invert dark:text-gray-200">
                    {!! $memo->content !!}
                </div>
            </div>

            {{-- Assignment Details & Participants (If Applicable) --}}
            @if ($memo->assignment_start_date || $memo->location || !empty($participantsList))
                <div class="mt-6 pt-5 border-t border-gray-200 dark:border-white/10">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-3 flex items-center gap-1.5">
                        <x-heroicon-m-clipboard-document-check class="h-4 w-4" />
                        Rincian Pelaksanaan Penugasan
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs sm:text-sm">
                        @if ($memo->assignment_start_date)
                            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02] p-3">
                                <span class="text-xs text-gray-500 dark:text-gray-400 block font-medium">Waktu Penugasan</span>
                                <span class="font-semibold text-gray-900 dark:text-white mt-0.5 block">
                                    {{ \Carbon\Carbon::parse($memo->assignment_start_date)->locale('id')->translatedFormat('d F Y') }}
                                    @if ($memo->assignment_end_date && $memo->assignment_start_date != $memo->assignment_end_date)
                                        s/d {{ \Carbon\Carbon::parse($memo->assignment_end_date)->locale('id')->translatedFormat('d F Y') }}
                                    @endif
                                </span>
                            </div>
                        @endif

                        @if ($memo->location)
                            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02] p-3">
                                <span class="text-xs text-gray-500 dark:text-gray-400 block font-medium">Tempat / Lokasi Kegiatan</span>
                                <span class="font-semibold text-gray-900 dark:text-white mt-0.5 block">
                                    {{ $memo->location }}
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Participants Table --}}
                    @if (!empty($participantsList))
                        <div class="mt-4">
                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 block mb-2">
                                Daftar Pegawai yang Ditugaskan:
                            </span>
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                                <table class="w-full text-left text-xs sm:text-sm divide-y divide-gray-200 dark:divide-white/10">
                                    <thead class="bg-gray-50 dark:bg-white/5">
                                        <tr>
                                            <th class="w-12 px-3 py-2.5 font-semibold text-gray-700 dark:text-gray-300">No</th>
                                            <th class="px-4 py-2.5 font-semibold text-gray-700 dark:text-gray-300">Nama Pegawai & NIP</th>
                                            <th class="px-4 py-2.5 font-semibold text-gray-700 dark:text-gray-300">Jabatan & Unit</th>
                                            <th class="px-4 py-2.5 font-semibold text-gray-700 dark:text-gray-300">Dokumen SPPD</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                        @foreach ($participantsList as $index => $item)
                                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                                                <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                                <td class="px-4 py-2.5">
                                                    <div class="font-bold text-gray-900 dark:text-white">{{ $item['employee']->name }}</div>
                                                    <div class="text-[11px] font-mono text-gray-500 dark:text-gray-400">NIP: {{ $item['employee']->identity_number }}</div>
                                                </td>
                                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                                    <div>{{ $item['employee']->position?->name ?? 'Staff' }}</div>
                                                    <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $item['employee']->department?->name ?? '-' }}</div>
                                                </td>
                                                <td class="px-4 py-2.5">
                                                    @if ($item['sppd'])
                                                        <x-filament::badge color="info" icon="heroicon-m-document-text" size="sm">
                                                            {{ $item['sppd']->sppd_number }}
                                                        </x-filament::badge>
                                                    @else
                                                        <span class="text-xs text-gray-400 italic">Otomatis dibuat</span>
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
        </x-filament::section>

        {{-- Jejak / Riwayat Persetujuan (Clean & Simple Timeline) --}}
        @if ($approvalRequest && $approvalRequest->steps->isNotEmpty())
            <x-filament::section icon="heroicon-o-shield-check">
                <x-slot name="heading">
                    Riwayat & Jejak Persetujuan
                </x-slot>

                <div class="space-y-4 pt-1">
                    {{-- Step: Pembuatan --}}
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 mt-0.5">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                                <x-heroicon-m-check class="h-3.5 w-3.5" />
                            </span>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">
                                    Dokumen Memo Diajukan
                                </span>
                                <x-filament::badge color="success" size="xs">Diajukan</x-filament::badge>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Oleh {{ $memo->creator?->name ?? 'Pembuat' }} &bull; {{ $memo->created_at ? \Carbon\Carbon::parse($memo->created_at)->locale('id')->translatedFormat('d M Y, H:i') : '-' }}
                            </p>
                        </div>
                    </div>

                    {{-- Step: Persetujuan --}}
                    @foreach ($approvalRequest->steps as $step)
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 mt-0.5">
                                @if ($step->status === 'approved')
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                                        <x-heroicon-m-check class="h-3.5 w-3.5" />
                                    </span>
                                @elseif ($step->status === 'rejected')
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400">
                                        <x-heroicon-m-x-mark class="h-3.5 w-3.5" />
                                    </span>
                                @else
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400">
                                        <x-heroicon-m-clock class="h-3.5 w-3.5" />
                                    </span>
                                @endif
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $step->policyStep?->step_name ?? 'Persetujuan Manajer' }}
                                    </span>
                                    @if ($step->status === 'approved')
                                        <x-filament::badge color="success" size="xs">Disetujui</x-filament::badge>
                                    @elseif ($step->status === 'rejected')
                                        <x-filament::badge color="danger" size="xs">Ditolak</x-filament::badge>
                                    @else
                                        <x-filament::badge color="warning" size="xs">Menunggu</x-filament::badge>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    @if ($step->approver)
                                        Pemeriksa: {{ $step->approver->name }} ({{ $step->approver->position?->name ?? 'Manajer' }})
                                    @else
                                        Pemeriksa: Manajer Terkait
                                    @endif
                                    @if ($step->updated_at && $step->status !== 'pending')
                                        &bull; {{ \Carbon\Carbon::parse($step->updated_at)->locale('id')->translatedFormat('d M Y, H:i') }}
                                    @endif
                                </p>
                                @if ($step->notes)
                                    <div class="mt-1.5 rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02] p-2 text-xs text-gray-700 dark:text-gray-300">
                                        <span class="font-medium text-gray-900 dark:text-white">Catatan:</span> {{ $step->notes }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Bottom Action Footer if Pending (Clean, Non-Intrusive) --}}
        @if ($canApprove && $memo->status === 'pending')
            <div class="flex items-center justify-between rounded-xl border border-gray-200 dark:border-white/10 bg-white/80 dark:bg-gray-900/80 p-4 backdrop-blur shadow-sm">
                <span class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-300">
                    Aksi Keputusan Manajer
                </span>
                <div class="flex items-center gap-2">
                    <x-filament::button
                        wire:click="mountAction('reject')"
                        color="danger"
                        icon="heroicon-o-x-circle"
                        size="sm"
                    >
                        Tolak Memo
                    </x-filament::button>

                    <x-filament::button
                        wire:click="mountAction('approve')"
                        color="success"
                        icon="heroicon-o-check-circle"
                        size="sm"
                    >
                        Setujui Memo
                    </x-filament::button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
