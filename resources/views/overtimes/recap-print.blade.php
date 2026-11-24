<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $recap->title }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #111827;
            margin: 18px 22px;
        }
        .header {
            margin-bottom: 12px;
        }
        .title {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 3px 0;
        }
        .subtitle {
            font-size: 11px;
            margin: 0 0 2px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #374151;
            padding: 5px 6px;
        }
        th {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .signature-block {
            margin-top: 28px;
            width: 260px;
        }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">{{ $recap->title }}</p>
        @if($recap->department)
            <p class="subtitle">{{ $recap->department->name }}</p>
        @endif
        <p class="subtitle">Periode {{ $periodLabel }} &mdash; {{ $recap->recap_number }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">No.</th>
                <th style="width: 90px;">NIP</th>
                <th>Nama</th>
                <th style="width: 150px;">Posisi</th>
                <th style="width: 85px;">Periode</th>
                <th style="width: 110px;">Lembur Shift</th>
                <th style="width: 110px;">Lembur Non-Shift</th>
                <th style="width: 110px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recap->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ $item->employee?->identity_number ?? '-' }}</td>
                    <td>{{ $item->employee?->name ?? '-' }}</td>
                    <td>{{ $item->employee?->position?->name ?? '-' }}</td>
                    <td class="text-center">{{ $periodLabel }}</td>
                    <td class="text-right">{{ number_format((float) $item->shift_amount, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $item->non_shift_amount, 0, ',', '.') }}</td>
                    <td class="text-right font-bold">{{ number_format((float) $item->total_amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">Belum ada data SPKL yang disetujui pada periode ini.</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="5" class="font-bold text-center">Grand Total</td>
                <td class="text-right font-bold">{{ number_format((float) $recap->total_shift_amount, 0, ',', '.') }}</td>
                <td class="text-right font-bold">{{ number_format((float) $recap->total_non_shift_amount, 0, ',', '.') }}</td>
                <td class="text-right font-bold">{{ number_format((float) $recap->grand_total, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signature-block">
        <p style="margin: 0;"><strong>PT Permata Karya Jasa</strong></p>
        <p style="margin: 2px 0 46px 0;">Business Division Head</p>
        <p style="margin: 0; font-weight: bold;">{{ $divisionHead?->name ?? 'Business Division Head' }}</p>
    </div>
</body>
</html>
