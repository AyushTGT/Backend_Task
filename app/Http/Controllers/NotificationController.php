<?php

namespace App\Http\Controllers;
use App\Models\Notification;
use Illuminate\Http\Request;




class NotificationController extends Controller
{
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'assignee'    => 'required|string',
            'status'      => 'required|string',
            'title'       => 'required|string',
            'description' => 'nullable|string',
        ]);

        $notification = Notification::create($validated);

        return response()->json([
            'message' => 'Notification created successfully.',
            'data'    => $notification,
        ], 201);
    }

    //List all notifications with optional filtering by assignee
    public function getNotif(Request $request)
    {
        $assignee = $request->query('assignee');
        $query = Notification::query();

        if ($assignee) {
            $query->where('assignee', $assignee)->where('status', 'unread');
        }

        $notifications = $query->get();

        return response()->json($notifications);
    }

    //Marking a notification as read
    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['status' => 'read']);

        return response()->json([
            'message' => 'Notification marked as read.',
            'data'    => $notification,
        ]);
    }
}