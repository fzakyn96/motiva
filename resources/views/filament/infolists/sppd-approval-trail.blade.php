@php
    $record = $getRecord();
    $approvalRequest = \App\Models\ApprovalRequest::where('document_type', \App\Models\Sppd::class)
        ->where('document_id', $record->id)
        ->with(['steps.policyStep', 'steps.approver.position', 'steps.approver.department'])
        ->first();

    if (! $approvalRequest && $record->status === 'pending') {
        $approvalRequest = (new \App\Observers\SppdObserver())->ensureApprovalRequestExists($record);
        if ($approvalRequest) {
            $approvalRequest->load(['steps.policyStep', 'steps.approver.position', 'steps.approver.department']);
        }
    }

    $step1 = null;
    $step2 = null;

    if ($approvalRequest) {
        $step1 = $approvalRequest->steps->first(fn ($s) => $s->policyStep?->resolver_class === \App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class);
        $step2 = $approvalRequest->steps->first(fn ($s) => $s->policyStep?->resolver_class === \App\Services\Approval\Resolvers\ManagerAuthorityResolver::class);
    }
@endphp

<div class="space-y-4">
    {{-- Banner Ringkasan Status --}}
    @if ($record->status === 'approved')
        <div class="flex items-start gap-3 rounded-xl p-4 bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <x-filament::icon icon="heroicon-m-check-circle" class="w-5 h-5 shrink-0 mt-0.5" style="color: #10b981;" />
            <div>
                <h4 class="font-bold text-sm text-gray-950 dark:text-white">SPPD Telah Disetujui Sepenuhnya (Final Approved)</h4>
                <p class="text-xs text-gray-600 dark:text-white mt-1 leading-relaxed">
                    Surat Perintah Perjalanan Dinas ini telah mendapatkan persetujuan resmi. Dokumen siap untuk dicetak dan direalisasikan.
                </p>
            </div>
        </div>
    @elseif ($record->status === 'rejected')
        <div class="flex items-start gap-3 rounded-xl p-4 bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <x-filament::icon icon="heroicon-m-x-circle" class="w-5 h-5 shrink-0 mt-0.5" style="color: #f43f5e;" />
            <div>
                <h4 class="font-bold text-sm text-gray-950 dark:text-white">Pengajuan SPPD Ditolak</h4>
                <p class="text-xs text-gray-600 dark:text-white mt-1 leading-relaxed">
                    Pengajuan perjalanan dinas ini ditolak. Silakan periksa catatan alasan penolakan di bawah.
                </p>
            </div>
        </div>
    @elseif (! $step1)
        <div class="flex items-start gap-3 rounded-xl p-4 bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <x-filament::icon icon="heroicon-m-clock" class="w-5 h-5 shrink-0 mt-0.5" style="color: #f59e0b;" />
            <div>
                <h4 class="font-bold text-sm text-gray-950 dark:text-white">Menunggu Persetujuan Manajer</h4>
                <p class="text-xs text-gray-600 dark:text-white mt-1 leading-relaxed">
                    SPPD ini sedang menunggu verifikasi dan persetujuan tingkat akhir (Final Approval) oleh Manajer Area.
                </p>
            </div>
        </div>
    @elseif ($step1 && $step1->status === 'approved')
        <div class="flex items-start gap-3 rounded-xl p-4 bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <x-filament::icon icon="heroicon-m-information-circle" class="w-5 h-5 shrink-0 mt-0.5" style="color: #3b82f6;" />
            <div>
                <h4 class="font-bold text-sm text-gray-950 dark:text-white">Disetujui Supervisor — Menunggu Persetujuan Manajer</h4>
                <p class="text-xs text-gray-600 dark:text-white mt-1 leading-relaxed">
                    Supervisor ({{ $step1->approver?->name ?? 'Supervisor' }}) telah menyetujui SPPD ini. Sekarang menunggu persetujuan tingkat akhir (Final Approval) oleh Manajer.
                </p>
            </div>
        </div>
    @else
        <div class="flex items-start gap-3 rounded-xl p-4 bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <x-filament::icon icon="heroicon-m-clock" class="w-5 h-5 shrink-0 mt-0.5" style="color: #f59e0b;" />
            <div>
                <h4 class="font-bold text-sm text-gray-950 dark:text-white">Tahap 1: Menunggu Persetujuan Supervisor</h4>
                <p class="text-xs text-gray-600 dark:text-white mt-1 leading-relaxed">
                    SPPD ini sedang menunggu verifikasi dan persetujuan dari Supervisor terkait sebelum dapat disetujui oleh Manajer.
                </p>
            </div>
        </div>
    @endif

    {{-- Grid Dua Tahap Persetujuan --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- KARTU STEP 1: SUPERVISOR --}}
        <div class="rounded-xl p-4 bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 transition-all">
            <div class="flex items-center justify-between gap-2 border-b pb-3 mb-3 border-gray-200 dark:border-white/10" style="gap: 0.75rem;">
                <div class="flex items-center gap-x-3">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white shadow-sm"
                        style="background-color: {{ $step1?->status === 'approved' ? '#10b981' : ($step1?->status === 'rejected' ? '#f43f5e' : '#f59e0b') }}; margin-right: 0.75rem;"
                    >
                        1
                    </span>
                    <div>
                        <div class="font-bold text-sm text-gray-950 dark:text-white leading-tight">Persetujuan Supervisor</div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-0.5">Verifikasi Teknis & Lapangan</div>
                    </div>
                </div>

                @if ($step1?->status === 'approved')
                    <x-filament::badge color="success" icon="heroicon-m-check-circle">
                        Disetujui
                    </x-filament::badge>
                @elseif ($step1?->status === 'rejected')
                    <x-filament::badge color="danger" icon="heroicon-m-x-circle">
                        Ditolak
                    </x-filament::badge>
                @else
                    <x-filament::badge color="warning" icon="heroicon-m-clock">
                        Menunggu Persetujuan
                    </x-filament::badge>
                @endif
            </div>

            <div class="space-y-2.5 text-xs" style="display: flex; flex-direction: column; gap: 0.625rem;">
                <div class="flex justify-between items-start gap-4">
                    <span class="text-gray-500 dark:text-gray-400 font-medium shrink-0">Penyetuju:</span>
                    <span class="font-bold text-gray-950 dark:text-white text-right">
                        {{ $step1?->approver?->name ?? ($step1?->status === 'approved' ? 'Supervisor' : 'Belum Ditentukan') }}
                    </span>
                </div>
                @if ($step1?->approver)
                    <div class="flex justify-between items-start gap-4">
                        <span class="text-gray-500 dark:text-gray-400 font-medium shrink-0">NIP / Jabatan:</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100 text-right">
                            {{ $step1->approver->identity_number }} ({{ $step1->approver->position?->name ?? 'Supervisor' }})
                        </span>
                    </div>
                @endif
                <div class="flex justify-between items-start gap-4">
                    <span class="text-gray-500 dark:text-gray-400 font-medium shrink-0">Waktu Proses:</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100 text-right">
                        {{ $step1?->updated_at && $step1->status !== 'pending' ? $step1->updated_at->locale('id')->isoFormat('D MMMM Y, HH:mm') : '-' }}
                    </span>
                </div>
                @if ($step1?->notes)
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-white/10">
                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200 block mb-2">Catatan Supervisor:</span>
                        <div class="rounded-lg bg-gray-100/90 p-3 text-xs font-medium text-gray-950 ring-1 ring-inset ring-white/10 dark:bg-white/[0.08] dark:text-white dark:ring-white/15 leading-relaxed">
                            "{{ $step1->notes }}"
                        </div>
                    </div>
                @endif
                @if ($step1?->status === 'approved' && \Illuminate\Support\Facades\Schema::hasTable('document_signatures'))
                    @php
                        $spvSig = \App\Models\DocumentSignature::where('document_type', \App\Models\Sppd::class)
                            ->where('document_id', $record->id)
                            ->where('role_type', 'supervisor')
                            ->latest('signed_at')
                            ->first();
                    @endphp
                    @if ($spvSig)
                        <div class="mt-2">
                            <a href="{{ $spvSig->verification_url }}" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:6px;background:#059669;color:#ffffff;font-size:11px;font-weight:600;text-decoration:none;">
                                ✓ Verifikasi Tanda Tangan Supervisor
                            </a>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- KARTU STEP 2: MANAJER (LEVEL TERAKHIR) --}}
        <div class="rounded-xl p-4 bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 transition-all {{ $step1?->status !== 'approved' && $step2?->status === 'pending' ? 'opacity-80' : '' }}">
            <div class="flex items-center justify-between gap-2 border-b pb-3 mb-3 border-gray-200 dark:border-white/10" style="gap: 0.75rem;">
                <div class="flex items-center gap-x-3">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white shadow-sm"
                        style="background-color: {{ $step2?->status === 'approved' ? '#10b981' : ($step2?->status === 'rejected' ? '#f43f5e' : ($step1?->status === 'approved' ? '#f59e0b' : '#6b7280')) }}; margin-right: 0.75rem;"
                    >
                        2
                    </span>
                    <div>
                        <div class="font-bold text-sm text-gray-950 dark:text-white leading-tight">Persetujuan Manajer</div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-0.5">Persetujuan Tingkat Akhir (Final)</div>
                    </div>
                </div>

                @if ($step2?->status === 'approved')
                    <x-filament::badge color="success" icon="heroicon-m-check-circle">
                        Disetujui (Final)
                    </x-filament::badge>
                @elseif ($step2?->status === 'rejected')
                    <x-filament::badge color="danger" icon="heroicon-m-x-circle">
                        Ditolak
                    </x-filament::badge>
                @elseif ($step1?->status === 'approved')
                    <x-filament::badge color="warning" icon="heroicon-m-clock">
                        Menunggu Persetujuan Manajer
                    </x-filament::badge>
                @else
                    <x-filament::badge color="gray" icon="heroicon-m-lock-closed">
                        Menunggu Supervisor
                    </x-filament::badge>
                @endif
            </div>

            <div class="space-y-2.5 text-xs" style="display: flex; flex-direction: column; gap: 0.625rem;">
                <div class="flex justify-between items-start gap-4">
                    <span class="text-gray-500 dark:text-gray-400 font-medium shrink-0">Penyetuju:</span>
                    <span class="font-bold text-gray-950 dark:text-white text-right">
                        {{ $step2?->approver?->name ?? ($step2?->status === 'approved' ? 'Manajer' : 'Belum Diproses') }}
                    </span>
                </div>
                @if ($step2?->approver)
                    <div class="flex justify-between items-start gap-4">
                        <span class="text-gray-500 dark:text-gray-400 font-medium shrink-0">NIP / Jabatan:</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100 text-right">
                            {{ $step2->approver->identity_number }} ({{ $step2->approver->position?->name ?? 'Manajer' }})
                        </span>
                    </div>
                @endif
                <div class="flex justify-between items-start gap-4">
                    <span class="text-gray-500 dark:text-gray-400 font-medium shrink-0">Waktu Proses:</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100 text-right">
                        {{ $step2?->updated_at && $step2->status !== 'pending' ? $step2->updated_at->locale('id')->isoFormat('D MMMM Y, HH:mm') : '-' }}
                    </span>
                </div>
                @if ($step2?->notes)
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-white/10">
                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200 block mb-2">Catatan Manajer:</span>
                        <div class="rounded-lg bg-gray-100/90 p-3 text-xs font-medium text-gray-950 ring-1 ring-inset ring-white/10 dark:bg-white/[0.08] dark:text-white dark:ring-white/15 leading-relaxed">
                            "{{ $step2->notes }}"
                        </div>
                    </div>
                @endif
                @if ($step2?->status === 'approved' && \Illuminate\Support\Facades\Schema::hasTable('document_signatures'))
                    @php
                        $mgrSig = \App\Models\DocumentSignature::where('document_type', \App\Models\Sppd::class)
                            ->where('document_id', $record->id)
                            ->whereIn('role_type', ['manager', 'approver'])
                            ->latest('signed_at')
                            ->first();
                    @endphp
                    @if ($mgrSig)
                        <div class="mt-2">
                            <a href="{{ $mgrSig->verification_url }}" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:6px;background:#059669;color:#ffffff;font-size:11px;font-weight:600;text-decoration:none;">
                                ✓ Verifikasi Tanda Tangan Manajer
                            </a>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
