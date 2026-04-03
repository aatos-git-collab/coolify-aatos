<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KubernetesAppStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $appId;
    public string $appName;
    public string $status;
    public ?string $message;
    public ?int $teamId;

    public function __construct(string $appId, string $appName, string $status, ?string $message = null, ?int $teamId = null)
    {
        $this->appId = $appId;
        $this->appName = $appName;
        $this->status = $status;
        $this->message = $message;
        $this->teamId = $teamId;
    }

    public function broadcastOn(): array
    {
        if (is_null($this->teamId)) {
            return [];
        }

        return [
            new PrivateChannel("team.{$this->teamId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'k8s-app-status';
    }
}
