<?php

namespace App\Services\Signature;

use App\Filament\Admin\Pages\SignatureSettingsPage;
use App\Models\DocumentSignature;
use App\Models\Employee;
use App\Models\Memo;
use App\Models\Reimbursement;
use App\Models\SignatureSetting;
use App\Models\Sppd;
use App\Models\SppdCost;
use App\Models\User;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SignatureService
{
    /**
     * Check if the user has configured their signature, and optionally send a Filament notification reminder.
     */
    public function ensureUserCanSign(?User $user, bool $sendNotification = true): bool
    {
        if (! $user) {
            return false;
        }

        $setting = SignatureSetting::current();
        if (! $setting->require_signature_before_sign) {
            return true;
        }

        if ($user->hasConfiguredSignature()) {
            return true;
        }

        if ($sendNotification) {
            Notification::make()
                ->title('Tanda Tangan Belum Diatur')
                ->body('Anda belum mengatur tanda tangan. Silakan atur tanda tangan terlebih dahulu (cukup 1 kali) di menu Tanda Tangan sebelum menandatangani dokumen.')
                ->warning()
                ->persistent()
                ->actions([
                    NotificationAction::make('setup_signature')
                        ->label('Atur Tanda Tangan Sekarang')
                        ->button()
                        ->color('primary')
                        ->url(SignatureSettingsPage::getUrl()),
                ])
                ->send();
        }

        return false;
    }

    /**
     * Record a digital signature for a document and generate a verifiable token.
     */
    public function signDocument(
        Model $document,
        ?Employee $signerEmployee,
        string $roleType = 'approver',
        ?string $notes = null,
        ?User $actingUser = null
    ): ?DocumentSignature {
        if (! Schema::hasTable('document_signatures')) {
            return null;
        }

        $user = $actingUser ?? $signerEmployee?->user ?? auth()->user();
        $visualUri = $user?->getSignatureImageDataUri();
        $setting = SignatureSetting::current();

        $sigType = $user?->signature_type ?: 'draw';
        if ($setting->document_display_mode === 'qrcode_only') {
            $sigType = 'qrcode';
        }

        $existingQuery = DocumentSignature::where('document_type', get_class($document))
            ->where('document_id', $document->getKey())
            ->where('role_type', $roleType);

        if ($roleType === 'manager' && $signerEmployee && in_array(get_class($document), [\App\Models\TransportAccommodationRecap::class, \App\Models\OfficialTravelRecap::class], true)) {
            $existingQuery->where('employee_id', $signerEmployee->id);
            $existing = $existingQuery->first();
            $token = $existing?->token ?: bin2hex(random_bytes(20));

            return DocumentSignature::updateOrCreate(
                [
                    'document_type' => get_class($document),
                    'document_id' => $document->getKey(),
                    'role_type' => $roleType,
                    'employee_id' => $signerEmployee->id,
                ],
                [
                    'token' => $token,
                    'document_number' => $this->resolveDocumentNumber($document),
                    'user_id' => $user?->id,
                    'signer_name' => $signerEmployee->name ?? $user?->name ?? 'Pejabat Berwenang',
                    'signer_position' => $signerEmployee->position?->name ?? null,
                    'signer_nip' => $signerEmployee->identity_number ?? null,
                    'signature_type' => $sigType,
                    'signature_snapshot' => $visualUri ?? $existing?->signature_snapshot,
                    'signed_at' => now(),
                    'notes' => $notes,
                    'ip_address' => request()?->ip(),
                ]
            );
        }

        $existing = $existingQuery->first();

        $token = $existing?->token ?: bin2hex(random_bytes(20));

        return DocumentSignature::updateOrCreate(
            [
                'document_type' => get_class($document),
                'document_id' => $document->getKey(),
                'role_type' => $roleType,
            ],
            [
                'token' => $token,
                'document_number' => $this->resolveDocumentNumber($document),
                'user_id' => $user?->id,
                'employee_id' => $signerEmployee?->id,
                'signer_name' => $signerEmployee?->name ?? $user?->name ?? 'Pejabat Berwenang',
                'signer_position' => $signerEmployee?->position?->name ?? null,
                'signer_nip' => $signerEmployee?->identity_number ?? null,
                'signature_type' => $sigType,
                'signature_snapshot' => $visualUri ?? $existing?->signature_snapshot,
                'signed_at' => now(),
                'notes' => $notes,
                'ip_address' => request()?->ip(),
            ]
        );
    }

    /**
     * Resolve signature rendering payload for PDF views.
     */
    public function resolveRenderData(
        Model $document,
        ?Employee $signerEmployee,
        string $roleType = 'approver',
        bool $isSigned = true
    ): array {
        $empty = [
            'signed' => false,
            'show_visual' => false,
            'show_qr' => false,
            'visual_uri' => null,
            'qr_uri' => null,
            'verify_url' => null,
            'token' => null,
            'signed_at' => null,
        ];

        if (! $isSigned || ! Schema::hasTable('document_signatures')) {
            return $empty;
        }

        $setting = SignatureSetting::current();

        $docSigQuery = DocumentSignature::where('document_type', get_class($document))
            ->where('document_id', $document->getKey())
            ->where('role_type', $roleType);

        if ($signerEmployee && $roleType === 'manager' && in_array(get_class($document), [\App\Models\TransportAccommodationRecap::class, \App\Models\OfficialTravelRecap::class], true)) {
            $docSig = (clone $docSigQuery)->where('employee_id', $signerEmployee->id)->latest('signed_at')->first()
                ?? $docSigQuery->latest('signed_at')->first();
        } else {
            $docSig = $docSigQuery->latest('signed_at')->first();
        }

        if (! $docSig && in_array($roleType, ['manager', 'approver'], true)) {
            $fallbackQuery = DocumentSignature::where('document_type', get_class($document))
                ->where('document_id', $document->getKey())
                ->whereIn('role_type', ['manager', 'approver']);
            if ($signerEmployee && in_array(get_class($document), [\App\Models\TransportAccommodationRecap::class, \App\Models\OfficialTravelRecap::class], true)) {
                $docSig = (clone $fallbackQuery)->where('employee_id', $signerEmployee->id)->latest('signed_at')->first();
            } else {
                $docSig = $fallbackQuery->latest('signed_at')->first();
            }
        }

        $signerUser = $docSig?->user ?? $signerEmployee?->user;
        $visualUri = $docSig?->signature_snapshot ?: $signerUser?->getSignatureImageDataUri();

        $mode = $setting->document_display_mode; // user_choice, draw_or_upload, qrcode_only, visual_and_qrcode
        $userPref = $docSig?->signature_type ?: ($signerUser?->signature_type ?: 'draw');

        $wantsQr = ($mode === 'qrcode_only')
            || ($mode === 'visual_and_qrcode')
            || ($mode === 'user_choice' && $userPref === 'qrcode' && $setting->allow_qrcode);

        $wantsVisual = ($mode === 'draw_or_upload')
            || ($mode === 'visual_and_qrcode')
            || ($mode === 'user_choice' && in_array($userPref, ['draw', 'upload'], true));

        // Ensure a DocumentSignature record exists if we need a QR code or if user has configured signature
        if (! $docSig && ($wantsQr || ! empty($visualUri)) && $signerEmployee) {
            $docSig = $this->signDocument($document, $signerEmployee, $roleType, 'Tanda tangan elektronik dokumen', $signerUser);
        }

        $showVisual = $wantsVisual && ! empty($visualUri);
        $showQr = $wantsQr && $docSig !== null;

        // Fallback: if user_choice was draw/upload without visual URI, but QR is allowed and docSig exists
        if (! $showVisual && ! $showQr && $setting->allow_qrcode && $docSig) {
            $showQr = true;
        }

        $verifyUrl = $docSig ? route('signatures.verify', ['token' => $docSig->token]) : null;
        $qrUri = ($showQr && $verifyUrl) ? QrCodeGenerator::toPngDataUri($verifyUrl, 3, 2) : null;

        return [
            'signed' => $showVisual || $showQr,
            'show_visual' => $showVisual,
            'show_qr' => $showQr,
            'visual_uri' => $visualUri,
            'qr_uri' => $qrUri,
            'verify_url' => $verifyUrl,
            'token' => $docSig?->token,
            'signed_at' => $docSig?->signed_at,
        ];
    }

    public function resolveDocumentNumber(Model $document): ?string
    {
        if ($document instanceof Memo) {
            return $document->nomor_memo ?: ('MEMO-' . $document->id);
        }
        if ($document instanceof Sppd) {
            return $document->sppd_number ?: ('SPPD-' . $document->id);
        }
        if ($document instanceof Reimbursement) {
            return $document->reimbursement_number ?: ('RMB-' . $document->id);
        }
        if ($document instanceof SppdCost) {
            return $document->sppd?->sppd_number ?: ('COST-' . $document->id);
        }
        if ($document instanceof \App\Models\BusinessTrip) {
            return $document->memo?->nomor_memo ?: ('REKAP-' . $document->id);
        }
        if ($document instanceof \App\Models\TransportAccommodationRecap) {
            return $document->recap_number ?: ('REKAP-TA-' . $document->id);
        }
        if ($document instanceof \App\Models\OfficialTravelRecap) {
            return $document->recap_number ?: ('REKAP-PD-' . $document->id);
        }

        return (string) $document->getKey();
    }
}
