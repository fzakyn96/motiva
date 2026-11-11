<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SignatureSetting extends Model
{
    protected $fillable = [
        'allow_draw',
        'allow_upload',
        'allow_qrcode',
        'document_display_mode',
        'require_signature_before_sign',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allow_draw' => 'boolean',
            'allow_upload' => 'boolean',
            'allow_qrcode' => 'boolean',
            'require_signature_before_sign' => 'boolean',
        ];
    }

    public static function current(): self
    {
        if (! Schema::hasTable('signature_settings')) {
            return new self([
                'allow_draw' => true,
                'allow_upload' => true,
                'allow_qrcode' => false,
                'document_display_mode' => 'user_choice',
                'require_signature_before_sign' => true,
            ]);
        }

        return static::query()->first() ?? static::query()->create([
            'allow_draw' => true,
            'allow_upload' => true,
            'allow_qrcode' => false,
            'document_display_mode' => 'user_choice',
            'require_signature_before_sign' => true,
        ]);
    }

    public function getAllowedMethodsForUser(): array
    {
        $methods = [];

        if ($this->allow_draw) {
            $methods['draw'] = 'Goresan Tanda Tangan (Draw Pad)';
        }

        if ($this->allow_upload) {
            $methods['upload'] = 'Upload Gambar Tanda Tangan (PNG/JPG)';
        }

        if ($this->allow_qrcode || in_array($this->document_display_mode, ['qrcode_only', 'visual_and_qrcode'], true)) {
            $methods['qrcode'] = 'Tanda Tangan Berbasis QR Code Terverifikasi';
        }

        if (empty($methods)) {
            $methods['draw'] = 'Goresan Tanda Tangan (Draw Pad)';
        }

        return $methods;
    }
}

