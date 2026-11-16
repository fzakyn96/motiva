<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'password', 'signature_type', 'signature_data', 'signature_path', 'signature_updated_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'signature_updated_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function documentSignatures()
    {
        return $this->hasMany(DocumentSignature::class);
    }

    /**
     * Check whether this user has configured their digital signature.
     */
    public function hasConfiguredSignature(): bool
    {
        $setting = SignatureSetting::current();

        if ($this->signature_type === 'qrcode') {
            return $setting->allow_qrcode || in_array($setting->document_display_mode, ['qrcode_only', 'visual_and_qrcode'], true);
        }

        if (in_array($this->signature_type, ['draw', 'upload'], true)) {
            return ! empty($this->signature_data) || ! empty($this->signature_path);
        }

        return false;
    }

    /**
     * Resolve human-readable label for the user's configured signature method.
     */
    public function getSignatureMethodLabel(): string
    {
        return match ($this->signature_type) {
            'draw' => 'Gambar Langsung',
            'upload' => 'Unggah Gambar',
            'qrcode' => 'QR Code Verifikasi',
            default => 'Belum Diatur',
        };
    }

    /**
     * Resolve the base64 data URI for the user's visual signature (draw or upload).
     */
    public function getSignatureImageDataUri(): ?string
    {
        if (! empty($this->signature_data) && str_starts_with($this->signature_data, 'data:image/')) {
            return $this->signature_data;
        }

        if (! empty($this->signature_path)) {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($this->signature_path);
            if (file_exists($fullPath)) {
                $mime = mime_content_type($fullPath) ?: 'image/png';
                $encoded = base64_encode((string) file_get_contents($fullPath));
                return "data:{$mime};base64,{$encoded}";
            }
        }

        return null;
    }
}
