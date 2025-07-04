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
        \Log::info('NotificationCreated event initialized', [
            'notification' => $this->notification,
            'assignee' => $this->assignee,
        ]);
    }

    public function broadcastOn()
    {
        // Each user gets their own channel
        \Log::info('BroadcastsOn methhod called');
        $channel = new Channel('user.' . $this->assignee);
        \Log::info('Channel created: ' . $channel->name);
        return $channel;
        // return new Channel('notifications');
    }

    public function broadcastAs()
    {
        return 'notification.created';
    }
}
