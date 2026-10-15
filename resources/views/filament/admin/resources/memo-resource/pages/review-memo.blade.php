<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Status Alert Banner --}}
        @if ($memo->status === 'pending')
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-amber-100 p-2 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400">
                        <x-heroicon-o-clock class="h-6 w-6" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-amber-900 dark:text-amber-200">
                                Status: Menunggu Review & Persetujuan Manajer
                            </h3>
                            <span class="inline-flex items-center rounded-md bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">
                                Pending Review
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-amber-700 dark:text-amber-300/80">
                            Memo ini diajukan dan membutuhkan tinjauan dari Manajer Unit Kerja. Mohon telaah rincian agenda, peserta, dan tujuan penugasan sebelum mengambil keputusan persetujuan.
                        </p>
                        @if ($canApprove)
                            <div class="mt-3 flex flex-wrap gap-2">
                                <x-filament::button
                                    wire:click="mountAction('approve')"
                                    color="success"
                                    icon="heroicon-o-check-circle"
                                    size="sm"
                                >
                                    Setujui Memo Ini
                                </x-filament::button>

                                <x-filament::button
                                    wire:click="mountAction('reject')"
                                    color="danger"
                                    icon="heroicon-o-x-circle"
                                    size="sm"
                                >
                                    Tolak Pengajuan
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @elseif ($memo->status === 'approved')
            <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/40">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-emerald-100 p-2 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400">
                        <x-heroicon-o-check-badge class="h-6 w-6" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-emerald-900 dark:text-emerald-200">
                                Status: Memo Telah Disetujui
                            </h3>
                            <span class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                                Disetujui (Approved)
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-300/80">
                            Memo ini telah disahkan secara resmi. Nomor surat dokumen telah diterbitkan dan surat tugas/SPPD terkait telah siap diproses.
                        </p>
                        <div class="mt-3">
                            <x-filament::button
                                tag="a"
                                href="{{ route('memos.print', $memo) }}"
                                target="_blank"
                                color="info"
                                icon="heroicon-o-printer"
                                size="sm"
                            >
                                Cetak Lembar Memo Resmi (PDF)
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($memo->status === 'rejected')
            <div class="rounded-xl border border-rose-300 bg-rose-50 p-4 dark:border-rose-800 dark:bg-rose-950/40">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-rose-100 p-2 text-rose-600 dark:bg-rose-900/50 dark:text-rose-400">
                        <x-heroicon-o-x-circle class="h-6 w-6" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-rose-900 dark:text-rose-200">
                                Status: Memo Ditolak
                            </h3>
                            <span class="inline-flex items-center rounded-md bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-900/60 dark:text-rose-300">
                                Ditolak (Rejected)
                            </span>
                        </div>
                        @php
                            $rejectStep = $approvalRequest?->steps->where('status', 'rejected')->last();
                        @endphp
                        <p class="mt-1 text-sm text-rose-700 dark:text-rose-300/80">
                            Pengajuan memo ini telah ditolak oleh pemeriksa.
                            @if ($rejectStep && $rejectStep->notes)
                                <br><span class="font-semibold">Catatan / Alasan Penolakan:</span> "{{ $rejectStep->notes }}"
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Official Memo Document Sheet (Review Sheet) --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-10">
            {{-- Document Header --}}
            <div class="flex flex-col justify-between gap-4 border-b-2 border-gray-900 pb-5 sm:flex-row sm:items-center dark:border-gray-100">
                <div>
                    <span class="text-xl font-light italic text-gray-700 dark:text-gray-300">Energy for Life</span>
                </div>
                <div class="text-left sm:text-right">
                    <h2 class="text-lg font-black tracking-wider text-red-600 dark:text-red-500">
                        PERTAMINA
                    </h2>
                    <h3 class="text-sm font-bold tracking-tight text-gray-800 dark:text-gray-200">
                        GAS NEGARA
                    </h3>
                </div>
            </div>

            {{-- Memo Title & Number --}}
            <div class="mt-6 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                        MEMORANDUM INTERNAL
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Nomor: <span class="font-mono font-bold text-gray-900 dark:text-gray-100">{{ $memo->nomor_memo ?? '-' }}</span>
                    </p>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-medium">Tanggal:</span> 
                    {{ $memo->date ? \Carbon\Carbon::parse($memo->date)->locale('id')->translatedFormat('l, d F Y') : '-' }}
                </div>
            </div>

            {{-- Metadata Grid --}}
            <div class="mt-6 rounded-lg bg-gray-50 p-4 border border-gray-100 dark:border-gray-800 dark:bg-gray-800/60">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Kepada (Yth)</div>
                        <div class="mt-1 text-sm font-bold text-gray-900 dark:text-gray-100">{{ $memo->to_recipient }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Dari (Pembuat)</div>
                        <div class="mt-1 text-sm font-bold text-gray-900 dark:text-gray-100">
                            {{ $memo->creator?->name ?? 'Administrator' }}
                            @if ($memo->creator?->position)
                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                                    ({{ $memo->creator->position->name }})
                                </span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Unit Kerja / Departemen</div>
                        <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $memo->department?->name ?? ($memo->creator?->department?->name ?? '-') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Perihal / Agenda</div>
                        <div class="mt-1 text-sm font-bold text-primary-600 dark:text-primary-400">
                            {{ $memo->agenda ?? ($memo->title ?? '-') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Memo Content --}}
            <div class="mt-8 border-t border-gray-100 pt-6 dark:border-gray-800">
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                    Isi & Uraian Memo
                </h4>
                <div class="prose max-w-none mt-3 text-gray-800 dark:prose-invert dark:text-gray-200 leading-relaxed">
                    {!! $memo->content !!}
                </div>
            </div>

            {{-- Assignment Details (If applicable) --}}
            @if ($memo->assignment_start_date || $memo->agenda || $memo->location || !empty($participantsList))
                <div class="mt-8 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800/40">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-clipboard-document-list class="h-5 w-5 text-primary-500" />
                        Rincian Penugasan & Pelaksanaan
                    </h3>
                    
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @if ($memo->assignment_start_date)
                            <div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Waktu Penugasan</span>
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ \Carbon\Carbon::parse($memo->assignment_start_date)->locale('id')->translatedFormat('d F Y') }}
                                    @if ($memo->assignment_end_date && $memo->assignment_start_date != $memo->assignment_end_date)
                                        s/d {{ \Carbon\Carbon::parse($memo->assignment_end_date)->locale('id')->translatedFormat('d F Y') }}
                                    @endif
                                </p>
                            </div>
                        @endif

                        @if ($memo->location)
                            <div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Tempat / Lokasi Kegiatan</span>
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $memo->location }}
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- Participants Table --}}
                    @if (!empty($participantsList))
                        <div class="mt-5">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                                Daftar Pegawai yang Ditugaskan
                            </h4>
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-left text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-2.5 font-semibold text-gray-700 dark:text-gray-300">No</th>
                                            <th class="px-4 py-2.5 font-semibold text-gray-700 dark:text-gray-300">Nama Pegawai & NIP</th>
                                            <th class="px-4 py-2.5 font-semibold text-gray-700 dark:text-gray-300">Jabatan & Unit</th>
                                            <th class="px-4 py-2.5 font-semibold text-gray-700 dark:text-gray-300">Dokumen SPPD</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                        @foreach ($participantsList as $index => $item)
                                            <tr>
                                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                                <td class="px-4 py-3">
                                                    <div class="font-bold text-gray-900 dark:text-gray-100">
                                                        {{ $item['employee']->name }}
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                                        NIP: {{ $item['employee']->identity_number }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                    <div>{{ $item['employee']->position?->name ?? 'Staff' }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $item['employee']->department?->name ?? '-' }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    @if ($item['sppd'])
                                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                                            <x-heroicon-m-document-text class="h-3.5 w-3.5" />
                                                            {{ $item['sppd']->sppd_number }}
                                                        </span>
                                                    @else
                                                        <span class="text-xs text-gray-400">Akan dibuat</span>
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

            {{-- Signature / Approval Endorsement Section --}}
            <div class="mt-10 grid grid-cols-1 gap-8 pt-8 border-t border-gray-200 dark:border-gray-800 sm:grid-cols-2">
                {{-- Creator Signature --}}
                <div class="text-left">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Diajukan Oleh
                    </p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ $memo->creator?->position?->name ?? 'Pembuat Dokumen' }}
                    </p>
                    <div class="my-6">
                        <span class="inline-block rounded border border-dashed border-gray-300 px-4 py-1.5 text-xs text-gray-400 dark:border-gray-700">
                            Ditandatangani secara digital
                        </span>
                    </div>
                    <p class="font-bold underline text-gray-900 dark:text-gray-100">
                        {{ $memo->creator?->name ?? '-' }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                        NIP. {{ $memo->creator?->identity_number ?? '-' }}
                    </p>
                </div>

                {{-- Approver / Manager Endorsement --}}
                <div class="text-left sm:text-right">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Persetujuan Manajer
                    </p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Manajer Unit Kerja Terkait
                    </p>
                    <div class="my-4">
                        @if ($memo->status === 'approved')
                            @php
                                $approvedStep = $approvalRequest?->steps->where('status', 'approved')->last();
                            @endphp
                            <div class="inline-block rounded-lg border-2 border-emerald-500 bg-emerald-50 px-4 py-2 text-left sm:text-right dark:bg-emerald-950/40">
                                <div class="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400 font-black tracking-widest text-xs uppercase">
                                    <x-heroicon-s-check-circle class="h-4 w-4" />
                                    DISETUJUI / APPROVED
                                </div>
                                <div class="text-xs font-semibold text-gray-800 dark:text-gray-200 mt-1">
                                    {{ $approvedStep?->approver?->name ?? 'Manajer' }}
                                </div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                    {{ $approvedStep?->updated_at ? \Carbon\Carbon::parse($approvedStep->updated_at)->locale('id')->translatedFormat('d M Y, H:i') : '' }}
                                </div>
                            </div>
                        @elseif ($memo->status === 'rejected')
                            <div class="inline-block rounded-lg border-2 border-rose-500 bg-rose-50 px-4 py-2 text-left sm:text-right dark:bg-rose-950/40">
                                <div class="flex items-center gap-1.5 text-rose-700 dark:text-rose-400 font-black tracking-widest text-xs uppercase">
                                    <x-heroicon-s-x-circle class="h-4 w-4" />
                                    DITOLAK / REJECTED
                                </div>
                            </div>
                        @else
                            <div class="inline-block rounded-lg border border-dashed border-amber-400 bg-amber-50/60 px-4 py-3 text-left sm:text-right dark:bg-amber-950/20">
                                <span class="text-xs font-semibold text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                                    <x-heroicon-o-clock class="h-4 w-4" />
                                    Menunggu Persetujuan
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Approval Trail / Riwayat Persetujuan --}}
        @if ($approvalRequest && $approvalRequest->steps->isNotEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2 mb-4">
                    <x-heroicon-o-shield-check class="h-5 w-5 text-primary-500" />
                    Jejak & Riwayat Persetujuan
                </h3>
                
                <div class="relative pl-6 space-y-6 before:absolute before:bottom-2 before:top-2 before:left-2.5 before:w-0.5 before:bg-gray-200 dark:before:bg-gray-800">
                    {{-- Step 0: Pembuatan --}}
                    <div class="relative">
                        <div class="absolute -left-6 mt-1 flex h-4 w-4 items-center justify-center rounded-full bg-emerald-500 ring-4 ring-white dark:ring-gray-900">
                            <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                Dokumen Memo Diajukan
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Oleh {{ $memo->creator?->name ?? 'Pembuat' }} &bull; {{ $memo->created_at ? \Carbon\Carbon::parse($memo->created_at)->locale('id')->translatedFormat('d M Y, H:i') : '-' }}
                            </p>
                        </div>
                    </div>

                    {{-- Step: Approval Steps --}}
                    @foreach ($approvalRequest->steps as $step)
                        <div class="relative">
                            @if ($step->status === 'approved')
                                <div class="absolute -left-6 mt-1 flex h-4 w-4 items-center justify-center rounded-full bg-emerald-500 ring-4 ring-white dark:ring-gray-900">
                                    <x-heroicon-s-check class="h-2.5 w-2.5 text-white" />
                                </div>
                            @elseif ($step->status === 'rejected')
                                <div class="absolute -left-6 mt-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 ring-4 ring-white dark:ring-gray-900">
                                    <x-heroicon-s-x-mark class="h-2.5 w-2.5 text-white" />
                                </div>
                            @else
                                <div class="absolute -left-6 mt-1 flex h-4 w-4 items-center justify-center rounded-full bg-amber-400 ring-4 ring-white dark:ring-gray-900">
                                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                </div>
                            @endif

                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $step->policyStep?->step_name ?? 'Persetujuan Manajer' }}
                                    </p>
                                    @if ($step->status === 'approved')
                                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            Disetujui
                                        </span>
                                    @elseif ($step->status === 'rejected')
                                        <span class="rounded bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">
                                            Ditolak
                                        </span>
                                    @else
                                        <span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                            Menunggu Tindakan
                                        </span>
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
                                    <div class="mt-2 rounded bg-gray-50 p-2 text-xs text-gray-700 border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        <span class="font-medium">Catatan:</span> {{ $step->notes }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Bottom Action Footer if Pending --}}
        @if ($canApprove && $memo->status === 'pending')
            <div class="sticky bottom-4 z-10 flex items-center justify-between rounded-xl border border-gray-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-semibold text-gray-900 dark:text-white">Keputusan Manajer:</span> Silakan setujui atau tolak memo ini.
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
