<x-filament-panels::page>
    <style>
        .wf-wrap { display: flex; flex-direction: column; gap: 1.25rem; }
        .wf-tabs { display: flex; flex-wrap: wrap; gap: 0.5rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.75rem; }
        .dark .wf-tabs { border-color: #374151; }
        .wf-tab { padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: 1px solid #d1d5db; background: #ffffff; color: #374151; transition: all 0.15s ease; }
        .dark .wf-tab { background: #1f2937; border-color: #4b5563; color: #e5e7eb; }
        .wf-tab:hover { background: #f9fafb; }
        .dark .wf-tab:hover { background: #374151; }
        .wf-tab-active { background: #d97706 !important; border-color: #d97706 !important; color: #ffffff !important; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }

        .wf-legend { display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.75rem; color: #4b5563; align-items: center; }
        .dark .wf-legend { color: #9ca3af; }
        .wf-legend-item { display: inline-flex; align-items: center; gap: 0.4rem; }
        .wf-dot { width: 10px; height: 10px; border-radius: 9999px; display: inline-block; }
        .wf-diamond-icon { width: 10px; height: 10px; transform: rotate(45deg); display: inline-block; border-radius: 2px; }

        .wf-board { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.875rem; padding: 2rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .dark .wf-board { background: #111827; border-color: #1f2937; }

        .wf-flow { max-width: 860px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; }
        .wf-pill { padding: 0.45rem 1.25rem; border-radius: 9999px; background: #111827; color: #ffffff; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; text-align: center; }
        .dark .wf-pill { background: #f3f4f6; color: #111827; }

        .wf-arrow { display: flex; flex-direction: column; align-items: center; padding: 0.25rem 0; color: #9ca3af; font-size: 0.75rem; font-weight: 600; gap: 0.15rem; }
        .wf-arrow svg { width: 20px; height: 22px; stroke: currentColor; fill: none; stroke-width: 2; }

        .wf-node { width: 100%; max-width: 540px; border-radius: 0.75rem; border: 2px solid #cbd5e1; background: #f8fafc; padding: 1rem 1.125rem; text-align: center; }
        .dark .wf-node { background: #1e293b; border-color: #334155; }
        .wf-node-wide { max-width: 780px; }

        .wf-node-primary { border-color: #3b82f6; background: #eff6ff; }
        .dark .wf-node-primary { border-color: #3b82f6; background: rgba(30, 58, 138, 0.22); }
        .wf-node-info { border-color: #0ea5e9; background: #f0f9ff; }
        .dark .wf-node-info { border-color: #0ea5e9; background: rgba(12, 74, 110, 0.22); }
        .wf-node-warning { border-color: #f59e0b; background: #fffbeb; }
        .dark .wf-node-warning { border-color: #f59e0b; background: rgba(120, 53, 15, 0.22); }
        .wf-node-success { border-color: #10b981; background: #ecfdf5; }
        .dark .wf-node-success { border-color: #10b981; background: rgba(6, 78, 59, 0.25); }
        .wf-node-danger { border-color: #ef4444; background: #fef2f2; }
        .dark .wf-node-danger { border-color: #ef4444; background: rgba(127, 29, 29, 0.22); }

        .wf-node-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 0.35rem; }
        .wf-node-title { font-size: 0.8125rem; font-weight: 700; color: #111827; text-transform: uppercase; letter-spacing: 0.02em; }
        .dark .wf-node-title { color: #f9fafb; }
        .wf-node-desc { font-size: 0.78rem; color: #4b5563; line-height: 1.45; margin: 0; }
        .dark .wf-node-desc { color: #d1d5db; }

        .wf-badge { display: inline-flex; align-items: center; padding: 0.125rem 0.55rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; }
        .wf-badge-warn { background: #fef3c7; color: #92400e; }
        .wf-badge-info { background: #e0f2fe; color: #075985; }
        .wf-badge-ok { background: #d1fae5; color: #065f46; }
        .wf-badge-gray { background: #e5e7eb; color: #374151; }

        .wf-links { margin-top: 0.6rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem; }
        .wf-link { font-size: 0.75rem; font-weight: 600; color: #2563eb; text-decoration: none; }
        .wf-link:hover { text-decoration: underline; }
        .dark .wf-link { color: #60a5fa; }

        .wf-branch-2 { width: 100%; max-width: 780px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .wf-branch-3 { width: 100%; max-width: 820px; display: grid; grid-template-columns: 1fr 1.3fr 1fr; gap: 0.85rem; align-items: center; }
        .wf-substeps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem; margin-top: 0.75rem; text-align: left; }
        .wf-substep { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.65rem 0.75rem; font-size: 0.75rem; color: #374151; }
        .dark .wf-substep { background: #0f172a; border-color: #334155; color: #e2e8f0; }

        @media (max-width: 768px) {
            .wf-branch-2, .wf-branch-3, .wf-substeps { grid-template-columns: 1fr; }
        }

        .wf-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .wf-table th, .wf-table td { border: 1px solid #e5e7eb; padding: 0.75rem 0.9rem; text-align: left; vertical-align: top; }
        .dark .wf-table th, .dark .wf-table td { border-color: #374151; }
        .wf-table th { background: #f3f4f6; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: #374151; }
        .dark .wf-table th { background: #1f2937; color: #d1d5db; }
    </style>

    <div x-data="{ tab: 'sppd' }" class="wf-wrap">
        {{-- Tab Navigation --}}
        <div class="wf-tabs">
            <button type="button" @click="tab = 'sppd'" :class="tab === 'sppd' ? 'wf-tab wf-tab-active' : 'wf-tab'">
                1. Flowchart Perjalanan Dinas (SPPD)
            </button>
            <button type="button" @click="tab = 'lembur'" :class="tab === 'lembur' ? 'wf-tab wf-tab-active' : 'wf-tab'">
                2. Flowchart Kerja Lembur (SPKL)
            </button>
            <button type="button" @click="tab = 'roles'" :class="tab === 'roles' ? 'wf-tab wf-tab-active' : 'wf-tab'">
                3. Matriks Peran &amp; Akses
            </button>
        </div>

        {{-- Legend --}}
        <div class="wf-legend">
            <span class="wf-legend-item"><span class="wf-dot" style="background:#3b82f6;"></span> Pengajuan / Proses</span>
            <span class="wf-legend-item"><span class="wf-diamond-icon" style="background:#f59e0b;"></span> Validasi / Keputusan</span>
            <span class="wf-legend-item"><span class="wf-dot" style="background:#10b981;"></span> Disetujui / Selesai</span>
            <span class="wf-legend-item"><span class="wf-dot" style="background:#ef4444;"></span> Ditolak / Diblokir</span>
        </div>

        {{-- TAB 1: FLOWCHART PERJALANAN DINAS --}}
        <div x-show="tab === 'sppd'" class="wf-board">
            <div class="wf-flow">
                <div class="wf-pill">Mulai: Perencanaan Dinas (HR / SPV / Manajer)</div>

                <div class="wf-arrow">
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-node wf-node-primary">
                    <div class="wf-node-head">
                        <span class="wf-node-title">1. Buat Memo Perjalanan Dinas</span>
                        <span class="wf-badge wf-badge-warn">{{ $counts['memo_pending'] }} Pending</span>
                    </div>
                    <p class="wf-node-desc">Menentukan agenda, peserta dinas, tanggal, dan lokasi penugasan.</p>
                    @if($links['memos'])
                        <div class="wf-links"><a href="{{ $links['memos'] }}" class="wf-link">Buka Menu Memo →</a></div>
                    @endif
                </div>

                <div class="wf-arrow">
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-branch-3">
                    <div class="wf-node wf-node-danger" style="padding: 0.75rem;">
                        <div class="wf-node-title" style="font-size:0.75rem; color:#b91c1c;">Jika Ditolak</div>
                        <p class="wf-node-desc">Revisi atau dibatalkan oleh pengaju.</p>
                    </div>

                    <div class="wf-node wf-node-warning">
                        <div class="wf-node-head">
                            <span class="wf-node-title">◇ Persetujuan Memo</span>
                        </div>
                        <p class="wf-node-desc">Disetujui oleh Manajer / Division Head?</p>
                        @if($links['memo_approvals'])
                            <div class="wf-links"><a href="{{ $links['memo_approvals'] }}" class="wf-link">Antrean ({{ $counts['memo_approval_pending'] }}) →</a></div>
                        @endif
                    </div>

                    <div class="wf-node wf-node-info" style="padding: 0.75rem;">
                        <div class="wf-node-title" style="font-size:0.75rem; color:#0369a1;">Notifikasi Email</div>
                        <p class="wf-node-desc">Terkirim otomatis ke approver &amp; pembuat.</p>
                    </div>
                </div>

                <div class="wf-arrow">
                    <span style="color:#059669;">Ya (Disetujui)</span>
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-node wf-node-info">
                    <div class="wf-node-head">
                        <span class="wf-node-title">2. Pengajuan SPPD</span>
                        <span class="wf-badge wf-badge-warn">{{ $counts['sppd_pending'] }} Proses</span>
                    </div>
                    <p class="wf-node-desc">Dokumen SPPD diterbitkan untuk setiap pegawai yang ditugaskan.</p>
                    @if($links['sppds'])
                        <div class="wf-links"><a href="{{ $links['sppds'] }}" class="wf-link">Buka Menu SPPD →</a></div>
                    @endif
                </div>

                <div class="wf-arrow">
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-branch-2">
                    <div class="wf-node wf-node-warning">
                        <div class="wf-node-head"><span class="wf-node-title">Jalur A: Unit Memiliki SPV</span></div>
                        <p class="wf-node-desc">Persetujuan &amp; TTD QR <strong>Supervisor</strong> → diteruskan ke <strong>Manajer</strong>.</p>
                    </div>
                    <div class="wf-node wf-node-info">
                        <div class="wf-node-head"><span class="wf-node-title">Jalur B: Tanpa SPV</span></div>
                        <p class="wf-node-desc">Langsung menuju Persetujuan &amp; TTD QR <strong>Manajer</strong>.</p>
                    </div>
                </div>

                <div class="wf-arrow">
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-node wf-node-success">
                    <div class="wf-node-head">
                        <span class="wf-node-title">3. SPPD Disetujui &amp; Nomor Terbit</span>
                    </div>
                    <p class="wf-node-desc">TTD QR SPV &amp; Manajer tercetak pada PDF SPPD (pegawai yang ditugaskan tidak perlu menandatangani dokumen SPPD).</p>
                    @if($links['sppd_approvals'])
                        <div class="wf-links"><a href="{{ $links['sppd_approvals'] }}" class="wf-link">Antrean Persetujuan SPPD ({{ $counts['sppd_approval_pending'] }}) →</a></div>
                    @endif
                </div>

                <div class="wf-arrow">
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-node wf-node-wide wf-node-primary">
                    <div class="wf-node-head">
                        <span class="wf-node-title">4. Perhitungan &amp; Persetujuan Biaya SPPD</span>
                        <span class="wf-badge wf-badge-gray">{{ $counts['cost_total'] }} Dokumen</span>
                    </div>
                    <div class="wf-substeps">
                        <div class="wf-substep"><strong>a. HR Input Biaya</strong><br>Mengisi rincian uang harian, transport, hotel, &amp; bukti lampiran.</div>
                        <div class="wf-substep"><strong>b. Pegawai Menyetujui</strong><br>Pegawai yang ditugaskan review &amp; TTD QR pada Biaya SPPD.</div>
                        <div class="wf-substep"><strong>c. Manajer Mengesahkan</strong><br>Persetujuan akhir &amp; TTD QR Manajer pada dokumen Biaya SPPD.</div>
                    </div>
                    <div class="wf-links">
                        @if($links['sppd_costs'])
                            <a href="{{ $links['sppd_costs'] }}" class="wf-link">Buka Biaya SPPD →</a>
                        @endif
                        @if($links['sppd_cost_approvals'])
                            <a href="{{ $links['sppd_cost_approvals'] }}" class="wf-link">Persetujuan Biaya ({{ $counts['cost_approval_pending'] }}) →</a>
                        @endif
                    </div>
                </div>

                <div class="wf-arrow">
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-node wf-node-success">
                    <div class="wf-node-head">
                        <span class="wf-node-title">5. Selesai: Rekap Transport &amp; Rekap Perjalanan Dinas</span>
                    </div>
                    <p class="wf-node-desc">Disahkan oleh Business Division Head dan siap dicetak PDF.</p>
                    <div class="wf-links">
                        @if($links['transport_recaps'])
                            <a href="{{ $links['transport_recaps'] }}" class="wf-link">Rekap Transport ({{ $counts['transport_recap_total'] }}) →</a>
                        @endif
                        @if($links['travel_recaps'])
                            <a href="{{ $links['travel_recaps'] }}" class="wf-link">Rekap Dinas ({{ $counts['travel_recap_total'] }}) →</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 2: FLOWCHART KERJA LEMBUR --}}
        <div x-show="tab === 'lembur'" x-cloak class="wf-board">
            <div class="wf-flow">
                <div class="wf-pill">Mulai: Pengajuan SPKL (HR / Manajer / SPV)</div>

                <div class="wf-arrow">
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-node wf-node-primary">
                    <div class="wf-node-head">
                        <span class="wf-node-title">1. Buat Perintah Lembur (Bagian A)</span>
                        <span class="wf-badge wf-badge-warn">{{ $counts['ot_command_pending'] }} Pending</span>
                    </div>
                    <p class="wf-node-desc">Pilih Pegawai, Satuan Kerja, Tanggal, Status Shift/Non-Shift, &amp; Uraian Tugas. Klasifikasi <code>N</code> / <code>L</code> otomatis (5 hari kerja + Google Calendar).</p>
                    @if($links['overtimes'])
                        <div class="wf-links"><a href="{{ $links['overtimes'] }}" class="wf-link">Buka Menu Lembur (SPKL) →</a></div>
                    @endif
                </div>

                <div class="wf-arrow">
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-branch-3">
                    <div class="wf-node wf-node-danger" style="padding: 0.75rem;">
                        <div class="wf-node-title" style="font-size:0.75rem; color:#b91c1c;">Mode Block</div>
                        <p class="wf-node-desc">Pengajuan ditolak otomatis jika melebihi batas.</p>
                    </div>

                    <div class="wf-node wf-node-warning">
                        <div class="wf-node-head"><span class="wf-node-title">◇ Validasi PP 35/2021</span></div>
                        <p class="wf-node-desc">Cek batas maksimal <strong>4 jam/hari (N)</strong> &amp; <strong>18 jam/minggu</strong>.</p>
                    </div>

                    <div class="wf-node wf-node-warning" style="padding: 0.75rem;">
                        <div class="wf-node-title" style="font-size:0.75rem; color:#b45309;">Mode Warning</div>
                        <p class="wf-node-desc">Bisa diajukan dengan <strong>Wajib Catatan Khusus</strong>.</p>
                    </div>
                </div>

                <div class="wf-arrow">
                    <span style="color:#059669;">Lolos Validasi</span>
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-node wf-node-info">
                    <div class="wf-node-head">
                        <span class="wf-node-title">2. Persetujuan Perintah oleh Manajer</span>
                        <span class="wf-badge wf-badge-info">{{ $counts['ot_approval_pending'] }} Antrean</span>
                    </div>
                    <p class="wf-node-desc">Manajer menyetujui &amp; TTD QR Bagian A → Terbit Nomor SPKL otomatis + Email notifikasi ke pegawai.</p>
                    @if($links['overtime_approvals'])
                        <div class="wf-links"><a href="{{ $links['overtime_approvals'] }}" class="wf-link">Buka Persetujuan Lembur →</a></div>
                    @endif
                </div>

                <div class="wf-arrow">
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-node wf-node-warning">
                    <div class="wf-node-head">
                        <span class="wf-node-title">3. Pertanggungjawaban Pegawai (Bagian B)</span>
                        <span class="wf-badge wf-badge-info">{{ $counts['ot_report_await_worker'] }} Menunggu Pegawai</span>
                    </div>
                    <p class="wf-node-desc">Pegawai mengisi jam mulai/selesai &amp; hasil kerja + TTD QR. Sistem menghitung durasi, koefisien (5 hari kerja), &amp; upah lembur <code>MAX(75%×(UD+TP)/173, UD/173)</code>.</p>
                </div>

                <div class="wf-arrow">
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-node wf-node-success">
                    <div class="wf-node-head">
                        <span class="wf-node-title">4. Pengesahan Akhir Manajer (Bagian B)</span>
                        <span class="wf-badge wf-badge-ok">{{ $counts['ot_approved'] }} Disetujui</span>
                    </div>
                    <p class="wf-node-desc">Manajer mengesahkan &amp; TTD QR Bagian B ({{ $counts['ot_report_pending_mgr'] }} menunggu pengesahan) → SPKL selesai &amp; siap cetak PDF FS-05.</p>
                </div>

                <div class="wf-arrow">
                    <svg viewBox="0 0 24 24"><path d="M12 3v16m0 0l-5-5m5 5l5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wf-node wf-node-success">
                    <div class="wf-node-head">
                        <span class="wf-node-title">5. Rekapitulasi Bulanan &amp; Cetak PDF</span>
                        <span class="wf-badge wf-badge-ok">{{ $counts['ot_recap_total'] }} Rekap</span>
                    </div>
                    <p class="wf-node-desc">Memisahkan kolom <strong>Lembur Shift</strong>, <strong>Lembur Non-Shift</strong>, &amp; <strong>Grand Total</strong> dengan TTD Business Division Head.</p>
                    <div class="wf-links">
                        @if($links['overtime_recaps'])
                            <a href="{{ $links['overtime_recaps'] }}" class="wf-link">Rekap Lembur →</a>
                        @endif
                        @if($links['national_holidays'])
                            <a href="{{ $links['national_holidays'] }}" class="wf-link">Libur Nasional →</a>
                        @endif
                        @if($links['overtime_settings'])
                            <a href="{{ $links['overtime_settings'] }}" class="wf-link">Pengaturan Lembur →</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 3: MATRIKS PERAN --}}
        <div x-show="tab === 'roles'" x-cloak class="wf-board">
            <h3 style="font-size:1rem; font-weight:700; margin:0 0 1rem 0;">Matriks Peran &amp; Tanggung Jawab Pengguna</h3>
            <div style="overflow-x:auto;">
                <table class="wf-table">
                    <thead>
                        <tr>
                            <th>Peran (Role)</th>
                            <th>Perjalanan Dinas (SPPD &amp; Biaya)</th>
                            <th>Kerja Lembur (SPKL &amp; Rekap)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Staff / Pegawai</strong></td>
                            <td>Melihat SPPD yang ditugaskan; melihat &amp; menyetujui/TTD QR Biaya SPPD miliknya.</td>
                            <td>Mengisi &amp; TTD QR Pertanggungjawaban Lembur (Bagian B) serta mencetak PDF SPKL miliknya.</td>
                        </tr>
                        <tr>
                            <td><strong>Supervisor (SPV)</strong></td>
                            <td>Menyetujui &amp; TTD QR SPPD tim sebelum diteruskan ke Manajer.</td>
                            <td>Dapat membuat/mengajukan SPKL (Bagian A) jika diaktifkan di Pengaturan Lembur.</td>
                        </tr>
                        <tr>
                            <td><strong>HR (Human Resources)</strong></td>
                            <td>Mengelola Memo, SPPD, input Biaya SPPD, serta menyusun Rekap Transport &amp; Perjalanan Dinas.</td>
                            <td>Mengatur komponen UD/TP pegawai, Libur Nasional, Pengaturan Lembur, membuat SPKL, &amp; membuat Rekap Bulanan Lembur.</td>
                        </tr>
                        <tr>
                            <td><strong>Manajer</strong></td>
                            <td>Menyetujui &amp; TTD QR Memo, SPPD, dan Biaya SPPD sesuai area kerjanya.</td>
                            <td>Pejabat yang memerintahkan: menyetujui &amp; TTD QR Bagian A (Perintah) dan Bagian B (Pertanggungjawaban).</td>
                        </tr>
                        <tr>
                            <td><strong>Division Head</strong></td>
                            <td>Menyetujui &amp; menandatangani Rekap Transport &amp; Rekap Biaya Perjalanan Dinas.</td>
                            <td>Penandatangan Rekapitulasi Biaya Reimbursement Lembur Bulanan.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
