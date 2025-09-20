<?php

namespace App\Notifications;

use App\Models\MediaFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Exception;

class VideoProcessingFailed extends Notification
{
    use Queueable;

    private MediaFile $mediaFile;
    private Exception $exception;

    /**
     * Create a new notification instance.
     */
    public function __construct(MediaFile $mediaFile, Exception $exception)
    {
        $this->mediaFile = $mediaFile;
        $this->exception = $exception;
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
            ->error()
            ->subject('Video Processing Failed - ' . $this->mediaFile->name)
            ->greeting('Processing Error')
            ->line("The video file '{$this->mediaFile->name}' failed to process.")
            ->line("Error: " . $this->exception->getMessage())
            ->line("Please check the file format and try uploading again, or contact support if the issue persists.")
            ->action('View Media Files', $url)
            ->line('You can retry processing or upload a different file.');
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
            'error_message' => $this->exception->getMessage(),
            'type' => 'video_processing_failed',
            'failed_at' => now(),
        ];
    }
}
