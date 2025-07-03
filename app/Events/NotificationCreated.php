<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NotificationCreated extends Event implements ShouldBroadcast
{
    public $notification;
    public $assignee;

    public function __construct($notification, $assignee)
    {
        $this->notification = $notification;
        $this->assignee = $assignee;
    }

    public function broadcastOn()
    {
        // Each user gets their own channel
        return new Channel('user.' . $this->assignee);
    }

    public function broadcastAs()
    {
        return 'notification.created';
    }

    public function broadcastWith()
    {
        return [
            'notification' => $this->notification,
            'assignee' => $this->assignee,
        ];
    }
}
