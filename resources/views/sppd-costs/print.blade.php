<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Formulir Perhitungan Biaya Perjalanan Dinas - {{ $sppd->sppd_number }}</title>
    <style>
        @page {
            margin: 25px 35px 25px 35px;
        }
        * {
            font-family: Arial, Helvetica, sans-serif !important;
            box-sizing: border-box;
        }
        html, body, table, tbody, thead, tr, td, th, div, span, p, strong, b, em, i {
            font-family: Arial, Helvetica, sans-serif !important;
        }
        body {
            font-size: 11px;
            line-height: 1.3;
            color: #000;
        }
        .header-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .header-title {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            line-height: 1.4;
            text-transform: uppercase;
        }
        .cost-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
        }
        .cost-table th, .cost-table td {
            border: 1px solid #000;
            padding: 4px 8px;
            vertical-align: middle;
            font-size: 11px;
        }
        .th-blue {
            background-color: #bdd7ee;
            font-weight: bold;
            text-align: center;
            font-size: 11px;
            padding: 6px;
            letter-spacing: 0.5px;
        }
        .col-desc {
            width: 52%;
        }
        .col-calc {
            width: 48%;
        }
        .calc-row {
            width: 100%;
            border-collapse: collapse;
        }
        .calc-row td {
            border: none;
            padding: 1px 2px;
            font-size: 11px;
        }
        .qty-col {
            width: 40px;
            text-align: center;
        }
        .x-col {
            width: 20px;
            text-align: center;
        }
        .rate-col {
            width: 110px;
            text-align: right;
            padding-right: 5px;
        }
        .rp-label {
            width: 25px;
            text-align: left;
        }
        .total-col {
            text-align: right;
        }
        .bold-row {
            font-weight: bold;
        }
        .subtotal-row {
            font-weight: bold;
            background-color: #f2f2f2;
        }
        .grand-total-row {
            font-weight: bold;
            font-size: 11px;
            background-color: #e9ecef;
        }
        .indent-1 {
            padding-left: 14px !important;
        }
        .indent-2 {
            padding-left: 28px !important;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 2px solid #000;
        }
        .signature-table td {
            border: none;
            padding: 10px 15px 5px 15px;
            text-align: center;
            vertical-align: top;
            width: 50%;
            font-size: 11px;
        }
        .sign-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 6px;
        }
        .sign-name {
            font-weight: bold;
            font-size: 11px;
            text-decoration: underline;
        }
        .footer-code {
            text-align: right;
            font-size: 10px;
            margin-top: 6px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    @php
        $logoPath = public_path('images/logo-pkj.png');
        $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;

        $fmt = fn ($val) => ($val && $val > 0) ? number_format($val, 0, ',', '.') : '-';
        $fmtQty = fn ($val) => ($val && $val > 0) ? $val : '';
        $fmtRate = fn ($val) => ($val && $val > 0) ? number_format($val, 0, ',', '.') : '';
    @endphp

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td style="width: 15%; text-align: left; vertical-align: top;">
                @if ($logoBase64)
                    <img src="data:image/png;base64,{{ $logoBase64 }}" style="height: 38px;">
                @endif
            </td>
            <td style="width: 85%; vertical-align: middle;">
                <div class="header-title">
                    FORMULIR PERHITUNGAN BIAYA PERJALANAN DINAS<br>
                    PEKERJA PENEMPATAN PT PERMATA KARYA JASA
                </div>
            </td>
        </tr>
    </table>

    {{-- Main Cost Table --}}
    <table class="cost-table">
        <thead>
            <tr>
                <th colspan="2" class="th-blue">
                    URAIAN PENGGUNAAN BIAYA PERJALANAN DINAS
                </th>
            </tr>
        </thead>
        <tbody>
            {{-- 1. BIAYA TRANSPORT --}}
            <tr class="bold-row">
                <td colspan="2">1. Biaya Transport</td>
            </tr>
            <tr>
                <td class="col-desc indent-1">
                    a. Ke Bandar Udara/Stasiun Kereta Api/<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;Terminal Bus/Pelabuhan
                </td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col">{{ $fmtQty($cost->transport_terminal_qty) }}</td>
                            <td class="x-col">x</td>
                            <td class="rate-col">{{ $fmtRate($cost->transport_terminal_rate) }}</td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->transport_terminal_total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-1">b. Sarana Transportasi</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col">{{ $fmtQty($cost->transport_ticket_qty) }}</td>
                            <td class="x-col">x</td>
                            <td class="rate-col">{{ $fmtRate($cost->transport_ticket_rate) }}</td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->transport_ticket_total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-1">c. Di Tempat Tujuan</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col">{{ $fmtQty($cost->transport_local_qty) }}</td>
                            <td class="x-col">x</td>
                            <td class="rate-col">{{ $fmtRate($cost->transport_local_rate) }}</td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->transport_local_total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="subtotal-row">
                <td style="font-weight: bold;">Jumlah Jenis Biaya</td>
                <td>
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col" style="font-weight: bold;">{{ number_format($cost->subtotal_transport, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            {{-- 2. JENIS BIAYA (BIAYA HARIAN) --}}
            <tr class="bold-row">
                <td colspan="2">2. Jenis Biaya</td>
            </tr>
            <tr>
                <td colspan="2" class="indent-1" style="font-weight: bold;">
                    a. Biaya Harian Dinas Dalam Negeri
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-2">a.1. Hotel / Penginapan (sesuai Kuitansi)</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col">{{ $fmtQty($cost->hotel_qty) }}</td>
                            <td class="x-col">x</td>
                            <td class="rate-col">{{ $fmtRate($cost->hotel_rate) }}</td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->hotel_total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-2">a.2. Uang Makan</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col">{{ $fmtQty($cost->meal_qty) }}</td>
                            <td class="x-col">x</td>
                            <td class="rate-col">{{ $fmtRate($cost->meal_rate) }}</td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->meal_total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-2">a.3. Penggantian Makan (sesuai Kuitansi)</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col">{{ $fmtQty($cost->meal_reimbursement_qty) }}</td>
                            <td class="x-col">x</td>
                            <td class="rate-col">{{ $fmtRate($cost->meal_reimbursement_rate) }}</td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->meal_reimbursement_total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-2">a.4. Uang Angkutan Setempat</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col">{{ $fmtQty($cost->local_transit_qty) }}</td>
                            <td class="x-col">x</td>
                            <td class="rate-col">{{ $fmtRate($cost->local_transit_rate) }}</td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->local_transit_total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-2">a.5. Uang Saku</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col">{{ $fmtQty($cost->pocket_money_qty) }}</td>
                            <td class="x-col">x</td>
                            <td class="rate-col">{{ $fmtRate($cost->pocket_money_rate) }}</td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->pocket_money_total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-1">b. Daily Allowance Dinas Luar Negeri</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->overseas_daily_allowance) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="indent-1" style="font-weight: bold;">
                    c. Biaya Harian Dinas Luar Negeri
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-2">c.1. Penggantian Biaya Penginapan & Uang Makan</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->overseas_hotel_meal) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-2">c.2. Transport Lokal</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->overseas_transport) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-2">c.3. Uang Saku</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->overseas_pocket_money) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-1">d. Monthly Allowance Dinas Luar Negeri</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->overseas_monthly_allowance) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="subtotal-row">
                <td style="font-weight: bold;">Jumlah Jenis Biaya</td>
                <td>
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col" style="font-weight: bold;">{{ number_format($cost->subtotal_per_diem, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            {{-- 3. BIAYA LAIN --}}
            <tr class="bold-row">
                <td colspan="2">3. Biaya Lain</td>
            </tr>
            <tr>
                <td class="col-desc indent-1">a. Airport Tax</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->other_airport_tax) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-1">b. Cuci Pakaian</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->other_laundry) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-1">c. Tunjangan Perlengkapan (Luar Negeri)</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->other_equipment) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-1">d. Uang Buku dan Biaya Karya Tulis</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->other_book_paper) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="col-desc indent-1">e. Jamuan untuk Tim Assesor</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->other_assessor) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            @if ($cost->other_car_rental > 0)
            <tr>
                <td class="col-desc indent-1">f. Sewa Mobil</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->other_car_rental) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            @endif
            @if ($cost->other_baggage > 0)
            <tr>
                <td class="col-desc indent-1">g. Over Baggage</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->other_baggage) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            @endif
            @if ($cost->other_toll_fuel_parking > 0)
            <tr>
                <td class="col-desc indent-1">h. Tol, BBM dan Parkir</td>
                <td class="col-calc">
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col">{{ $fmt($cost->other_toll_fuel_parking) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            @endif
            <tr class="subtotal-row">
                <td style="font-weight: bold;">Jumlah Biaya Lain</td>
                <td>
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col">x</td>
                            <td class="rate-col"></td>
                            <td class="rp-label">Rp.</td>
                            <td class="total-col" style="font-weight: bold;">{{ $cost->subtotal_other > 0 ? number_format($cost->subtotal_other, 0, ',', '.') : '-' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            {{-- JUMLAH BIAYA DISETUJUI --}}
            <tr class="grand-total-row">
                <td style="font-size: 11px; font-weight: bold; padding: 6px 8px;">Jumlah Biaya Disetujui</td>
                <td>
                    <table class="calc-row">
                        <tr>
                            <td class="qty-col"></td>
                            <td class="x-col"></td>
                            <td class="rate-col"></td>
                            <td class="rp-label" style="font-weight: bold; font-size: 11px;">Rp.</td>
                            <td class="total-col" style="font-size: 11px; font-weight: bold;">{{ number_format($cost->total_cost, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            {{-- SIGNATURES SECTION --}}
            @php
                $sigService = app(\App\Services\Signature\SignatureService::class);
                $signerSignatureRender = $signerSignatureRender ?? $sigService->resolveRenderData(
                    $sppd,
                    $signer ?? null,
                    'manager',
                    $sppd?->status === 'approved'
                );
                $employeeSignatureRender = $employeeSignatureRender ?? $sigService->resolveRenderData(
                    $sppd,
                    $sppd?->employee,
                    'requester',
                    in_array($sppd?->status, ['pending', 'approved'], true)
                );
            @endphp
            <tr>
                <td colspan="2" style="padding: 0;">
                    <table class="signature-table">
                        <tr>
                            <td>
                                <div class="sign-title">Pejabat yang memberi Perintah</div>
                                @include('signatures.pdf-stamp', ['render' => $signerSignatureRender, 'align' => 'center', 'emptyHeight' => '45px'])
                                <div>( <span class="sign-name">{{ $signerName }}</span> )</div>
                            </td>
                            <td>
                                <div class="sign-title">
                                    Menerima Pembayaran Lunas<br>
                                    Pekerja yang Diperintahkan
                                </div>
                                @include('signatures.pdf-stamp', ['render' => $employeeSignatureRender, 'align' => 'center', 'emptyHeight' => '40px'])
                                <div>( <span class="sign-name">{{ $sppd->employee?->name ?? '____________________' }}</span> )</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="footer-code">FM-BS-02.00</div>

</body>
</html>
