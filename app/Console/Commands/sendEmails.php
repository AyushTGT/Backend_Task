<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:send_summaryEmails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send emails to users with task summarys and reminders';

    /**
     * Execute the console command.
     *
     * @return int
     */
    // public function handle()
    // {
    // $users = User::all();
    // $users = User::where('email', 'like', 'ayushtomar.iis@gmail.com')->get(); // Example filter, adjust as needed
    // $today = Carbon::now()->format('Y-m-d');
    // foreach ($users as $user) {
    //     $pendingTasks = Task::where('assignee', $user->id)
    //         ->where('status', 'pending')
    //         ->count();
    //     $tomorrow = Carbon::tomorrow()->toDateString();
    //     $tasksDueTomorrow = Task::where('assignee', $user->id)
    //         ->whereDate('due_date', $tomorrow)
    //         ->get();
    //     $emailContent = "Hello {$user->name},\n";
    //     $emailContent .= "You have {$pendingTasks} pending tasks.\n";
    //     if ($tasksDueTomorrow->count()) {
    //         $emailContent .= "Tasks due tomorrow:\n";
    //         foreach ($tasksDueTomorrow as $task) {
    //             $emailContent .= "- {$task->title}\n";
    //         }
    //     } else {
    //         $emailContent .= "You have no tasks due tomorrow.\n";
    //     }
    //     sleep(1);  // Simulate a delay for each email
    //     Mail::raw($emailContent, function ($message) use ($user) {
    //         $message
    //             ->to($user->email)
    //             ->subject('Your Task Summary');
    //     });
    // }
    // $this->info('Task summary emails sent!');
    // }


    //Send emails to users with task summaries and reminders
    public function handle()
    {
        $users = User::where('email', 'like', 'ayushtomar.iis@gmail.com')->get();
        //$users = User::all(); 
        $today = Carbon::now()->format('Y-m-d');

        foreach ($users as $user) {
            $pendingTasks = Task::where('assignee', $user->id)
                ->where('status', 'pending')
                ->count();

            $tomorrow = Carbon::tomorrow()->toDateString();
            $tasksDueTomorrow = Task::where('assignee', $user->id)
                ->whereDate('due_date', $tomorrow)
                ->get();

            $emailContent = "Hello {$user->name},\n";
            $emailContent .= "You have {$pendingTasks} pending tasks.\n";
            if ($tasksDueTomorrow->count()) {
                $emailContent .= "Tasks due tomorrow:\n";
                foreach ($tasksDueTomorrow as $task) {
                    $emailContent .= "- {$task->title}\n";
                }
            } else {
                $emailContent .= "You have no tasks due tomorrow.\n";
            }

            $email = \App\Models\Email::create([
                'user_id' => $user->id,
                'to_email' => $user->email,
                'subject' => 'Your Task Summary',
                'body' => $emailContent,
                'status' => 'pending',
            ]);
            
            dispatch(new \App\Jobs\SendEmailJob($email->id));
        }

        $this->info('Task summary emails queued!');
    }
}
