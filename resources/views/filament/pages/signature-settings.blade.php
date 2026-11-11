<x-filament-panels::page>
    @php
        /** @var \App\Models\User|null $currentUser */
        $currentUser = auth()->user();
        $employee = $currentUser?->employee;
        $isConfigured = $currentUser?->hasConfiguredSignature() ?? false;
        $savedImageUri = $currentUser?->getSignatureImageDataUri();
        $setting = \App\Models\SignatureSetting::current();
        $allowedMethods = $this->allowedMethods;
    @endphp

    <style>
        .sig-info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        @media (min-width: 640px) {
            .sig-info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (min-width: 1024px) {
            .sig-info-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .sig-two-col {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        @media (min-width: 1024px) {
            .sig-two-col {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .sig-method-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .sig-method-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .sig-method-card {
            display: flex;
            align-items: flex-start;
            gap: 0.875rem;
            padding: 1rem 1.125rem;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            border: 1px solid rgba(15, 23, 42, 0.1);
            background-color: transparent;
        }
        .dark .sig-method-card {
            border-color: rgba(255, 255, 255, 0.12);
        }
        .sig-method-card:hover {
            border-color: rgba(217, 119, 6, 0.5);
        }
        .sig-method-card.is-active {
            border: 2px solid #d97706;
            background-color: rgba(251, 191, 36, 0.06);
        }
        .dark .sig-method-card.is-active {
            border-color: #f59e0b;
            background-color: rgba(245, 158, 11, 0.1);
        }

        .sig-option-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            border-radius: 0.625rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            cursor: pointer;
        }
        .dark .sig-option-item {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .sig-canvas-box {
            position: relative;
            border-radius: 0.75rem;
            border: 2px dashed #cbd5e1;
            background-color: #ffffff;
            overflow: hidden;
        }
        .dark .sig-canvas-box {
            border-color: #475569;
        }

        .sig-preview-box {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1rem;
            border-radius: 0.625rem;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            min-height: 4.5rem;
            min-width: 9rem;
        }

        .sig-ink-btn {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 9999px;
            border: 2px solid #ffffff;
            cursor: pointer;
            transition: transform 0.12s ease;
        }
        .sig-ink-btn.active {
            outline: 2px solid #d97706;
            outline-offset: 2px;
            transform: scale(1.08);
        }
    </style>

    {{-- 1. Ringkasan Status Tanda Tangan User --}}
    <x-filament::section
        icon="heroicon-o-finger-print"
        icon-color="primary"
    >
        <x-slot name="heading">
            Status Tanda Tangan
        </x-slot>

        <x-slot name="headerEnd">
            @if ($isConfigured)
                <x-filament::badge color="success" icon="heroicon-m-check-circle" size="md">
                    Tanda Tangan Aktif & Siap Digunakan
                </x-filament::badge>
            @else
                <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle" size="md">
                    Belum Diatur (Wajib Diatur 1 Kali)
                </x-filament::badge>
            @endif
        </x-slot>

        <x-slot name="description">
            @if ($isConfigured)
                Tanda tangan Anda sudah tersimpan dan akan otomatis dibubuhkan setiap kali Anda menyetujui atau mengajukan dokumen tanpa perlu tanda tangan berulang.
            @else
                Anda belum mengatur tanda tangan. Silakan atur tanda tangan cukup 1 kali pada formulir di bawah ini agar Anda dapat menyetujui atau mengajukan dokumen.
            @endif
        </x-slot>

        <div class="sig-info-grid">
            {{-- Kolom 1: Identitas Penandatangan --}}
            <div>
                <div class="flex items-center text-xs font-medium text-gray-500 dark:text-gray-400" style="margin-bottom: 0.375rem;">
                    <x-heroicon-m-user class="text-gray-400 dark:text-gray-500 shrink-0" style="width: 1rem; height: 1rem; margin-right: 0.5rem;" />
                    <span>Identitas Penandatangan</span>
                </div>
                <div class="text-sm font-bold text-gray-950 dark:text-white" style="margin-bottom: 0.25rem;">
                    {{ $employee?->name ?? $currentUser?->name ?? '-' }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                    {{ $employee?->identity_number ? "NIP: {$employee->identity_number}" : $currentUser?->email }}
                    @if ($employee?->position)
                        &bull; {{ $employee->position->name }}
                    @endif
                    @if ($employee?->department)
                        &bull; {{ $employee->department->name }}
                    @endif
                </div>
            </div>

            {{-- Kolom 2: Metode Aktif --}}
            <div>
                <div class="flex items-center text-xs font-medium text-gray-500 dark:text-gray-400" style="margin-bottom: 0.375rem;">
                    <x-heroicon-m-adjustments-horizontal class="text-gray-400 dark:text-gray-500 shrink-0" style="width: 1rem; height: 1rem; margin-right: 0.5rem;" />
                    <span>Metode Tanda Tangan User</span>
                </div>
                <div class="text-sm font-bold text-gray-950 dark:text-white" style="margin-bottom: 0.25rem;">
                    @if ($isConfigured)
                        {{ match ($currentUser?->signature_type) {
                            'draw' => 'Goresan Langsung (Draw Pad)',
                            'upload' => 'Upload Gambar Tanda Tangan',
                            'qrcode' => 'QR Code Terverifikasi',
                            default => 'Tanda Tangan',
                        } }}
                    @else
                        Belum Dipilih
                    @endif
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    @if ($currentUser?->signature_updated_at)
                        Diperbarui {{ $currentUser->signature_updated_at->diffForHumans() }}
                    @else
                        Silakan simpan pengaturan di bawah
                    @endif
                </div>
            </div>

            {{-- Kolom 3: Kebijakan Tampilan Dokumen PDF --}}
            <div>
                <div class="flex items-center text-xs font-medium text-gray-500 dark:text-gray-400" style="margin-bottom: 0.375rem;">
                    <x-heroicon-m-document-check class="text-gray-400 dark:text-gray-500 shrink-0" style="width: 1rem; height: 1rem; margin-right: 0.5rem;" />
                    <span>Mode Cetakan Dokumen (PDF)</span>
                </div>
                <div class="text-sm font-bold text-gray-950 dark:text-white" style="margin-bottom: 0.25rem;">
                    {{ match ($setting->document_display_mode) {
                        'draw_or_upload' => 'Visual (Goresan / Upload)',
                        'qrcode_only' => 'QR Code Verifikasi Saja',
                        'visual_and_qrcode' => 'Visual + QR Code Verifikasi',
                        default => 'Mengikuti Metode User',
                    } }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Ditetapkan oleh kebijakan sistem
                </div>
            </div>

            {{-- Kolom 4: Preview & Aksi Reset --}}
            <div>
                <div class="flex items-center text-xs font-medium text-gray-500 dark:text-gray-400" style="margin-bottom: 0.375rem;">
                    <x-heroicon-m-eye class="text-gray-400 dark:text-gray-500 shrink-0" style="width: 1rem; height: 1rem; margin-right: 0.5rem;" />
                    <span>Preview Tanda Tangan Aktif</span>
                </div>

                @if ($isConfigured)
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
                        <div class="sig-preview-box">
                            @if ($currentUser?->signature_type === 'qrcode' || $setting->document_display_mode === 'qrcode_only')
                                <img src="{{ $this->sampleQrDataUri }}" alt="QR Signature Preview" style="height: 3.25rem; width: 3.25rem; object-fit: contain;">
                            @elseif ($savedImageUri)
                                <img src="{{ $savedImageUri }}" alt="Saved Signature" style="height: 3.25rem; max-width: 9rem; object-fit: contain;">
                            @endif
                        </div>

                        <x-filament::button
                            color="danger"
                            outlined
                            size="xs"
                            icon="heroicon-m-trash"
                            wire:click="clearUserSignature"
                            wire:confirm="Apakah Anda yakin ingin mereset/menghapus tanda tangan Anda?"
                        >
                            Reset
                        </x-filament::button>
                    </div>
                @else
                    <div class="text-xs text-gray-400 dark:text-gray-500 italic">
                        Belum ada tanda tangan tersimpan.
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>

    {{-- 2. Bagian Khusus Superadmin: Pengaturan Kebijakan Sistem --}}
    @if ($this->isSuperadmin())
        <x-filament::section
            icon="heroicon-o-cog-6-tooth"
            icon-color="warning"
            collapsible
        >
            <x-slot name="heading">
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
                    <span style="margin-right: 0.25rem;">Kebijakan & Mode Tanda Tangan Sistem</span>
                    <x-filament::badge color="warning" size="sm" icon="heroicon-m-shield-exclamation">
                        Khusus Superadmin
                    </x-filament::badge>
                </div>
            </x-slot>

            <x-slot name="description">
                Atur metode tanda tangan yang diizinkan untuk seluruh user (Goresan, Upload Gambar, atau QR Code) serta bagaimana tanda tangan ditampilkan pada cetakan dokumen PDF.
            </x-slot>

            <div class="sig-two-col">
                {{-- Kolom Kiri: Metode yang Diizinkan --}}
                <x-filament::fieldset label="Metode Input yang Diizinkan bagi User">
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <label class="sig-option-item">
                            <x-filament::input.checkbox wire:model="allowDraw" style="margin-top: 0.125rem;" />
                            <div>
                                <div class="text-sm font-semibold text-gray-950 dark:text-white">
                                    Goresan Langsung (Draw Pad / Canvas)
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Mengizinkan user menggambar goresan tanda tangan langsung pada kanvas layar (Default).
                                </div>
                            </div>
                        </label>

                        <label class="sig-option-item">
                            <x-filament::input.checkbox wire:model="allowUpload" style="margin-top: 0.125rem;" />
                            <div>
                                <div class="text-sm font-semibold text-gray-950 dark:text-white">
                                    Upload Gambar Tanda Tangan (PNG / JPG)
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Mengizinkan user mengunggah file gambar tanda tangan transparan (Default).
                                </div>
                            </div>
                        </label>

                        <label class="sig-option-item">
                            <x-filament::input.checkbox wire:model="allowQrcode" style="margin-top: 0.125rem;" />
                            <div>
                                <div class="text-sm font-semibold text-gray-950 dark:text-white">
                                    Aktifkan Tanda Tangan QR Code Terverifikasi
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Menyediakan QR Code unik yang dapat di-scan publik untuk memverifikasi keaslian dokumen & penandatangan.
                                </div>
                            </div>
                        </label>
                    </div>
                </x-filament::fieldset>

                {{-- Kolom Kanan: Mode Tampilan PDF & Kewajiban TTD --}}
                <x-filament::fieldset label="Pengaturan Cetakan PDF & Validasi Persetujuan">
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <label class="text-sm font-medium text-gray-950 dark:text-white" style="display: block; margin-bottom: 0.375rem;">
                                Mode Tampilan Tanda Tangan pada Dokumen PDF
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model="documentDisplayMode">
                                    <option value="user_choice">Sesuai Pilihan User (Default: Goresan / Upload, atau QR Code bila diaktifkan)</option>
                                    <option value="draw_or_upload">Tampilkan Tanda Tangan Visual (Goresan / Upload Gambar) Saja</option>
                                    <option value="qrcode_only">Tampilkan QR Code Verifikasi Saja pada Dokumen</option>
                                    <option value="visual_and_qrcode">Tampilkan Keduanya (Tanda Tangan Visual + QR Code Verifikasi)</option>
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            <p class="text-xs text-gray-500 dark:text-gray-400" style="margin-top: 0.375rem;">
                                Menentukan format tanda tangan yang tercetak pada PDF Memo, SPPD, Biaya SPPD, dan Reimbursement.
                            </p>
                        </div>

                        <label class="sig-option-item">
                            <x-filament::input.checkbox wire:model="requireSignatureBeforeSign" style="margin-top: 0.125rem;" />
                            <div>
                                <div class="text-sm font-semibold text-gray-950 dark:text-white">
                                    Wajibkan User Mengatur Tanda Tangan Sebelum Menandatangani Dokumen
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Jika aktif, user yang belum mengatur tanda tangan akan diingatkan dan harus mengatur tanda tangan terlebih dahulu sebelum bisa menyetujui atau mengajukan dokumen.
                                </div>
                            </div>
                        </label>

                        <div style="padding-top: 0.25rem;">
                            <x-filament::button
                                wire:click="saveSystemSettings"
                                icon="heroicon-m-check"
                                color="primary"
                                size="md"
                            >
                                Simpan Kebijakan Sistem
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::fieldset>
            </div>
        </x-filament::section>
    @endif

    {{-- 3. Bagian Pengaturan Tanda Tangan Personal User --}}
    <x-filament::section
        icon="heroicon-o-pencil-square"
        icon-color="primary"
    >
        <x-slot name="heading">
            Tanda Tangan Saya
        </x-slot>

        <x-slot name="description">
            Pilih metode tanda tangan Anda di bawah ini. Pengaturan ini cukup dilakukan 1 kali saja dan akan otomatis digunakan setiap kali Anda menandatangani dokumen.
        </x-slot>

        {{-- Pilihan Metode Tanda Tangan --}}
        <div style="margin-bottom: 1.5rem;">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" style="margin-bottom: 0.625rem;">
                1. Pilih Metode Tanda Tangan
            </div>

            <div class="sig-method-grid">
                @foreach ($allowedMethods as $methodKey => $methodLabel)
                    <label class="sig-method-card {{ $signatureType === $methodKey ? 'is-active' : '' }}">
                        <input
                            type="radio"
                            wire:model.live="signatureType"
                            value="{{ $methodKey }}"
                            style="margin-top: 0.25rem; accent-color: #d97706;"
                        >
                        <div style="flex: 1;">
                            <div class="flex items-center justify-between gap-2" style="margin-bottom: 0.25rem;">
                                <span class="text-sm font-bold text-gray-950 dark:text-white">
                                    {{ $methodLabel }}
                                </span>
                                @if ($methodKey === 'draw')
                                    <x-heroicon-o-pencil class="text-primary-600 dark:text-primary-400 shrink-0" style="width: 1.125rem; height: 1.125rem;" />
                                @elseif ($methodKey === 'upload')
                                    <x-heroicon-o-arrow-up-tray class="text-primary-600 dark:text-primary-400 shrink-0" style="width: 1.125rem; height: 1.125rem;" />
                                @elseif ($methodKey === 'qrcode')
                                    <x-heroicon-o-qr-code class="text-primary-600 dark:text-primary-400 shrink-0" style="width: 1.125rem; height: 1.125rem;" />
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                @if ($methodKey === 'draw')
                                    Buat goresan tanda tangan langsung menggunakan mouse atau layar sentuh.
                                @elseif ($methodKey === 'upload')
                                    Unggah file gambar tanda tangan Anda dalam format PNG, JPG, atau WEBP.
                                @elseif ($methodKey === 'qrcode')
                                    Gunakan QR Code kriptografis yang dapat di-scan untuk verifikasi keaslian.
                                @endif
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Panel 1: Goresan Tanda Tangan (Draw Pad) --}}
        @if ($signatureType === 'draw')
            <x-filament::fieldset label="2. Buat Goresan Tanda Tangan pada Kanvas">
                <div
                    x-data="{
                        drawing: false,
                        hasDrawn: false,
                        strokeColor: '#0f172a',
                        canvas: null,
                        ctx: null,
                        init() {
                            this.$nextTick(() => {
                                this.canvas = this.$refs.sigCanvas;
                                if (!this.canvas) return;
                                this.ctx = this.canvas.getContext('2d');
                                this.ctx.lineWidth = 2.6;
                                this.ctx.lineCap = 'round';
                                this.ctx.lineJoin = 'round';
                                this.ctx.strokeStyle = this.strokeColor;

                                const existing = @js($drawnSignatureData);
                                if (existing) {
                                    const img = new Image();
                                    img.onload = () => {
                                        this.ctx.drawImage(img, 0, 0, this.canvas.width, this.canvas.height);
                                        this.hasDrawn = true;
                                    };
                                    img.src = existing;
                                }
                            });
                        },
                        getPos(e) {
                            const rect = this.canvas.getBoundingClientRect();
                            const scaleX = this.canvas.width / rect.width;
                            const scaleY = this.canvas.height / rect.height;
                            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                            return {
                                x: (clientX - rect.left) * scaleX,
                                y: (clientY - rect.top) * scaleY
                            };
                        },
                        startDraw(e) {
                            e.preventDefault();
                            this.drawing = true;
                            this.hasDrawn = true;
                            this.ctx.strokeStyle = this.strokeColor;
                            const pos = this.getPos(e);
                            this.ctx.beginPath();
                            this.ctx.moveTo(pos.x, pos.y);
                        },
                        moveDraw(e) {
                            if (!this.drawing) return;
                            e.preventDefault();
                            const pos = this.getPos(e);
                            this.ctx.lineTo(pos.x, pos.y);
                            this.ctx.stroke();
                        },
                        endDraw(e) {
                            if (!this.drawing) return;
                            this.drawing = false;
                            this.ctx.closePath();
                            this.syncData();
                        },
                        clearPad() {
                            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                            this.hasDrawn = false;
                            $wire.set('drawnSignatureData', null);
                        },
                        syncData() {
                            if (!this.hasDrawn) return null;
                            const dataUrl = this.canvas.toDataURL('image/png');
                            $wire.set('drawnSignatureData', dataUrl, false);
                            return dataUrl;
                        },
                        savePad() {
                            const dataUrl = this.syncData();
                            $wire.saveUserSignature(dataUrl);
                        }
                    }"
                    style="display: flex; flex-direction: column; gap: 1rem;"
                >
                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem;">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Gunakan mouse, touchpad, atau layar sentuh untuk menggambar tanda tangan di area putih di bawah ini.
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.625rem;">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Warna Tinta:</span>
                            <button
                                type="button"
                                @click="strokeColor = '#0f172a'"
                                :class="strokeColor === '#0f172a' ? 'sig-ink-btn active' : 'sig-ink-btn'"
                                style="background-color: #0f172a;"
                                title="Hitam Formal"
                            ></button>
                            <button
                                type="button"
                                @click="strokeColor = '#1d4ed8'"
                                :class="strokeColor === '#1d4ed8' ? 'sig-ink-btn active' : 'sig-ink-btn'"
                                style="background-color: #1d4ed8;"
                                title="Biru Tinta"
                            ></button>
                        </div>
                    </div>

                    <div class="sig-canvas-box">
                        <canvas
                            x-ref="sigCanvas"
                            width="680"
                            height="210"
                            style="width: 100%; height: 200px; display: block; cursor: crosshair; touch-action: none; background-color: #ffffff;"
                            @mousedown="startDraw($event)"
                            @mousemove="moveDraw($event)"
                            @mouseup="endDraw($event)"
                            @mouseleave="endDraw($event)"
                            @touchstart="startDraw($event)"
                            @touchmove="moveDraw($event)"
                            @touchend="endDraw($event)"
                        ></canvas>
                        <div style="position: absolute; left: 2.5rem; right: 2.5rem; bottom: 2.25rem; border-bottom: 1px dashed #cbd5e1; pointer-events: none;"></div>
                        <!-- <div style="position: absolute; left: 2.5rem; bottom: 0.875rem; font-size: 11px; color: #94a3b8; pointer-events: none;">
                            Garis Bantu Tanda Tangan ({{ $employee?->name ?? $currentUser?->name }})
                        </div> -->
                    </div>

                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; padding-top: 0.25rem;">
                        <x-filament::button
                            type="button"
                            @click="savePad()"
                            icon="heroicon-m-check"
                            color="primary"
                            size="md"
                        >
                            Simpan Tanda Tangan Goresan
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            @click="clearPad()"
                            icon="heroicon-m-arrow-path"
                            color="gray"
                            size="md"
                        >
                            Bersihkan Kanvas
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::fieldset>
        @endif

        {{-- Panel 2: Upload Gambar Tanda Tangan --}}
        @if ($signatureType === 'upload')
            <x-filament::fieldset label="2. Unggah File Gambar Tanda Tangan">
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div class="sig-two-col" style="align-items: center;">
                        <div>
                            <label class="text-sm font-medium text-gray-950 dark:text-white" style="display: block; margin-bottom: 0.5rem;">
                                Pilih File Gambar Tanda Tangan (PNG transparan disarankan, maks. 2 MB)
                            </label>

                            <x-filament::input.wrapper>
                                <input
                                    type="file"
                                    wire:model="uploadedSignatureFile"
                                    accept="image/png,image/jpeg,image/jpg,image/webp"
                                    class="fi-input block w-full text-sm text-gray-950 dark:text-white"
                                    style="padding: 0.5rem 0.75rem;"
                                >
                            </x-filament::input.wrapper>

                            @error('uploadedSignatureFile')
                                <p class="text-xs text-danger-600 dark:text-danger-400" style="margin-top: 0.375rem;">
                                    {{ $message }}
                                </p>
                            @enderror

                            <p class="text-xs text-gray-500 dark:text-gray-400" style="margin-top: 0.5rem;">
                                Format yang didukung: <strong>PNG, JPG, JPEG, WEBP</strong>. Gunakan latar belakang putih atau transparan agar tampak rapi pada dokumen PDF.
                            </p>
                        </div>

                        <div>
                            @if ($uploadedSignatureFile)
                                <div class="sig-preview-box" style="width: 100%; padding: 1rem;">
                                    <div class="text-xs font-semibold text-gray-500" style="margin-bottom: 0.5rem;">Preview File Baru:</div>
                                    <img src="{{ $uploadedSignatureFile->temporaryUrl() }}" alt="Preview Upload" style="height: 5.5rem; object-fit: contain;">
                                </div>
                            @elseif ($savedImageUri && $currentUser?->signature_type === 'upload')
                                <div class="sig-preview-box" style="width: 100%; padding: 1rem;">
                                    <div class="text-xs font-semibold text-gray-500" style="margin-bottom: 0.5rem;">Gambar Tanda Tangan Tersimpan:</div>
                                    <img src="{{ $savedImageUri }}" alt="Tanda Tangan Tersimpan" style="height: 5.5rem; object-fit: contain;">
                                </div>
                            @else
                                <div class="sig-preview-box" style="width: 100%; padding: 1.25rem; color: #94a3b8; font-size: 0.75rem;">
                                    Belum ada gambar dipilih
                                </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <x-filament::button
                            type="button"
                            wire:click="saveUserSignature"
                            icon="heroicon-m-check"
                            color="primary"
                            size="md"
                        >
                            Simpan Gambar Tanda Tangan
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::fieldset>
        @endif

        {{-- Panel 3: Tanda Tangan QR Code Terverifikasi --}}
        @if ($signatureType === 'qrcode')
            <x-filament::fieldset label="2. Konfirmasi Tanda Tangan QR Code Terverifikasi">
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem;">
                        <div class="sig-preview-box" style="padding: 0.875rem;">
                            <img src="{{ $this->sampleQrDataUri }}" alt="Contoh QR Code" style="height: 6.5rem; width: 6.5rem; object-fit: contain;">
                        </div>

                        <div style="flex: 1; min-width: 240px;">
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.625rem; margin-bottom: 0.375rem;">
                                <span class="text-sm font-bold text-gray-950 dark:text-white">
                                    Tanda Tangan Elektronik Berbasis QR Code
                                </span>
                                <x-filament::badge color="info" size="sm" icon="heroicon-m-qr-code">
                                    Terverifikasi Publik
                                </x-filament::badge>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed" style="margin-bottom: 0.75rem;">
                                Setiap dokumen yang Anda setujui atau ajukan akan menghasilkan <strong>QR Code unik terenkripsi</strong> yang terhubung langsung ke halaman verifikasi resmi MOTIVA (menampilkan Nama Penandatangan, Jabatan, Nomor Dokumen, serta Waktu Pengesahan).
                            </p>

                            <div class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Penandatangan: {{ $employee?->name ?? $currentUser?->name }}
                                @if ($employee?->position?->name)
                                    &bull; {{ $employee->position->name }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <div>
                        <x-filament::button
                            type="button"
                            wire:click="saveUserSignature"
                            icon="heroicon-m-check"
                            color="primary"
                            size="md"
                        >
                            Aktifkan & Simpan Tanda Tangan QR Code
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::fieldset>
        @endif
    </x-filament::section>
</x-filament-panels::page>
