<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NotificationCreated extends Event implements ShouldBroadcast
{
    public $notification;
    public $assignee;
    public $queue = "database";

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
        \Log::info('BroadcastOn method called');
        $channel = new Channel('user.' . $this->assignee);
        \Log::info('Broadcasting on channel', ['channel' => $channel->name]);

        return $channel;
    }

    public function broadcastAs()
    {
        return 'notification.created';
    }

    public function broadcastWith()
    {
        // dd(debug_backtrace(true, 2));
        \Log::info('BroadcastWith method called', [
            'notification' => $this->notification,
            'assignee' => $this->assignee,
        ]);
        return $this->notification;
    }
}
