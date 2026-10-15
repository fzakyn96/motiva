<x-filament-panels::page>
    <div class="w-full space-y-6">
        {{-- Status Alert Banner (Pure Tailwind CSS) --}}
        @if ($memo->status === 'pending')
            <div class="rounded-2xl border border-amber-300 bg-amber-50/90 p-5 shadow-xs dark:border-amber-700/60 dark:bg-amber-950/40">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3.5">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-500/20 text-amber-600 dark:bg-amber-400/20 dark:text-amber-400">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h2 class="text-base sm:text-lg font-bold text-amber-950 dark:text-amber-200">
                                    Menunggu Review & Persetujuan Manajer
                                </h2>
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-100/80 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider text-amber-800 dark:border-amber-700 dark:bg-amber-900/60 dark:text-amber-300">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    Pending Review
                                </span>
                            </div>
                            <p class="mt-1 text-sm font-medium text-amber-800/90 dark:text-amber-300/90">
                                Dokumen memo ini diajukan untuk ditinjau oleh Manajer Unit Kerja sebelum nomor surat resmi dan SPPD disahkan.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($memo->status === 'approved')
            <div class="rounded-2xl border border-emerald-300 bg-emerald-50/90 p-5 shadow-xs dark:border-emerald-700/60 dark:bg-emerald-950/40">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3.5">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-600 dark:bg-emerald-400/20 dark:text-emerald-400">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h2 class="text-base sm:text-lg font-bold text-emerald-950 dark:text-emerald-200">
                                    Memo Telah Disetujui
                                </h2>
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-100/80 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                                    Disetujui (Approved)
                                </span>
                            </div>
                            <p class="mt-1 text-sm font-medium text-emerald-800/90 dark:text-emerald-300/90">
                                Dokumen memo ini telah disahkan. Surat tugas resmi dan SPPD peserta telah aktif dan siap dicetak.
                            </p>
                        </div>
                    </div>
                    <div class="shrink-0">
                        <a
                            href="{{ route('memos.print', $memo) }}"
                            target="_blank"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 transition"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.076-.665-2.09-1.248-3.003L4.5 9l2.846-5.692A1.5 1.5 0 0 1 8.688 2.5h6.624a1.5 1.5 0 0 1 1.342.808L19.5 9l-.972 1.826a11.238 11.238 0 0 0-1.248 3.003M6.72 13.829a11.22 11.22 0 0 0 10.56 0M6.72 13.829A11.22 11.22 0 0 1 3 18.75m18 0a11.22 11.22 0 0 1-3.72-4.921M3 18.75A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75M3 18.75V11.25a2.25 2.25 0 0 1 2.25-2.25h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5" />
                            </svg>
                            Cetak Dokumen PDF
                        </a>
                    </div>
                </div>
            </div>
        @elseif ($memo->status === 'rejected')
            @php
                $rejectStep = $approvalRequest?->steps->where('status', 'rejected')->last();
            @endphp
            <div class="rounded-2xl border border-rose-300 bg-rose-50/90 p-5 shadow-xs dark:border-rose-700/60 dark:bg-rose-950/40">
                <div class="flex items-start gap-3.5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-500/20 text-rose-600 dark:bg-rose-400/20 dark:text-rose-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-base sm:text-lg font-bold text-rose-950 dark:text-rose-200">
                                Pengajuan Memo Ditolak
                            </h2>
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-300 bg-rose-100/80 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider text-rose-800 dark:border-rose-700 dark:bg-rose-900/60 dark:text-rose-300">
                                Ditolak (Rejected)
                            </span>
                        </div>
                        <p class="mt-1 text-sm font-medium text-rose-800/90 dark:text-rose-300/90">
                            Pengajuan memo ini telah ditolak oleh pemeriksa.
                        </p>
                        @if ($rejectStep && $rejectStep->notes)
                            <div class="mt-3 rounded-xl border border-rose-200 bg-white/90 p-3.5 text-sm font-medium text-rose-900 shadow-xs dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200">
                                <span class="font-bold">Alasan Penolakan:</span> "{{ $rejectStep->notes }}"
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Official Memo Sheet (Pure Tailwind Styling) --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-10 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            {{-- Document Header Pertamina --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b-2 border-slate-900 pb-6 dark:border-slate-700">
                <div>
                    <span class="text-2xl font-light italic tracking-tight text-slate-500 dark:text-slate-400 font-serif">
                        Energy for Life
                    </span>
                </div>
                <div class="text-left sm:text-right">
                    <div class="text-2xl font-black tracking-widest text-red-600 dark:text-red-500">
                        PERTAMINA
                    </div>
                    <div class="text-sm font-extrabold tracking-wider text-slate-800 dark:text-slate-200">
                        GAS NEGARA
                    </div>
                </div>
            </div>

            {{-- Title & Memo Number Section --}}
            <div class="mt-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-black tracking-wider text-slate-950 dark:text-white">
                        MEMO
                    </h1>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Nomor:
                        </span>
                        <span class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-1 font-mono text-sm font-bold text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            {{ $memo->nomor_memo ?? '-' }}
                        </span>
                    </div>
                </div>
                <div class="text-left sm:text-right">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">
                        Tanggal Dokumen
                    </span>
                    <span class="mt-1 inline-flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.253 18.75m3-18.75h10.5a2.25 2.25 0 0 1 2.25 2.25v13.5a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V6.75a2.25 2.25 0 0 1 2.25-2.25Z" />
                        </svg>
                        {{ $memo->date ? \Carbon\Carbon::parse($memo->date)->locale('id')->translatedFormat('l, d F Y') : '-' }}
                    </span>
                </div>
            </div>

            {{-- Information Cards Grid (Pure Tailwind) --}}
            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Kepada --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-800/40">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <svg class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        Kepada (Yth)
                    </div>
                    <div class="mt-2 text-base font-bold text-slate-950 dark:text-white">
                        {{ $memo->to_recipient }}
                    </div>
                </div>

                {{-- Dari (Pengaju) --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-800/40">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        Dari (Pengaju)
                    </div>
                    <div class="mt-2 text-base font-bold text-slate-950 dark:text-white">
                        {{ $memo->creator?->name ?? 'Administrator' }}
                        @if ($memo->creator?->position)
                            <span class="text-sm font-normal text-slate-500 dark:text-slate-400 ml-1">
                                &bull; {{ $memo->creator->position->name }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Unit Kerja / Departemen --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-800/40">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                        </svg>
                        Unit Kerja / Departemen
                    </div>
                    <div class="mt-2 text-base font-bold text-slate-950 dark:text-white">
                        {{ $memo->department?->name ?? ($memo->creator?->department?->name ?? '-') }}
                    </div>
                </div>

                {{-- Perihal / Agenda --}}
                <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-5 dark:border-indigo-800/50 dark:bg-indigo-950/20">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                        </svg>
                        Perihal / Agenda
                    </div>
                    <div class="mt-2 text-base font-extrabold text-indigo-900 dark:text-indigo-200">
                        {{ $memo->agenda ?? ($memo->title ?? '-') }}
                    </div>
                </div>
            </div>

            {{-- Uraian Isi Memo --}}
            <div class="mt-8 border-t border-slate-200 pt-6 dark:border-slate-800">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2 mb-3">
                    <svg class="h-5 w-5 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Uraian Isi Memo
                </h3>
                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-6 dark:border-slate-800 dark:bg-slate-800/30">
                    <div class="prose max-w-none text-base leading-relaxed text-slate-900 dark:prose-invert dark:text-slate-100">
                        {!! $memo->content !!}
                    </div>
                </div>
            </div>

            {{-- Rincian Pelaksanaan Penugasan (If applicable) --}}
            @if ($memo->assignment_start_date || $memo->location || !empty($participantsList))
                <div class="mt-8 border-t border-slate-200 pt-6 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-950 dark:text-white flex items-center gap-2 mb-4">
                        <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                        </svg>
                        Rincian Pelaksanaan Tugas Lapangan
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if ($memo->assignment_start_date)
                            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-800/40">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    <svg class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.253 18.75m3-18.75h10.5a2.25 2.25 0 0 1 2.25 2.25v13.5a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V6.75a2.25 2.25 0 0 1 2.25-2.25Z" />
                                    </svg>
                                    Waktu Penugasan
                                </div>
                                <div class="mt-2 text-base font-bold text-slate-950 dark:text-white">
                                    {{ \Carbon\Carbon::parse($memo->assignment_start_date)->locale('id')->translatedFormat('d F Y') }}
                                    @if ($memo->assignment_end_date && $memo->assignment_start_date != $memo->assignment_end_date)
                                        s/d {{ \Carbon\Carbon::parse($memo->assignment_end_date)->locale('id')->translatedFormat('d F Y') }}
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($memo->location)
                            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-800/40">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    <svg class="h-4 w-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    Tempat / Lokasi Kegiatan
                                </div>
                                <div class="mt-2 text-base font-bold text-slate-950 dark:text-white">
                                    {{ $memo->location }}
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Participants Table --}}
                    @if (!empty($participantsList))
                        <div class="mt-6">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                </svg>
                                <span class="text-sm font-bold text-slate-900 dark:text-white">
                                    Daftar Pegawai yang Ditugaskan ({{ count($participantsList) }} Orang)
                                </span>
                            </div>
                            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                                <table class="w-full text-left text-sm divide-y divide-slate-200 dark:divide-slate-800">
                                    <thead class="bg-slate-100/80 dark:bg-slate-800/90 text-slate-700 dark:text-slate-300">
                                        <tr>
                                            <th class="w-16 px-4 py-3.5 font-bold">No</th>
                                            <th class="px-5 py-3.5 font-bold">Nama Pegawai & NIP</th>
                                            <th class="px-5 py-3.5 font-bold">Jabatan & Unit Kerja</th>
                                            <th class="px-5 py-3.5 font-bold">Dokumen SPPD</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-900/60">
                                        @foreach ($participantsList as $index => $item)
                                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                                                <td class="px-4 py-4 font-bold text-slate-500 dark:text-slate-400">
                                                    {{ $index + 1 }}
                                                </td>
                                                <td class="px-5 py-4">
                                                    <div class="text-base font-bold text-slate-950 dark:text-white">
                                                        {{ $item['employee']->name }}
                                                    </div>
                                                    <div class="mt-0.5 inline-block rounded border border-slate-200 bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        NIP: {{ $item['employee']->identity_number }}
                                                    </div>
                                                </td>
                                                <td class="px-5 py-4">
                                                    <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                                        {{ $item['employee']->position?->name ?? 'Staff' }}
                                                    </div>
                                                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5">
                                                        {{ $item['employee']->department?->name ?? '-' }}
                                                    </div>
                                                </td>
                                                <td class="px-5 py-4">
                                                    @if ($item['sppd'])
                                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:border-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                                            <svg class="h-3.5 w-3.5 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                            </svg>
                                                            {{ $item['sppd']->sppd_number }}
                                                        </span>
                                                    @else
                                                        <span class="text-xs text-slate-400 italic">Otomatis dibuat saat disetujui</span>
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

        {{-- Riwayat & Jejak Persetujuan (Pure Tailwind) --}}
        @if ($approvalRequest && $approvalRequest->steps->isNotEmpty())
            <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-950 dark:text-white flex items-center gap-2 mb-6">
                    <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    Riwayat & Jejak Persetujuan
                </h3>

                <div class="space-y-6">
                    {{-- Step: Pembuatan --}}
                    <div class="flex items-start gap-4">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950/80 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="text-base font-bold text-slate-900 dark:text-white">
                                    Dokumen Memo Diajukan
                                </span>
                                <span class="rounded-full border border-emerald-300 bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                                    Diajukan
                                </span>
                            </div>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
                                Diajukan oleh <span class="font-bold text-slate-800 dark:text-slate-200">{{ $memo->creator?->name ?? 'Pembuat' }}</span> &bull; {{ $memo->created_at ? \Carbon\Carbon::parse($memo->created_at)->locale('id')->translatedFormat('d F Y, H:i') : '-' }} WIB
                            </p>
                        </div>
                    </div>

                    {{-- Step: Persetujuan Manajer --}}
                    @foreach ($approvalRequest->steps as $step)
                        <div class="flex items-start gap-4">
                            @if ($step->status === 'approved')
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950/80 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-700">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                            @elseif ($step->status === 'rejected')
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950/80 dark:text-rose-400 border border-rose-300 dark:border-rose-700">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </div>
                            @else
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-950/80 dark:text-amber-400 border border-amber-300 dark:border-amber-700">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                            @endif

                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <span class="text-base font-bold text-slate-900 dark:text-white">
                                        {{ $step->policyStep?->step_name ?? 'Persetujuan Manajer' }}
                                    </span>
                                    @if ($step->status === 'approved')
                                        <span class="rounded-full border border-emerald-300 bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                                            Disetujui
                                        </span>
                                    @elseif ($step->status === 'rejected')
                                        <span class="rounded-full border border-rose-300 bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:border-rose-700 dark:bg-rose-900/60 dark:text-rose-300">
                                            Ditolak
                                        </span>
                                    @else
                                        <span class="rounded-full border border-amber-300 bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:border-amber-700 dark:bg-amber-900/60 dark:text-amber-300">
                                            Menunggu Persetujuan
                                        </span>
                                    @endif
                                </div>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
                                    @if ($step->approver)
                                        Pemeriksa: <span class="font-bold text-slate-800 dark:text-slate-200">{{ $step->approver->name }}</span> ({{ $step->approver->position?->name ?? 'Manajer' }})
                                    @else
                                        Pemeriksa: <span class="font-bold text-slate-800 dark:text-slate-200">Manajer Unit Kerja Terkait</span>
                                    @endif
                                    @if ($step->updated_at && $step->status !== 'pending')
                                        &bull; {{ \Carbon\Carbon::parse($step->updated_at)->locale('id')->translatedFormat('d F Y, H:i') }} WIB
                                    @endif
                                </p>
                                @if ($step->notes)
                                    <div class="mt-2.5 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        <span class="font-bold">Catatan / Alasan:</span> {{ $step->notes }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Bottom Action Decision Bar (Pure Tailwind Styled Buttons) --}}
        @if ($canApprove && $memo->status === 'pending')
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-lg backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
                <div>
                    <h4 class="text-base font-bold text-slate-950 dark:text-white">
                        Keputusan Persetujuan Manajer
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Tentukan persetujuan untuk pengajuan memo internal ini.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        wire:click="mountAction('reject')"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-red-700 active:scale-95 transition cursor-pointer"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Tolak Memo
                    </button>

                    <button
                        wire:click="mountAction('approve')"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 active:scale-95 transition cursor-pointer"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Setujui Memo
                    </button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
