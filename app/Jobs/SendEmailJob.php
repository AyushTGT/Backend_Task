<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Mail;
use App\Models\Email; // your log table's model
use Exception;
use Illuminate\Support\Facades\Log;

class SendEmailJob extends Job
{
    protected $emailId;

    /**
     * Create a new job instance.
     *
     * @param int $emailId
     */
    public function __construct($emailId)
    {
        $this->emailId = $emailId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $email = Email::find($this->emailId);

        // Check if email exists and is pending
        if (!$email || $email->status !== 'pending') {
            Log::info('Email not found or not pending', ['emailId' => $this->emailId]);
            return;
        }

        try {
            $email->status = 'sent';
            Mail::raw($email->body, function ($message) use ($email) {
                $message->to($email->to_email)
                        ->subject($email->subject);
            });

            $email->status = 'sent';
            $email->sent_at = now();
            $email->error_message = null;
            //Log::info('Email marked as sent', ['emailId' => $this->emailId]);
            $email->save();
        } catch (Exception $e) {
            $email->status = 'failed';
            $email->error_message = $e->getMessage();
        }

        $email->save();
    }
}