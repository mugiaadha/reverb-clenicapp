<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueCalled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $prefix;
    public int $number;
    public string $pasien;
    public ?string $poli;
    public ?string $channel;

    /**
     * Create a new event instance.
     */
    public function __construct(string $prefix, int $number, string $pasien, ?string $poli = null, ?string $channel = null)
    {
        $this->prefix = $prefix;
        $this->number = $number;
        $this->pasien = $pasien;
        $this->poli = $poli;
        $this->channel = $channel;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        // If a specific channel was provided, broadcast on "{channel}.queue"
        if ($this->channel && is_string($this->channel) && trim($this->channel) !== '') {
            $name = trim($this->channel);
            return new Channel($name . '.queue');
        }
        return new Channel('queue-display');
    }

    public function broadcastWith()
    {
        return [
            'prefix' => $this->prefix,
            'number' => $this->number,
            'pasien' => $this->pasien,
            'poli' => $this->poli,
            'channel' => $this->channel,
        ];
    }
}
