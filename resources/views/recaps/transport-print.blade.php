<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekapitulasi Biaya Reimbursement Transportasi dan Akomodasi - {{ $periodLabel }}</title>
    <style>
        @page { margin: 22px 28px; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #000; line-height: 1.3; }
        .logo-row { text-align: right; margin-bottom: 8px; }
        .doc-title { text-align: center; font-weight: bold; font-size: 11.5px; line-height: 1.4; margin-bottom: 10px; }
        .intro { text-align: center; font-size: 10px; margin-bottom: 12px; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.data-table th, table.data-table td { border: 1px solid #000; padding: 5px 6px; vertical-align: middle; }
        table.data-table th { background-color: #9fc5e8; font-weight: bold; text-align: center; }
        .num { text-align: center; width: 28px; }
        .period { text-align: center; width: 70px; font-weight: bold; }
        .money { text-align: right; width: 82px; white-space: nowrap; }
        .summary-row td { background-color: #9fc5e8; font-weight: bold; }
        table.sign-table { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
        table.sign-table th, table.sign-table td { border: 1px solid #000; padding: 5px 8px; text-align: center; vertical-align: middle; }
        .sign-space { height: 65px; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/logo-pkj.png');
        $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
        $fmt = fn ($val): string => number_format((float) $val, 0, ',', ',');
        $rows = $summary['rows'] ?? [];
        $itemCount = max(1, count($rows));
        $manfeeLabel = rtrim(rtrim(number_format((float) ($summary['manfee_percent'] ?? 5), 2, '.', ''), '0'), '.');
        $preparedBy = $signatories['prepared_by'] ?? null;
        $checkedBy = ($signatories['checked_by'] ?? collect())->isNotEmpty() ? $signatories['checked_by'] : collect([null]);
        $approvedBy = $signatories['approved_by'] ?? null;
        $sigService = app(\App\Services\Signature\SignatureService::class);
    @endphp

    <div class="logo-row">
        @if ($logoBase64)
            <img src="data:image/png;base64,{{ $logoBase64 }}" style="height: 36px;">
        @endif
    </div>

    <div class="doc-title">
        Rekapitulasi Biaya Reimbursement Transportasi dan Akomodasi<br>
        {{ $workName }}<br>
        Periode {{ $periodLabel }}
    </div>

    <div class="intro">
        Penyedia Jasa telah memenuhi syarat untuk menerima pembayaran reimbursement sebagaimana
        yang tercantum dalam Kerangka Acuan Kerja (KAK) dan Kontrak (beserta perubahannya apabila ada),
        dengan rincian sebagai berikut:
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="num">No</th>
                <th class="period">Periode</th>
                <th>Keterangan</th>
                <th class="money">Jumlah</th>
                <th class="money">Service Fee</th>
                <th class="money">Total Biaya</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    @if ($index === 0)
                        <td class="period" rowspan="{{ $itemCount }}">{{ $periodMonth }}</td>
                    @endif
                    <td>{{ $row['description'] }}</td>
                    <td class="money">{{ $fmt($row['amount']) }}</td>
                    <td class="money">{{ $fmt($row['service_fee']) }}</td>
                    <td class="money">{{ $fmt($row['total_amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">Belum ada komponen biaya transportasi &amp; akomodasi.</td>
                </tr>
            @endforelse
            <tr class="summary-row">
                <td colspan="5" style="text-align: center;">Total Biaya</td>
                <td class="money">{{ $fmt($summary['total_cost'] ?? 0) }}</td>
            </tr>
            <tr class="summary-row">
                <td colspan="5" style="text-align: center;">Manfee {{ $manfeeLabel }}%</td>
                <td class="money">{{ $fmt($summary['manfee_amount'] ?? 0) }}</td>
            </tr>
            <tr class="summary-row">
                <td colspan="5" style="text-align: center;">Total Biaya dan Manfee</td>
                <td class="money">{{ $fmt($summary['grand_total'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="sign-table">
        <tr>
            <th>PT Permata Karya Jasa</th>
            <th colspan="{{ $checkedBy->count() + 1 }}">PT Perusahaan Gas Negara (Persero) Tbk.</th>
        </tr>
        <tr>
            <td>Disusun oleh</td>
            <td colspan="{{ $checkedBy->count() }}">Diperiksa oleh</td>
            <td>Disetujui oleh</td>
        </tr>
        <tr>
            <td class="sign-space">
                @include('signatures.pdf-stamp', [
                    'render' => $sigService->resolveRenderData($trip, $preparedBy, 'requester', $step0Approved ?? (bool) $preparedBy),
                    'align' => 'center',
                    'emptyHeight' => '55px',
                ])
            </td>
            @foreach ($checkedBy as $checker)
                <td class="sign-space">
                    @include('signatures.pdf-stamp', [
                        'render' => $sigService->resolveRenderData($trip, $checker, 'manager', $step1Approved ?? false),
                        'align' => 'center',
                        'emptyHeight' => '55px',
                    ])
                </td>
            @endforeach
            <td class="sign-space">
                @include('signatures.pdf-stamp', [
                    'render' => $sigService->resolveRenderData($trip, $approvedBy, 'division_head', $step2Approved ?? false),
                    'align' => 'center',
                    'emptyHeight' => '55px',
                ])
            </td>
        </tr>
        <tr>
            <td><strong>{{ $preparedBy?->name ?? ($trip->prepared_by_name ?: 'Khairul Khitam') }}</strong></td>
            @foreach ($checkedBy as $checker)
                <td><strong>{{ $checker?->name ?? ($trip->checked_by_name ?: 'Manajer Unit Kerja') }}</strong></td>
            @endforeach
            <td><strong>{{ $approvedBy?->name ?? ($trip->approved_by_name ?: 'Division Head') }}</strong></td>
        </tr>
        <tr>
            <td>{{ $preparedBy?->position?->name ?? ($trip->prepared_by_position ?: 'Business Division Head') }}</td>
            @foreach ($checkedBy as $checker)
                <td>{{ $checker?->position?->name ?? ($trip->checked_by_position ?: 'Manajer') }}</td>
            @endforeach
            <td>{{ $approvedBy?->position?->name ?? ($trip->approved_by_position ?: 'Division Head Asset Integrity Management System') }}</td>
        </tr>
    </table>
</body>
</html>
