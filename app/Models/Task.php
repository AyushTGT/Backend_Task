<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
        'status',
        'start_date',
        'end_date',
        'due_date',
        'priority',
        'project_name',
        'created_by',
        'reporter',
        'assignee',
    ];

    // The user who created the task
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // The user who assigned the task
    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // The user to whom the task is assigned
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assignee');
    }
}