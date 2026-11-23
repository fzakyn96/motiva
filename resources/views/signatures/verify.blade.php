<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Tanda Tangan — MOTIVA</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            padding: 24px 16px;
        }
        .card {
            max-width: 560px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .header {
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .header-valid {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        }
        .header-invalid {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .badge-valid {
            background: #059669;
            color: #ffffff;
        }
        .badge-invalid {
            background: #dc2626;
            color: #ffffff;
        }
        .title {
            margin: 12px 0 4px 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
        }
        .subtitle {
            margin: 0;
            font-size: 13px;
            color: #475569;
        }
        .body {
            padding: 24px;
        }
        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            margin: 0 0 12px 0;
            padding-bottom: 6px;
            border-bottom: 1px solid #f1f5f9;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13.5px;
            border-bottom: 1px dashed #f1f5f9;
            gap: 12px;
        }
        .row:last-child {
            border-bottom: none;
        }
        .label {
            color: #64748b;
            flex-shrink: 0;
        }
        .value {
            font-weight: 600;
            color: #0f172a;
            text-align: right;
            word-break: break-word;
        }
        .token-box {
            margin-top: 18px;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-family: monospace;
            font-size: 11px;
            color: #475569;
            word-break: break-all;
        }
        .footer {
            padding: 14px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 11.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="card">
        @if($signature)
            <div class="header header-valid">
                <span class="badge badge-valid">✓ Tanda Tangan Elektronik Terverifikasi</span>
                <h1 class="title">Dokumen Resmi & Sah</h1>
                <p class="subtitle">MOTIVA Digital Signature Verification Service</p>
            </div>

            <div class="body">
                <h2 class="section-title">Informasi Penandatangan</h2>
                <div class="row">
                    <span class="label">Nama Penandatangan</span>
                    <span class="value">{{ $signature->signer_name }}</span>
                </div>
                <div class="row">
                    <span class="label">Jabatan</span>
                    <span class="value">{{ $signature->signer_position ?: '-' }}</span>
                </div>
                @if($signature->signer_nip)
                    <div class="row">
                        <span class="label">NIP / Nomor Induk</span>
                        <span class="value">{{ $signature->signer_nip }}</span>
                    </div>
                @endif
                <div class="row">
                    <span class="label">Peran Pengesahan</span>
                    <span class="value">{{ $signature->role_label }}</span>
                </div>
                <div class="row">
                    <span class="label">Waktu Penandatanganan</span>
                    <span class="value">{{ $signature->signed_at?->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i:s') }} WIB</span>
                </div>

                <h2 class="section-title" style="margin-top: 20px;">Informasi Dokumen</h2>
                <div class="row">
                    <span class="label">Jenis Dokumen</span>
                    <span class="value">{{ $signature->document_type_label }}</span>
                </div>
                <div class="row">
                    <span class="label">Nomor Dokumen</span>
                    <span class="value">{{ $signature->document_number ?: '-' }}</span>
                </div>
                @if($documentSummary)
                    <div class="row">
                        <span class="label">Perihal / Keperluan</span>
                        <span class="value">{{ $documentSummary }}</span>
                    </div>
                @endif
                @if($document && isset($document->status))
                    <div class="row">
                        <span class="label">Status Dokumen</span>
                        <span class="value">{{ strtoupper($document->status) }}</span>
                    </div>
                @endif

                <div class="token-box">
                    <strong>Digital Signature Token:</strong><br>
                    {{ $signature->token }}
                </div>

                @if($document)
                    <div style="margin-top: 20px; display: flex; flex-wrap: wrap; gap: 10px;">
                        <a href="{{ route('signatures.download', ['token' => $signature->token]) }}"
                           style="flex: 1 1 200px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 16px; border-radius: 10px; background: #059669; color: #ffffff; font-size: 13.5px; font-weight: 700; text-decoration: none; text-align: center; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);">
                            ⬇ Unduh Dokumen Resmi (PDF)
                        </a>
                        <a href="{{ route('signatures.download', ['token' => $signature->token, 'inline' => 1]) }}"
                           target="_blank"
                           style="flex: 1 1 160px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 16px; border-radius: 10px; background: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; font-size: 13.5px; font-weight: 600; text-decoration: none; text-align: center;">
                            👁 Lihat Dokumen (PDF)
                        </a>
                    </div>
                @endif
            </div>
        @else
            <div class="header header-invalid">
                <span class="badge badge-invalid">✕ Tanda Tangan Tidak Ditemukan</span>
                <h1 class="title">Verifikasi Gagal</h1>
                <p class="subtitle">Token tanda tangan tidak terdaftar atau tidak valid pada sistem MOTIVA.</p>
            </div>
            <div class="body">
                <div class="token-box">
                    <strong>Token yang Diperiksa:</strong><br>
                    {{ $token }}
                </div>
            </div>
        @endif

        <div class="footer">
            MOTIVA Digital Signature Verification Service
        </div>
    </div>
</body>
</html>

