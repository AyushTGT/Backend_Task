<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    protected $table = 'emails'; // Your table name

    // Allow mass assignment for these columns
    protected $fillable = [
        'user_id',
        'to_email',
        'subject',
        'body',
        'status',
        'error_message',
        'sent_at',
    ];

    // Optionally, if you want sent_at to be treated as a Carbon date
    protected $dates = [
        'sent_at',
    ];
}