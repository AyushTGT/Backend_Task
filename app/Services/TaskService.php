<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TaskService
{
    //Filter query based on request parameters
    public function applyFilters($query, Request $request): void
    {
        // Search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }

        // Simple field filters
        $filters = ['status', 'priority', 'project_name', 'assignee', 'created_by', 'assigned_by'];
        foreach ($filters as $filter) {
            if ($request->filled($filter)) {
                if ($filter === 'project_name') {
                    $query->where($filter, 'like', '%' . $request->input($filter) . '%');
                } else {
                    $query->where($filter, $request->input($filter));
                }
            }
        }

        if ($request->filled('reporter')) {
            $query->where('created_by', $request->input('reporter'));
        }

        // Date range filters
        $start = $request->input('due_date_start');
        $end = $request->input('due_date_end');
        if ($start && $end) {
            $query->whereBetween('due_date', [$start, $end]);
        } elseif ($start) {
            $query->where('due_date', '>=', $start);
        } elseif ($end) {
            $query->where('due_date', '<=', $end);
        }

        // Specific date filters
        if ($request->filled('due_date')) {
            $query->whereDate('due_date', $request->input('due_date'));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('start_date', $request->input('start_date'));
        }
    }

    
     //Send notification when task is created/assigned
     
    public function sendTaskNotification(Task $task, string $type): void
    {
        if (!$task->assignee && $type === 'assigned') return;
        if (!$task->created_by && $type === 'updated') return;

        $notifications = [
            'assigned' => [
                'assignee' => $task->assignee,
                'title' => 'New Task Assigned',
                'description' => "You have been assigned a new task: {$task->title}",
            ],
            'updated' => [
                'assignee' => $task->created_by,
                'title' => 'Task Status Changed',
                'description' => "Your task status has been changed: {$task->title} to {$task->status}",
            ]
        ];

        if (!isset($notifications[$type])) return;

        $config = $notifications[$type];
        
        $notification = Notification::create([
            'assignee' => (string) $config['assignee'],
            'status' => 'unread',
            'title' => $config['title'],
            'description' => $config['description'],
        ]);

        event(new NotificationCreated($notification, $config['assignee']));
    }

    //Error messages for invalid status transitions
    public function validateStatusTransition(string $oldStatus, string $newStatus): ?string
    {
        if ($oldStatus === 'unassigned' && $newStatus !== 'cancelled') {
            return 'Select an Assignee before changing the status.';
        }

        if ($oldStatus === 'pending' && $newStatus === 'overdue') {
            return 'Due date is yet to come';
        }

        if ($oldStatus === 'overdue' && $newStatus === 'pending') {
            return 'Due date has passed';
        }

        return null; // Valid transition
    }
}