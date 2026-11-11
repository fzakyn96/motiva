<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SPPD - {{ $sppd->sppd_number }}</title>
    <style>
        @page {
            margin: 20px 25px 20px 25px;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.25;
            color: #000;
        }
        .header-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: middle;
        }
        .title-area {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            line-height: 1.35;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
        }
        .form-table th, .form-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: middle;
        }
        .col-num {
            width: 24px;
            text-align: center;
            font-weight: bold;
        }
        .col-label {
            width: 220px;
        }
        .chk-box {
            display: inline-block;
            width: 28px;
            height: 14px;
            border: 1.5px solid #000;
            text-align: center;
            line-height: 14px;
            font-size: 10px;
            font-weight: bold;
            margin-right: 6px;
            vertical-align: middle;
        }
        .footer-note {
            margin-top: 6px;
            font-size: 9.5px;
            line-height: 1.3;
        }
    </style>
</head>
<body>

    @php
        $logoPath = public_path('images/logo-pkj.png');
        $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;

        $depDateFormatted = $sppd->departure_date ? \Carbon\Carbon::parse($sppd->departure_date)->locale('id')->translatedFormat('j M y') : '-';
        $retDateFormatted = $sppd->return_date ? \Carbon\Carbon::parse($sppd->return_date)->locale('id')->translatedFormat('j M y') : '-';

        $destTrans = is_array($sppd->destination_transportation) ? $sppd->destination_transportation : [];

        $isDalamNegeri = $sppd->travel_category === 'Dalam Negeri' || empty($sppd->travel_category);
        $isKhusus = $sppd->travel_category === 'Khusus';
        $isLuarNegeri = $sppd->travel_category === 'Luar Negeri';

        $isUmum1 = str_contains($sppd->general_travel_type ?? '', 'Kunjungan') || empty($sppd->general_travel_type);
        $isUmum2 = str_contains($sppd->general_travel_type ?? '', 'Pemindahan');
        $isUmum3 = str_contains($sppd->general_travel_type ?? '', 'Pendidikan');

        $isSpec1 = str_contains($sppd->special_travel_type ?? '', '≥ 80km dan menginap') || empty($sppd->special_travel_type);
        $isSpec2 = str_contains($sppd->special_travel_type ?? '', '≥ 80km dan tidak menginap');
        $isSpec3 = str_contains($sppd->special_travel_type ?? '', '≤ 80km dan menginap');
        $isSpec4 = str_contains($sppd->special_travel_type ?? '', '≤ 80 km dan tidak menginap');

        $depSendiri = $sppd->transport_departure === 'Sendiri' || empty($sppd->transport_departure);
        $depDinas = $sppd->transport_departure === 'Kendaraan Dinas/Operasional';

        $arrSendiri = $sppd->transport_arrival === 'Sendiri';
        $arrDinas = $sppd->transport_arrival === 'Kendaraan Dinas/Operasional' || empty($sppd->transport_arrival);

        $isPU = $sppd->transportation_mode === 'PU';
        $isKA = $sppd->transportation_mode === 'KA' || empty($sppd->transportation_mode);
        $isTUD = $sppd->transportation_mode === 'TUD';
        $isKL = $sppd->transportation_mode === 'KL';

        $tujuanPenginapan = in_array('Penugasan = Penginapan', $destTrans) || empty($destTrans);
        $tujuanDinas = in_array('Kendaraan Dinas/Operasional', $destTrans) || empty($destTrans);
        $tujuanSendiri = in_array('Mengatur Sendiri', $destTrans);
        $tujuanSewa = in_array('Sewa Kendaraan', $destTrans);
    @endphp

    <table class="header-table">
        <tr>
            <td style="width: 70px;">
                @if($logoBase64)
                    <img src="data:image/png;base64,{{ $logoBase64 }}" alt="Logo PKJ" style="height: 38px; width: auto;">
                @endif
            </td>
            <td class="title-area">
                FORMULIR PERINTAH PERJALANAN DINAS<br>
                PEKERJA PENEMPATAN PT PERMATA KARYA JASA
            </td>
            <td style="width: 70px;"></td>
        </tr>
    </table>

    <table class="form-table">
        <tr>
            <td class="col-num">1.</td>
            <td class="col-label">Nama Pekerja yang diperintah</td>
            <td colspan="4" style="font-weight: bold;">{{ $sppd->employee?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="col-num">2.</td>
            <td class="col-label">Golongan Perjalanan Dinas</td>
            <td colspan="4">{{ $sppd->travel_grade ?? '-' }}</td>
        </tr>
        <tr>
            <td class="col-num">3.</td>
            <td class="col-label">Jabatan</td>
            <td colspan="4">{{ $sppd->employee?->position?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="col-num">4.</td>
            <td class="col-label">Tanggal Berangkat</td>
            <td style="width: 140px; text-align: center;">{{ $depDateFormatted }}</td>
            <td style="width: 40px; text-align: center;">Jam</td>
            <td style="width: 65px; text-align: center;">{{ $sppd->departure_time ?? '08:00' }}</td>
            <td rowspan="2" style="width: 130px; text-align: center; vertical-align: middle;">
                <div style="font-weight: bold; font-size: 10px;">JUMLAH HARI</div>
                <div style="font-weight: bold; font-size: 14px; margin-top: 4px;">{{ $sppd->total_days ?? 1 }}</div>
            </td>
        </tr>
        <tr>
            <td class="col-num">5.</td>
            <td class="col-label">Tanggal Kembali</td>
            <td style="text-align: center;">{{ $retDateFormatted }}</td>
            <td style="text-align: center;">Jam</td>
            <td style="text-align: center;">{{ $sppd->return_time ?? '17:00' }}</td>
        </tr>
        <tr>
            <td class="col-num">6.</td>
            <td class="col-label">Tempat Kedudukan</td>
            <td colspan="4">{{ $sppd->origin_location ?? 'Jakarta' }}</td>
        </tr>
        <tr>
            <td class="col-num">7.</td>
            <td class="col-label">Lokasi Penugasan</td>
            <td colspan="4">{{ $sppd->destination_location ?? '-' }}</td>
        </tr>
        <tr>
            <td class="col-num" style="vertical-align: top;">8.</td>
            <td class="col-label" style="vertical-align: top;">
                Deskripsi Tujuan Penugasan (jabarkan peran, tanggung jawab, dan tugas pada pelaksanaan dinas)
            </td>
            <td colspan="4" style="vertical-align: top; text-align: justify;">
                {{ $sppd->assignment_purpose ?? '-' }}
            </td>
        </tr>
        <tr>
            <td class="col-num">9.</td>
            <td class="col-label">Kategori Perjalanan Dinas</td>
            <td colspan="4">
                <span class="chk-box">{{ $isDalamNegeri ? 'V' : '' }}</span> Dalam Negeri &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="chk-box">{{ $isKhusus ? 'V' : '' }}</span> Khusus &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="chk-box">{{ $isLuarNegeri ? 'V' : '' }}</span> Luar Negeri
            </td>
        </tr>
        <tr>
            <td class="col-num" style="vertical-align: top;">10.</td>
            <td class="col-label" style="vertical-align: top;">Jenis Perjalanan Dinas Umum</td>
            <td colspan="4">
                <div style="margin-bottom: 4px;"><span class="chk-box">{{ $isUmum1 ? 'V' : '' }}</span> Kunjungan Dinas Umum/Rapat Dinas/Dinas Survei *)</div>
                <div style="margin-bottom: 4px;"><span class="chk-box">{{ $isUmum2 ? 'V' : '' }}</span> Pemindahan Permanen/Penugasan Sementara *)</div>
                <div><span class="chk-box">{{ $isUmum3 ? 'V' : '' }}</span> Pendidikan dan Pelatihan</div>
            </td>
        </tr>
        <tr>
            <td class="col-num" style="vertical-align: top;">11.</td>
            <td class="col-label" style="vertical-align: top;">Jenis Perjalanan Dinas Khusus</td>
            <td colspan="4">
                <div style="margin-bottom: 4px;"><span class="chk-box">{{ $isSpec1 ? 'V' : '' }}</span> &ge; 80km dan menginap</div>
                <div style="margin-bottom: 4px;"><span class="chk-box">{{ $isSpec2 ? 'V' : '' }}</span> &ge; 80km dan tidak menginap</div>
                <div style="margin-bottom: 4px;"><span class="chk-box">{{ $isSpec3 ? 'V' : '' }}</span> &le; 80km dan menginap</div>
                <div><span class="chk-box">{{ $isSpec4 ? 'V' : '' }}</span> &le; 80 km dan tidak menginap</div>
            </td>
        </tr>
        <tr>
            <td class="col-num" style="vertical-align: top;">12.</td>
            <td class="col-label" style="vertical-align: top;">
                Transportasi ke Bandar Udara/<br>
                Stasiun KA/Terminal Bus/Pelabuhan
            </td>
            <td colspan="4">
                <div style="margin-bottom: 3px;">Di tempat keberangkatan :</div>
                <div style="margin-bottom: 6px;">
                    <span class="chk-box">{{ $depSendiri ? 'V' : '' }}</span> Sendiri &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <span class="chk-box">{{ $depDinas ? 'V' : '' }}</span> Kendaraan Dinas/Operasional
                </div>
                <div style="margin-bottom: 3px;">Di tempat kedatangan :</div>
                <div>
                    <span class="chk-box">{{ $arrSendiri ? 'V' : '' }}</span> Sendiri &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <span class="chk-box">{{ $arrDinas ? 'V' : '' }}</span> Kendaraan Dinas/Operasional
                </div>
            </td>
        </tr>
        <tr>
            <td class="col-num">13.</td>
            <td class="col-label">Sarana Transportasi</td>
            <td colspan="4">
                <span class="chk-box">{{ $isPU ? 'V' : '' }}</span> PU &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="chk-box">{{ $isKA ? 'V' : '' }}</span> KA &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="chk-box">{{ $isTUD ? 'V' : '' }}</span> TUD &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="chk-box">{{ $isKL ? 'V' : '' }}</span> KL
            </td>
        </tr>
        <tr>
            <td class="col-num" style="vertical-align: top;">14.</td>
            <td class="col-label" style="vertical-align: top;">Sarana Transportasi di Tujuan</td>
            <td colspan="4">
                <table style="width: 100%; border-collapse: collapse; border: none;">
                    <tr>
                        <td style="border: none; padding: 2px 0; width: 50%;">
                            <span class="chk-box">{{ $tujuanPenginapan ? 'V' : '' }}</span> Penugasan = Penginapan
                        </td>
                        <td style="border: none; padding: 2px 0; width: 50%;">
                            <span class="chk-box">{{ $tujuanDinas ? 'V' : '' }}</span> Kendaraan Dinas/Operasional
                        </td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 2px 0;">
                            <span class="chk-box">{{ $tujuanSendiri ? 'V' : '' }}</span> Mengatur Sendiri
                        </td>
                        <td style="border: none; padding: 2px 0;">
                            <span class="chk-box">{{ $tujuanSewa ? 'V' : '' }}</span> Sewa Kendaraan
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="6" style="text-align: center; padding: 16px 10px 10px 10px; border-top: 2px solid #000;">
                @php
                    $signatureRender = $signatureRender ?? app(\App\Services\Signature\SignatureService::class)->resolveRenderData(
                        $sppd,
                        $signer ?? null,
                        'manager',
                        $sppd->status === 'approved'
                    );
                @endphp
                <div style="font-weight: bold; font-size: 11px;">Pejabat yang memberi perintah,</div>
                <div style="font-weight: bold; font-size: 11px; margin-bottom: 6px;">User,</div>
                @include('signatures.pdf-stamp', ['render' => $signatureRender, 'align' => 'center', 'emptyHeight' => '48px'])
                <div style="margin-top: 6px;">
                    ( <span style="display: inline-block; min-width: 160px; text-decoration: underline; font-weight: bold;">{{ $signerName }}</span> )
                </div>
            </td>
        </tr>
    </table>

    <table style="width: 100%; margin-top: 6px; font-size: 9px; line-height: 1.35;">
        <tr>
            <td style="vertical-align: top;">
                <div>CATATAN :</div>
                <div>- *): PILIH SALAH SATU JENIS PENUGASAN YANG SESUAI</div>
                <div>- LEMBAR INI DITERBITKAN OLEH PEJABAT YANG BERWENANG MEMBERI PERINTAH PERJALANAN DINAS (minimal setingkat GM)</div>
            </td>
            <td style="vertical-align: top; text-align: right; font-style: italic; font-weight: bold; font-size: 10px;">
                FM-BS-01.00
            </td>
        </tr>
    </table>

</body>
</html>
