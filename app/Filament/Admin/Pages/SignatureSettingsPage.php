<?php

namespace App\Filament\Admin\Pages;

use App\Models\SignatureSetting;
use App\Models\User;
use App\Services\Signature\QrCodeGenerator;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class SignatureSettingsPage extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = 'Tanda Tangan';

    protected static ?string $title = 'Tanda Tangan';

    protected static ?string $slug = 'signature-settings';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.signature-settings';

    // User signature state
    public string $signatureType = 'draw';

    public ?string $drawnSignatureData = null;

    /** @var TemporaryUploadedFile|null */
    public $uploadedSignatureFile = null;

    // Superadmin system settings state
    public bool $allowDraw = true;

    public bool $allowUpload = true;

    public bool $allowQrcode = false;

    public string $documentDisplayMode = 'user_choice';

    public bool $requireSignatureBeforeSign = true;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        /** @var User|null $user */
        $user = auth()->user();
        $setting = SignatureSetting::current();

        $this->allowDraw = (bool) $setting->allow_draw;
        $this->allowUpload = (bool) $setting->allow_upload;
        $this->allowQrcode = (bool) $setting->allow_qrcode;
        $this->documentDisplayMode = $setting->document_display_mode ?: 'user_choice';
        $this->requireSignatureBeforeSign = (bool) $setting->require_signature_before_sign;

        $allowed = array_keys($setting->getAllowedMethodsForUser());
        $userType = $user?->signature_type;

        if ($userType && in_array($userType, $allowed, true)) {
            $this->signatureType = $userType;
        } else {
            $this->signatureType = $allowed[0] ?? 'draw';
        }

        if ($user?->signature_type === 'draw' && ! empty($user->signature_data)) {
            $this->drawnSignatureData = $user->signature_data;
        }
    }

    public function isSuperadmin(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null && $user->hasRole('Superadmin');
    }

    public function getAllowedMethodsProperty(): array
    {
        return SignatureSetting::current()->getAllowedMethodsForUser();
    }

    public function getSampleQrDataUriProperty(): string
    {
        /** @var User|null $user */
        $user = auth()->user();
        $sampleUrl = url('/verify-signature/sample-' . ($user?->id ?? 'user'));

        return QrCodeGenerator::toPngDataUri($sampleUrl, 4, 2);
    }

    public function saveSystemSettings(): void
    {
        if (! $this->isSuperadmin()) {
            abort(403, 'Hanya Superadmin yang dapat mengubah kebijakan tanda tangan sistem.');
        }

        if ($this->documentDisplayMode === 'qrcode_only') {
            $this->allowQrcode = true;
        }

        if (! $this->allowDraw && ! $this->allowUpload && ! $this->allowQrcode) {
            $this->allowDraw = true;
        }

        $setting = SignatureSetting::current();
        $setting->update([
            'allow_draw' => $this->allowDraw,
            'allow_upload' => $this->allowUpload,
            'allow_qrcode' => $this->allowQrcode,
            'document_display_mode' => $this->documentDisplayMode,
            'require_signature_before_sign' => $this->requireSignatureBeforeSign,
            'updated_by' => auth()->id(),
        ]);

        $allowed = array_keys($setting->getAllowedMethodsForUser());
        if (! in_array($this->signatureType, $allowed, true)) {
            $this->signatureType = $allowed[0] ?? 'draw';
        }

        Notification::make()
            ->title('Kebijakan Tanda Tangan Sistem Disimpan')
            ->body('Pengaturan metode tanda tangan dokumen berhasil diperbarui oleh Superadmin.')
            ->success()
            ->send();
    }

    public function saveUserSignature(?string $canvasData = null): void
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $setting = SignatureSetting::current();
        $allowed = array_keys($setting->getAllowedMethodsForUser());

        if (! in_array($this->signatureType, $allowed, true)) {
            Notification::make()
                ->title('Metode Tidak Diizinkan')
                ->body('Metode tanda tangan yang dipilih tidak diaktifkan oleh Superadmin.')
                ->danger()
                ->send();

            return;
        }

        if ($this->signatureType === 'draw') {
            $dataUri = $canvasData ?: $this->drawnSignatureData;

            if (empty($dataUri) || ! str_starts_with($dataUri, 'data:image/')) {
                Notification::make()
                    ->title('Goresan Tanda Tangan Kosong')
                    ->body('Silakan buat goresan tanda tangan pada area kanvas terlebih dahulu sebelum menyimpan.')
                    ->warning()
                    ->send();

                return;
            }

            $user->update([
                'signature_type' => 'draw',
                'signature_data' => $dataUri,
                'signature_updated_at' => now(),
            ]);

            $this->drawnSignatureData = $dataUri;
        } elseif ($this->signatureType === 'upload') {
            if ($this->uploadedSignatureFile) {
                $this->validate([
                    'uploadedSignatureFile' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                ]);

                $path = $this->uploadedSignatureFile->store('signatures', 'public');
                $realPath = Storage::disk('public')->path($path);
                $mime = mime_content_type($realPath) ?: 'image/png';
                $base64 = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($realPath));

                $user->update([
                    'signature_type' => 'upload',
                    'signature_path' => $path,
                    'signature_data' => $base64,
                    'signature_updated_at' => now(),
                ]);

                $this->uploadedSignatureFile = null;
            } elseif (! empty($user->signature_data) || ! empty($user->signature_path)) {
                $user->update([
                    'signature_type' => 'upload',
                    'signature_updated_at' => now(),
                ]);
            } else {
                Notification::make()
                    ->title('File Gambar Belum Dipilih')
                    ->body('Silakan pilih file gambar tanda tangan (PNG/JPG) terlebih dahulu.')
                    ->warning()
                    ->send();

                return;
            }
        } elseif ($this->signatureType === 'qrcode') {
            $user->update([
                'signature_type' => 'qrcode',
                'signature_updated_at' => now(),
            ]);
        }

        Notification::make()
            ->title('Tanda Tangan Berhasil Disimpan')
            ->body('Pengaturan cukup dilakukan 1 kali ini saja. Tanda tangan Anda kini akan otomatis dibubuhkan saat Anda menyetujui atau mengajukan dokumen.')
            ->success()
            ->send();
    }

    public function clearUserSignature(): void
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (! $user) {
            return;
        }

        if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
            Storage::disk('public')->delete($user->signature_path);
        }

        $user->update([
            'signature_type' => null,
            'signature_data' => null,
            'signature_path' => null,
            'signature_updated_at' => null,
        ]);

        $this->drawnSignatureData = null;
        $this->uploadedSignatureFile = null;

        Notification::make()
            ->title('Tanda Tangan Direset')
            ->body('Data tanda tangan Anda telah dihapus. Harap atur kembali sebelum menandatangani dokumen.')
            ->info()
            ->send();
    }
}

