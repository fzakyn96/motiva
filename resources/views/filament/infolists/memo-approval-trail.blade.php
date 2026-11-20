@php
    $record = $getRecord();
    $approvalRequest = \App\Models\ApprovalRequest::with(['steps.approver.position', 'steps.policyStep'])
        ->where('document_type', \App\Models\Memo::class)
        ->where('document_id', $record->id)
        ->first();

    // Resolve Manager name for this memo using authority hierarchy (not direct Supervisor)
    $manager = \App\Services\Approval\Resolvers\ManagerAuthorityResolver::resolveManagerForEmployee(
        $record->creator,
        $record->department_id
    );
    $defaultManagerName = $manager ? $manager->name : 'Manajer';
@endphp

<div class="space-y-4">
    {{-- Pengajuan --}}
    <div class="flex items-start gap-3" style="gap: 0.75rem;">
        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
            <x-heroicon-m-check class="h-4 w-4" />
        </div>
        <div>
            <div class="flex items-center gap-2" style="gap: 0.625rem;">
                <span class="text-sm font-bold text-gray-900 dark:text-white">Dokumen Memo Diajukan</span>
                <x-filament::badge color="success" size="xs">Diajukan</x-filament::badge>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Oleh {{ $record->creator?->name ?? 'Pembuat' }} &bull; {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->locale('id')->translatedFormat('d F Y, H:i') : '-' }} WIB
            </p>
        </div>
    </div>

    @if ($approvalRequest)
        @foreach ($approvalRequest->steps as $step)
            @php
                $pemeriksaName = $step->approver ? $step->approver->name : $defaultManagerName;
            @endphp
            <div class="flex items-start gap-3" style="gap: 0.75rem;">
                @if ($step->status === 'approved')
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                        <x-heroicon-m-check class="h-4 w-4" />
                    </div>
                @elseif ($step->status === 'rejected')
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400">
                        <x-heroicon-m-x-mark class="h-4 w-4" />
                    </div>
                @else
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400">
                        <x-heroicon-m-clock class="h-4 w-4" />
                    </div>
                @endif
                <div>
                    <div class="flex items-center gap-2" style="gap: 0.625rem;">
                        <span class="text-sm font-bold text-gray-900 dark:text-white">
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
                        Manajer: {{ $pemeriksaName }}
                        @if ($step->updated_at && $step->status !== 'pending')
                            &bull; {{ \Carbon\Carbon::parse($step->updated_at)->locale('id')->translatedFormat('d F Y, H:i') }} WIB
                        @endif
                    </p>
                    @if ($step->notes)
                        <div class="mt-2 rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-2.5 text-xs text-gray-700 dark:text-gray-300">
                            <span class="font-bold">Catatan:</span> {{ $step->notes }}
                        </div>
                    @endif
                    @if ($step->status === 'approved' && \Illuminate\Support\Facades\Schema::hasTable('document_signatures'))
                        @php
                            $docSig = \App\Models\DocumentSignature::where('document_type', \App\Models\Memo::class)
                                ->where('document_id', $record->id)
                                ->latest('signed_at')
                                ->first();
                        @endphp
                        @if ($docSig)
                            <div class="mt-2">
                                <a href="{{ $docSig->verification_url }}" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:6px;background:#059669;color:#ffffff;font-size:11px;font-weight:600;text-decoration:none;">
                                    ✓ Verifikasi Tanda Tangan Elektronik
                                </a>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
