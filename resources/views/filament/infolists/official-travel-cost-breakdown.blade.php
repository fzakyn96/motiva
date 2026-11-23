@php
    /** @var \App\Models\OfficialTravelRecapItem $item */
    $item = $getRecord();
    $cost = $item->sppdCost ?? $item->sppd?->cost;
    $idr = fn ($v): string => 'Rp ' . number_format((float) $v, 0, ',', '.');
@endphp

<div class="w-full space-y-3">
    <div class="text-sm font-medium text-gray-950 dark:text-white">
        Rincian Komponen Biaya SPPD
    </div>

    @if (! $cost)
        <p class="text-xs text-gray-500 dark:text-gray-400">Rincian biaya SPPD belum tersedia.</p>
    @else
        @php
            $groups = [
                [
                    'title' => 'Transportasi',
                    'accent' => '#0284c7',
                    'headerBg' => 'rgba(2, 132, 199, 0.14)',
                    'borderColor' => 'rgba(2, 132, 199, 0.35)',
                    'subtotal' => (float) $cost->subtotal_transport,
                    'items' => [
                        'Transportasi Terminal / Bandara' => (float) $cost->transport_terminal_total,
                        'Tiket Transportasi' => (float) $cost->transport_ticket_total,
                        'Transportasi Lokal' => (float) $cost->transport_local_total,
                    ],
                ],
                [
                    'title' => 'Uang Harian & Penginapan',
                    'accent' => '#d97706',
                    'headerBg' => 'rgba(217, 119, 6, 0.14)',
                    'borderColor' => 'rgba(217, 119, 6, 0.35)',
                    'subtotal' => (float) $cost->subtotal_per_diem,
                    'items' => [
                        'Hotel / Penginapan' => (float) $cost->hotel_total,
                        'Uang Makan' => (float) $cost->meal_total,
                        'Reimburse Uang Makan' => (float) $cost->meal_reimbursement_total,
                        'Transit Lokal' => (float) $cost->local_transit_total,
                        'Uang Saku' => (float) $cost->pocket_money_total,
                        'Harian Luar Negeri' => (float) $cost->overseas_daily_allowance,
                        'Hotel & Makan Luar Negeri' => (float) $cost->overseas_hotel_meal,
                        'Transport Luar Negeri' => (float) $cost->overseas_transport,
                        'Uang Saku Luar Negeri' => (float) $cost->overseas_pocket_money,
                        'Tunjangan Bulanan Luar Negeri' => (float) $cost->overseas_monthly_allowance,
                    ],
                ],
                [
                    'title' => 'Biaya Lain-lain',
                    'accent' => '#7c3aed',
                    'headerBg' => 'rgba(124, 58, 237, 0.14)',
                    'borderColor' => 'rgba(124, 58, 237, 0.35)',
                    'subtotal' => (float) $cost->subtotal_other,
                    'items' => [
                        'Airport Tax' => (float) $cost->other_airport_tax,
                        'Laundry' => (float) $cost->other_laundry,
                        'Perlengkapan' => (float) $cost->other_equipment,
                        'Buku / Makalah' => (float) $cost->other_book_paper,
                        'Asesor' => (float) $cost->other_assessor,
                        'Sewa Kendaraan' => (float) $cost->other_car_rental,
                        'Bagasi' => (float) $cost->other_baggage,
                        'Tol / BBM / Parkir' => (float) $cost->other_toll_fuel_parking,
                    ],
                ],
            ];

            $visibleGroups = collect($groups)->map(function ($group) {
                $group['visibleItems'] = collect($group['items'])->filter(fn ($val) => $val > 0);
                return $group;
            })->filter(fn ($group) => $group['visibleItems']->isNotEmpty() || $group['subtotal'] > 0)->values();
        @endphp

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 12px; width: 100%;">
            @foreach ($visibleGroups as $group)
                <div style="border: 1px solid {{ $group['borderColor'] }}; border-radius: 12px; overflow: hidden; background: rgba(156, 163, 175, 0.04);">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 14px; background: {{ $group['headerBg'] }}; border-bottom: 1px solid {{ $group['borderColor'] }}; font-size: 12px; font-weight: 700;">
                        <span style="color: {{ $group['accent'] }};">{{ $group['title'] }}</span>
                        <span style="font-variant-numeric: tabular-nums; white-space: nowrap; color: {{ $group['accent'] }};">{{ $idr($group['subtotal']) }}</span>
                    </div>
                    <div style="padding: 8px 14px;">
                        @foreach ($group['visibleItems'] as $label => $val)
                            <div class="flex items-center justify-between gap-4 text-xs py-1">
                                <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                <span class="font-medium tabular-nums whitespace-nowrap text-gray-900 dark:text-gray-100">{{ $idr($val) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @php
            $hasEmployeeSigned = \Illuminate\Support\Facades\Schema::hasTable('document_signatures')
                && \App\Models\DocumentSignature::where('document_type', \App\Models\SppdCost::class)
                    ->where('document_id', $cost->id)
                    ->where('role_type', 'requester')
                    ->exists();
        @endphp

        <div class="pt-3 border-t border-dashed border-gray-200 dark:border-white/15 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-2">
                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 9999px; background: rgba(245, 158, 11, 0.14); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 600;">
                    Lama Dinas: {{ (int) $cost->official_days }} hari
                </span>
                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 9999px; background: rgba(16, 185, 129, 0.14); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 700; font-variant-numeric: tabular-nums;">
                    Total Rincian SPPD: {{ $idr($cost->total_cost) }}
                </span>
                @if (! $hasEmployeeSigned)
                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 9999px; background: rgba(244, 63, 94, 0.14); color: #f43f5e; border: 1px solid rgba(244, 63, 94, 0.35); font-weight: 600;">
                        Belum ditandatangani pegawai yang ditugaskan
                    </span>
                @else
                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 9999px; background: rgba(16, 185, 129, 0.14); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 600;">
                        Sudah ditandatangani pegawai
                    </span>
                @endif
            </div>
            <a href="{{ route('sppd-costs.print', $cost) }}" target="_blank"
               style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; background: linear-gradient(135deg, #0284c7, #2563eb); color: #ffffff; font-weight: 600; text-decoration: none; box-shadow: 0 2px 6px rgba(37, 99, 235, 0.28); white-space: nowrap;">
                Lihat Rincian SPPD (PDF) &rarr;
            </a>
        </div>
    @endif
</div>
