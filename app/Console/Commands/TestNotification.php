<?php

namespace App\Console\Commands;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TestNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test_sending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifgication';

    // Send emails to users with task summaries and reminders
    public function handle()
    {
        $notification = Notification::create([
            'assignee' => (string) 1,
            'status' => 'unread',
            'title' => 'Task Status Changed',
            'description' => 'Your task status has been changed TestTask to pending',
        ]);
        event(new NotificationCreated($notification, 1));
    }
}
