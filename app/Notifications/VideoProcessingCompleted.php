<?php

namespace App\Notifications;

use App\Models\MediaFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VideoProcessingCompleted extends Notification
{
    use Queueable;

    private MediaFile $mediaFile;

    /**
     * Create a new notification instance.
     */
    public function __construct(MediaFile $mediaFile)
    {
        $this->mediaFile = $mediaFile;
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
        $url = route('media.index');

        return (new MailMessage)
            ->subject('Video Processing Completed - ' . $this->mediaFile->name)
            ->greeting('Hello!')
            ->line("The video file '{$this->mediaFile->name}' has been successfully processed.")
            ->line("Duration: " . gmdate('H:i:s', $this->mediaFile->duration))
            ->line("File size: " . $this->formatBytes($this->mediaFile->size))
            ->action('View Media Files', $url)
            ->line('The processed video is now ready for use in playlists.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'media_file_id' => $this->mediaFile->id,
            'media_file_name' => $this->mediaFile->name,
            'duration' => $this->mediaFile->duration,
            'size' => $this->mediaFile->size,
            'type' => 'video_processing_completed',
            'processed_at' => now(),
        ];
    }

    /**
     * Format bytes into human readable format
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
