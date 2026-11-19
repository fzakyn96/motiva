<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSignature extends Model
{
    protected $fillable = [
        'token',
        'document_type',
        'document_id',
        'document_number',
        'user_id',
        'employee_id',
        'signer_name',
        'signer_position',
        'signer_nip',
        'role_type',
        'signature_type',
        'signature_snapshot',
        'signed_at',
        'notes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    public function document()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getVerificationUrlAttribute(): string
    {
        return route('signatures.verify', ['token' => $this->token]);
    }

    public function getDocumentTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            Memo::class => 'Memo Internal Penugasan',
            Sppd::class => 'Surat Perintah Perjalanan Dinas (SPPD)',
            Reimbursement::class => 'Formulir Penggantian Biaya (Reimbursement)',
            SppdCost::class => 'Formulir Perhitungan Biaya SPPD',
            TransportAccommodationRecap::class => 'Rekapitulasi Biaya Tiket & Akomodasi',
            OfficialTravelRecap::class => 'Rekapitulasi Biaya Perjalanan Dinas',
            default => class_basename($this->document_type),
        };
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role_type) {
            'division_head' => 'Pejabat Penyetuju (Division Head)',
            'manager' => 'Pejabat Pemeriksa / Penyetuju (Manajer)',
            'supervisor' => 'Pemeriksa (Supervisor)',
            'requester' => in_array($this->document_type, [TransportAccommodationRecap::class, OfficialTravelRecap::class], true)
                ? 'Pejabat Penyusun (Business Division Head)'
                : 'Pegawai Pengaju',
            'authorizer' => 'Pejabat Pemberi Perintah',
            default => 'Penandatangan',
        };
    }
}

