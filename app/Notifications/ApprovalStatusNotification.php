<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $event,
        public string $documentTypeLabel,
        public string $documentTitle,
        public string $requesterName,
        public ?string $notes = null,
        public ?string $actionUrl = null,
        public array $details = [],
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = $notifiable->name ?? 'Rekan Kerja';
        $titleSuffix = filled($this->documentTitle) ? " - {$this->documentTitle}" : '';
        $docPhrase = filled($this->documentTitle)
            ? "{$this->documentTypeLabel} **{$this->documentTitle}**"
            : $this->documentTypeLabel;

        if ($this->event === 'approved') {
            $mail = (new MailMessage)
                ->subject("[MOTIVA] Pemberitahuan Persetujuan {$this->documentTypeLabel}{$titleSuffix}")
                ->greeting("Yth. {$recipientName},")
                ->line("Dengan hormat, kami informasikan bahwa pengajuan {$docPhrase} telah **DISETUJUI**.");

            $this->appendDetails($mail);

            if (filled($this->notes)) {
                $mail->line("**Catatan Penyetuju:** {$this->notes}");
            }

            if (filled($this->actionUrl)) {
                $mail->action('Lihat Dokumen', $this->actionUrl);
            }

            return $mail
                ->line('Demikian pemberitahuan ini kami sampaikan. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.')
                ->salutation("Hormat kami,\nMOTIVA");
        }

        if ($this->event === 'assigned') {
            $agenda = $this->details['Agenda'] ?? $this->documentTitle;
            $subjectSuffix = filled($agenda) ? " - {$agenda}" : '';

            $mail = (new MailMessage)
                ->subject("[MOTIVA] Pemberitahuan Penugasan{$subjectSuffix}")
                ->greeting("Yth. {$recipientName},")
                ->line('Dengan hormat, kami informasikan bahwa Anda ditugaskan untuk melaksanakan perjalanan dinas sesuai rincian berikut:');

            $this->appendDetails($mail);

            if (filled($this->actionUrl)) {
                $mail->action('Lihat Detail Dokumen', $this->actionUrl);
            }

            return $mail
                ->line('Mohon untuk mempersiapkan pelaksanaan tugas sesuai jadwal dan ketentuan yang berlaku.')
                ->salutation("Hormat kami,\nMOTIVA");
        }

        if ($this->event === 'rejected') {
            $mail = (new MailMessage)
                ->error()
                ->subject("[MOTIVA] Pemberitahuan Penolakan {$this->documentTypeLabel}{$titleSuffix}")
                ->greeting("Yth. {$recipientName},")
                ->line("Dengan hormat, kami informasikan bahwa pengajuan {$docPhrase} **DITOLAK**.");

            $this->appendDetails($mail);

            if (filled($this->notes)) {
                $mail->line("**Alasan Penolakan:** {$this->notes}");
            }

            if (filled($this->actionUrl)) {
                $mail->action('Tinjau Pengajuan', $this->actionUrl);
            }

            return $mail
                ->line('Mohon untuk melakukan peninjauan dan perbaikan sesuai catatan di atas sebelum mengajukan kembali.')
                ->salutation("Hormat kami,\nMOTIVA");
        }

        $mail = (new MailMessage)
            ->subject("[MOTIVA] Permohonan Persetujuan {$this->documentTypeLabel}{$titleSuffix}")
            ->greeting("Yth. {$recipientName},")
            ->line("Dengan hormat, bersama ini kami sampaikan terdapat pengajuan {$this->documentTypeLabel} yang memerlukan peninjauan dan persetujuan Anda.")
            ->line("**Pemohon / Pengaju:** {$this->requesterName}");

        $this->appendDetails($mail);

        if (filled($this->actionUrl)) {
            $mail->action('Tinjau & Proses Persetujuan', $this->actionUrl);
        }

        return $mail
            ->line('Atas perhatian dan persetujuannya, kami ucapkan terima kasih.')
            ->salutation("Hormat kami,\nMOTIVA");
    }

    protected function appendDetails(MailMessage $mail): void
    {
        $mail->line("**Jenis Dokumen:** {$this->documentTypeLabel}");

        if (filled($this->documentTitle)) {
            $mail->line("**Nomor Dokumen:** {$this->documentTitle}");
        }

        foreach ($this->details as $label => $value) {
            if (filled($value)) {
                $mail->line("**{$label}:** {$value}");
            }
        }
    }
}
