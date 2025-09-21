<?php

namespace App\Mail;

use App\Models\AlertRule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlaybackErrorAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public AlertRule $alertRule,
        public array $alertData
    ) {
        $this->onQueue('emails');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $tenant = $this->alertRule->tenant;
        $errorCount = $this->alertData['total_errors'];
        $timePeriod = $this->alertData['time_period'];

        return new Envelope(
            subject: "[{$tenant->name}] {$errorCount} erros de reprodução detectados em {$timePeriod}"
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $tenant = $this->alertRule->tenant;

        return new Content(
            view: 'emails.playback-error-alert',
            with: [
                'alertRule' => $this->alertRule,
                'alertData' => $this->alertData,
                'tenant' => $tenant,
                'totalErrors' => $this->alertData['total_errors'],
                'thresholdErrors' => $this->alertData['threshold_errors'],
                'timePeriod' => $this->alertData['time_period'],
                'errorsByPlayer' => $this->alertData['errors_by_player'],
                'mostAffectedPlayer' => $this->alertData['most_affected_player'],
                'dashboardUrl' => $this->getDashboardUrl(),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get dashboard URL for the tenant
     */
    private function getDashboardUrl(): string
    {
        $tenant = $this->alertRule->tenant;
        return route('dashboard', ['tenant' => $tenant->id]);
    }
}