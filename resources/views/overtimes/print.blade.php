<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Perintah Kerja Lembur - {{ $order->overtime_number ?? $order->id }}</title>
    <style>
        @page {
            margin: 22px 30px 22px 30px;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10.5px;
            line-height: 1.3;
            color: #000;
        }
        .header-box {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-bottom: 14px;
        }
        .header-box td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: middle;
        }
        .header-title {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            line-height: 1.35;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .meta-table td {
            border: none;
            padding: 2px 4px;
        }
        .section-heading {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .info-table td {
            padding: 2.5px 2px;
            vertical-align: top;
        }
        .lbl {
            width: 145px;
        }
        .sep {
            width: 14px;
            text-align: center;
        }
        .chk-box {
            display: inline-block;
            width: 32px;
            height: 14px;
            border: 1px solid #000;
            text-align: center;
            line-height: 12px;
            font-weight: bold;
            font-size: 11px;
            vertical-align: middle;
            margin: 0 5px;
        }
        .divider {
            border: none;
            border-top: 1.5px solid #000;
            margin: 12px 0 14px 0;
        }
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .sig-table td {
            vertical-align: top;
            text-align: center;
        }
        .doc-number-line {
            text-align: right;
            font-size: 9px;
            color: #444;
            margin-top: -10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/logo-pkj.png');
        $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;

        $city = $order->resolveCityLabel();
        $overtimeDateStr = $order->overtime_date
            ? \Carbon\Carbon::parse($order->overtime_date)->locale('id')->translatedFormat('d F Y')
            : '-';
        $commandDateStr = $order->command_date
            ? \Carbon\Carbon::parse($order->command_date)->locale('id')->translatedFormat('d F Y')
            : '-';
        $reportDateStr = $order->report_date
            ? \Carbon\Carbon::parse($order->report_date)->locale('id')->translatedFormat('d F Y')
            : '____________________';

        $isBiasa = $order->overtime_type === 'Biasa';
        $isEmergency = $order->overtime_type === 'Emergency';

        $timeRange = ($order->start_time && $order->end_time)
            ? "{$order->start_time} s/d {$order->end_time}"
            : '-';
    @endphp

    <table class="header-box">
        <tr>
            <td style="width: 24%; text-align: center;">
                @if ($logoBase64)
                    <img src="data:image/png;base64,{{ $logoBase64 }}" alt="Perkasa" style="max-height: 36px; max-width: 120px;">
                @else
                    <strong style="font-size: 14px; color: #0284c7;">perkasa</strong>
                @endif
            </td>
            <td style="width: 54%;" class="header-title">
                <div>PT Perusahaan Gas Negara</div>
                <div>SURAT PERINTAH KERJA LEMBUR</div>
            </td>
            <td style="width: 22%; padding: 0;">
                <table class="meta-table">
                    <tr>
                        <td style="border-bottom: 1px solid #000; width: 58%;">FS No</td>
                        <td style="border-bottom: 1px solid #000;">: {{ $setting->fs_number ?: '05' }}</td>
                    </tr>
                    <tr>
                        <td>No Revisi</td>
                        <td>: {{ $setting->revision_number ?: '01' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($order->overtime_number)
        <div class="doc-number-line">Nomor: <strong>{{ $order->overtime_number }}</strong></div>
    @endif

    <div class="section-heading">A. SURAT PERINTAH KERJA LEMBUR</div>

    <table class="info-table">
        <tr>
            <td class="lbl">Satuan Kerja</td>
            <td class="sep">:</td>
            <td>{{ $order->satuan_kerja }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal</td>
            <td class="sep">:</td>
            <td>{{ $overtimeDateStr }}</td>
        </tr>
    </table>

    <div style="margin: 8px 0;">Kepada pekerja tersebut dibawah ini, ditugaskan untuk Kerja Lembur</div>

    <table class="info-table">
        <tr>
            <td class="lbl">Nama</td>
            <td class="sep">:</td>
            <td style="font-weight: bold;">{{ strtoupper($order->employee?->name ?? '-') }}</td>
        </tr>
        <tr>
            <td class="lbl">Jenis Kerja Lembur</td>
            <td class="sep">:</td>
            <td>
                <span style="display: inline-block; width: 110px; border-bottom: 1px solid #000;">&nbsp;</span>
                Biasa <span class="chk-box">{{ $isBiasa ? '✔' : '' }}</span>
                Emergency <span class="chk-box">{{ $isEmergency ? '✔' : '' }}</span>
            </td>
        </tr>
        <tr>
            <td class="lbl">Uraian Kerja Lembur</td>
            <td class="sep">:</td>
            <td>{{ $order->work_description }}</td>
        </tr>
    </table>

    <table class="sig-table">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%;">
                <div>{{ $city }}, {{ $commandDateStr }}</div>
                <div style="margin-bottom: 4px;">Pejabat yang memerintahkan,</div>
                @include('signatures.pdf-stamp', ['render' => $commandSignatureRender, 'align' => 'center', 'emptyHeight' => '48px'])
                <div style="margin-top: 4px;">( {{ $commandManager?->name ?? '____________________' }} )</div>
            </td>
        </tr>
    </table>

    <hr class="divider">

    <div class="section-heading">B. SURAT PERTANGGUNG JAWABAN KERJA LEMBUR</div>

    <div style="margin: 6px 0 8px 0;">Perintah Kerja Lembur pada :</div>

    <table class="info-table">
        <tr>
            <td class="lbl">Tanggal</td>
            <td class="sep">:</td>
            <td>{{ $overtimeDateStr }}</td>
        </tr>
        <tr>
            <td class="lbl">Jam</td>
            <td class="sep">:</td>
            <td>{{ $timeRange }}</td>
        </tr>
        <tr>
            <td class="lbl">Jenis Kerja Lembur</td>
            <td class="sep">:</td>
            <td>
                <span style="display: inline-block; width: 110px; border-bottom: 1px solid #000;">&nbsp;</span>
                Biasa <span class="chk-box">{{ $isBiasa ? '✔' : '' }}</span>
                Emergency <span class="chk-box">{{ $isEmergency ? '✔' : '' }}</span>
            </td>
        </tr>
        <tr>
            <td class="lbl">Hasil Kerja Lembur</td>
            <td class="sep">:</td>
            <td>{{ $order->actual_result ?: '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">Telah dilaksanakan</td>
            <td class="sep">:</td>
            <td>{{ $order->report_status !== 'not_submitted' ? 'Sudah dilaksanakan' : '-' }}</td>
        </tr>
        <tr>
            <td class="lbl" style="padding-top: 8px;">Nama</td>
            <td class="sep" style="padding-top: 8px;">:</td>
            <td style="padding-top: 8px; font-weight: bold;">{{ strtoupper($order->employee?->name ?? '-') }}</td>
        </tr>
        <tr>
            <td class="lbl">Satuan Kerja</td>
            <td class="sep">:</td>
            <td>{{ $order->satuan_kerja }}</td>
        </tr>
    </table>

    <table class="sig-table" style="margin-top: 14px;">
        <tr>
            <td style="width: 50%;">
                <div style="margin-top: 14px; margin-bottom: 4px;">Yang melaksanakan Kerja Lembur</div>
                @include('signatures.pdf-stamp', ['render' => $employeeSignatureRender, 'align' => 'center', 'emptyHeight' => '48px'])
                <div style="margin-top: 4px;">( {{ strtoupper($order->employee?->name ?? '____________________') }} )</div>
            </td>
            <td style="width: 50%;">
                <div>{{ $city }}, {{ $reportDateStr }}</div>
                <div style="margin-bottom: 4px;">Pejabat yang memerintahkan</div>
                @include('signatures.pdf-stamp', ['render' => $reportSignatureRender, 'align' => 'center', 'emptyHeight' => '48px'])
                <div style="margin-top: 4px;">( {{ $reportManager?->name ?? '____________________' }} )</div>
            </td>
        </tr>
    </table>
</body>
</html>
