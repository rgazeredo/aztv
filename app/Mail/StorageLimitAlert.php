<?php

namespace App\Mail;

use App\Models\AlertRule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StorageLimitAlert extends Mailable implements ShouldQueue
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
        $usagePercentage = $this->alertData['usage_percentage'];

        return new Envelope(
            subject: "[{$tenant->name}] Limite de armazenamento atingiu {$usagePercentage}%"
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $tenant = $this->alertRule->tenant;

        return new Content(
            view: 'emails.storage-limit-alert',
            with: [
                'alertRule' => $this->alertRule,
                'alertData' => $this->alertData,
                'tenant' => $tenant,
                'currentUsageGb' => $this->alertData['current_usage_gb'],
                'limitGb' => $this->alertData['limit_gb'],
                'usagePercentage' => $this->alertData['usage_percentage'],
                'thresholdPercentage' => $this->alertData['threshold_percentage'],
                'remainingGb' => $this->alertData['remaining_gb'],
                'largestFiles' => $this->alertData['largest_files'],
                'planName' => $this->alertData['plan_name'],
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