<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reimbursement - {{ $reimbursement->reimbursement_number }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #222;
            line-height: 1.4;
            margin: 20px 25px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #222;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .company-title {
            font-size: 15px;
            font-weight: bold;
            color: #1a365d;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .company-sub {
            font-size: 10px;
            color: #555;
            margin: 2px 0 0 0;
        }
        .doc-title-container {
            text-align: center;
            margin-bottom: 15px;
        }
        .doc-title {
            font-size: 13px;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .doc-number {
            font-size: 11px;
            color: #444;
            margin-top: 3px;
            font-weight: bold;
        }
        .info-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 3px 4px;
            vertical-align: top;
            font-size: 11px;
        }
        .info-table .label {
            width: 18%;
            font-weight: bold;
            color: #333;
        }
        .info-table .separator {
            width: 2%;
            text-align: center;
        }
        .info-table .value {
            width: 30%;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .items-table th, .items-table td {
            border: 1px solid #777;
            padding: 5px 6px;
            font-size: 10.5px;
        }
        .items-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 10px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .summary-box {
            width: 100%;
            margin-bottom: 20px;
        }
        .notes-box {
            border: 1px dashed #999;
            padding: 6px 8px;
            background-color: #fafafa;
            margin-bottom: 20px;
            font-size: 10px;
        }
        .signatures-table {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .sig-role {
            font-weight: bold;
            margin-bottom: 6px;
            font-size: 11px;
        }
        .sig-name {
            font-weight: bold;
            text-decoration: underline;
            font-size: 11px;
        }
        .sig-pos {
            font-size: 10px;
            color: #444;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-approved { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .badge-pending { background-color: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
        .badge-draft { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    </style>
</head>
<body>

    <!-- Header Perusahaan -->
    <table class="header-table">
        <tr>
            <td>
                <h1 class="company-title">PT Permata Karya Jasa</h1>
                <p class="company-sub">Human Capital & General Affairs Management System (MOTIVA)</p>
            </td>
            <td style="text-align: right; width: 35%;">
                <span class="badge {{ $reimbursement->status === 'approved' ? 'badge-approved' : ($reimbursement->status === 'pending' ? 'badge-pending' : 'badge-draft') }}">
                    Status: {{ strtoupper($reimbursement->status) }}
                </span>
            </td>
        </tr>
    </table>

    <!-- Judul Dokumen -->
    <div class="doc-title-container">
        <h2 class="doc-title">Formulir Pengajuan Penggantian Biaya (Reimbursement)</h2>
        <div class="doc-number">Nomor: {{ $reimbursement->reimbursement_number ?? 'DRAF' }}</div>
    </div>

    <!-- Informasi Pegawai & SPPD -->
    <table class="info-table">
        <tr>
            <td class="label">Nama Pegawai</td>
            <td class="separator">:</td>
            <td class="value text-bold">{{ $reimbursement->employee?->name ?? '-' }}</td>
            <td class="label">Terkait SPPD</td>
            <td class="separator">:</td>
            <td class="value text-bold">{{ $reimbursement->sppd?->sppd_number ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">NIP / Identity No</td>
            <td class="separator">:</td>
            <td class="value">{{ $reimbursement->employee?->identity_number ?? '-' }}</td>
            <td class="label">Tujuan Penugasan</td>
            <td class="separator">:</td>
            <td class="value">{{ $reimbursement->sppd?->businessTrip?->destination ?? ($reimbursement->sppd?->destination_location ?? '-') }}</td>
        </tr>
        <tr>
            <td class="label">Jabatan & Departemen</td>
            <td class="separator">:</td>
            <td class="value">{{ $reimbursement->employee?->position?->name ?? '-' }} / {{ $reimbursement->employee?->department?->name ?? '-' }}</td>
            <td class="label">Tanggal Pengajuan</td>
            <td class="separator">:</td>
            <td class="value">{{ $reimbursement->created_at?->format('d/m/Y') ?? date('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Keperluan / Judul</td>
            <td class="separator">:</td>
            <td class="value" colspan="4"><strong>{{ $reimbursement->title ?? 'Reimbursement Biaya Perjalanan Dinas' }}</strong></td>
        </tr>
    </table>

    <!-- Tabel Rincian Biaya Pengeluaran -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 14%;">Tanggal Kwitansi</th>
                <th style="width: 22%;">Jenis Pengeluaran</th>
                <th style="width: 25%;">Keterangan / Rincian</th>
                <th style="width: 16%;">Status Pembayaran</th>
                <th style="width: 18%;">Jumlah Biaya</th>
            </tr>
        </thead>
        <tbody>
            @php
                $expenses = $reimbursement->expenses;
                $totalCompany = 0;
                $totalClaim = 0;
            @endphp
            @forelse ($expenses as $idx => $item)
                @php
                    if ($item->is_company_paid) {
                        $totalCompany += (float) $item->amount;
                    } else {
                        $totalClaim += (float) $item->amount;
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-center">{{ $item->transaction_date ? \Carbon\Carbon::parse($item->transaction_date)->format('d/m/Y') : '-' }}</td>
                    <td>{{ $item->expense_type }}</td>
                    <td>{{ $item->description ?: '-' }}</td>
                    <td class="text-center">
                        @if ($item->is_company_paid)
                            <span style="color: #0369a1; font-weight: bold;">Dibayar Kantor</span>
                        @else
                            <span style="color: #b45309; font-weight: bold;">Klaim Pegawai</span>
                        @endif
                    </td>
                    <td class="text-right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 12px; color: #888;">Belum ada item pengeluaran yang diinput.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f8fafc;">
                <td colspan="5" class="text-right text-bold">Total Pengeluaran Ditalangi Kantor (Company Paid):</td>
                <td class="text-right text-bold">Rp {{ number_format($totalCompany, 0, ',', '.') }}</td>
            </tr>
            <tr style="background-color: #fefce8; font-size: 11.5px;">
                <td colspan="5" class="text-right text-bold" style="color: #854d0e;">TOTAL KLAIM YANG HARUS DIGANTI KE PEGAWAI:</td>
                <td class="text-right text-bold" style="color: #854d0e;">Rp {{ number_format($totalClaim, 0, ',', '.') }}</td>
            </tr>
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="5" class="text-right">Grand Total Seluruh Pengeluaran:</td>
                <td class="text-right">Rp {{ number_format($totalCompany + $totalClaim, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <!-- Catatan Tambahan -->
    @if (!empty($reimbursement->notes))
        <div class="notes-box">
            <strong>Catatan Tambahan Pengaju:</strong><br>
            {{ $reimbursement->notes }}
        </div>
    @endif

    <!-- Tanda Tangan -->
    @php
        $sigService = app(\App\Services\Signature\SignatureService::class);
        $requesterSignatureRender = $requesterSignatureRender ?? $sigService->resolveRenderData(
            $reimbursement,
            $reimbursement->employee,
            'requester',
            in_array($reimbursement->status, ['pending', 'approved', 'paid'], true)
        );
        $managerSignatureRender = $managerSignatureRender ?? $sigService->resolveRenderData(
            $reimbursement,
            $manager ?? null,
            'manager',
            in_array($reimbursement->status, ['approved', 'paid'], true)
        );
    @endphp
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-role">Pegawai Pengaju,</div>
                @include('signatures.pdf-stamp', ['render' => $requesterSignatureRender, 'align' => 'center', 'emptyHeight' => '48px'])
                <div class="sig-name">{{ $reimbursement->employee?->name ?? 'Pegawai' }}</div>
                <div class="sig-pos">{{ $reimbursement->employee?->position?->name ?? 'Karyawan' }}</div>
            </td>
            <td>
                <div class="sig-role">
                    Disetujui Oleh,<br>
                    <span style="font-size: 9px; font-weight: normal; color: #666;">
                        {{ $reimbursement->approved_at ? 'Pada ' . $reimbursement->approved_at->format('d M Y H:i') : '(Persetujuan Manajer)' }}
                    </span>
                </div>
                @include('signatures.pdf-stamp', ['render' => $managerSignatureRender, 'align' => 'center', 'emptyHeight' => '48px'])
                <div class="sig-name">{{ $managerName }}</div>
                <div class="sig-pos">{{ $managerPosition }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
