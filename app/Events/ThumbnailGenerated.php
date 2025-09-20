<?php

namespace App\Events;

use App\Models\MediaFile;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThumbnailGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MediaFile $mediaFile;
    public array $thumbnails;

    public function __construct(MediaFile $mediaFile, array $thumbnails)
    {
        $this->mediaFile = $mediaFile;
        $this->thumbnails = $thumbnails;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}