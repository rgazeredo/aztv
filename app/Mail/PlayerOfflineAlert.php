<?php

namespace App\Mail;

use App\Models\Player;
use App\Models\PlayerAlert;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlayerOfflineAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Player $player,
        public PlayerAlert $alert,
        public Tenant $tenant
    ) {
        $this->onQueue('emails');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->tenant->name}] Player '{$this->player->name}' está offline",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.player-offline-alert',
            with: [
                'player' => $this->player,
                'alert' => $this->alert,
                'tenant' => $this->tenant,
                'dashboardUrl' => $this->getDashboardUrl(),
                'playerUrl' => $this->getPlayerUrl(),
                'offlineDuration' => $this->getOfflineDuration(),
                'lastSeenFormatted' => $this->getLastSeenFormatted(),
                'alertSeverity' => $this->getAlertSeverity(),
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
        return route('dashboard', ['tenant' => $this->tenant->id]);
    }

    /**
     * Get player detail URL
     */
    private function getPlayerUrl(): string
    {
        return route('players.show', [
            'tenant' => $this->tenant->id,
            'player' => $this->player->id
        ]);
    }

    /**
     * Get offline duration in human readable format
     */
    private function getOfflineDuration(): string
    {
        $lastSeen = $this->player->last_seen_at ?? $this->player->updated_at;
        $diffInMinutes = now()->diffInMinutes($lastSeen);

        if ($diffInMinutes < 60) {
            return "{$diffInMinutes} minutos";
        }

        $hours = floor($diffInMinutes / 60);
        $minutes = $diffInMinutes % 60;

        if ($hours < 24) {
            return $minutes > 0 ? "{$hours}h {$minutes}min" : "{$hours}h";
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        if ($days < 7) {
            return $remainingHours > 0 ? "{$days}d {$remainingHours}h" : "{$days}d";
        }

        $weeks = floor($days / 7);
        $remainingDays = $days % 7;

        return $remainingDays > 0 ? "{$weeks}sem {$remainingDays}d" : "{$weeks}sem";
    }

    /**
     * Get last seen formatted
     */
    private function getLastSeenFormatted(): string
    {
        $lastSeen = $this->player->last_seen_at ?? $this->player->updated_at;

        if (!$lastSeen) {
            return 'Nunca conectado';
        }

        return $lastSeen->format('d/m/Y \à\s H:i');
    }

    /**
     * Get alert severity based on offline duration
     */
    private function getAlertSeverity(): array
    {
        $lastSeen = $this->player->last_seen_at ?? $this->player->updated_at;
        $diffInMinutes = now()->diffInMinutes($lastSeen);

        if ($diffInMinutes < 30) {
            return [
                'level' => 'warning',
                'color' => '#f59e0b',
                'icon' => '⚠️',
                'label' => 'Atenção'
            ];
        }

        if ($diffInMinutes < 120) {
            return [
                'level' => 'error',
                'color' => '#ef4444',
                'icon' => '🚨',
                'label' => 'Crítico'
            ];
        }

        return [
            'level' => 'critical',
            'color' => '#dc2626',
            'icon' => '💀',
            'label' => 'Emergência'
        ];
    }
}