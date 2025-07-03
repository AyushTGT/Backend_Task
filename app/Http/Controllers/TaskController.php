<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    // Creating a new task, and sending the notification to the assignee
    // and email to the assignee
    public function addTask(Request $request)
    {
        // $client = new \GuzzleHttp\Client(['verify' => false]);
        $validated = $this->validate($request, [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|string|max:20',
            'project_name' => 'nullable|string|max:100',
            'created_by' => 'nullable|exists:users,id',
            'reporter' => 'nullable|exists:users,id',
            'assignee' => 'nullable|exists:users,id'
        ]);

        $task = Task::create($validated);

        if ($task->assignee) {
            $notification = Notification::create([
                'assignee' => (string) $task->assignee,
                'status' => 'unread',
                'title' => 'New Task Assigned',
                'description' => "You have been assigned a new task: {$task->title}",
            ]);
            event(new NotificationCreated($notification, $task->assignee));
        }

        if ($task->assignee) {
            $user = User::find($task->assignee);

            if ($user && $user->email) {
                Mail::html(
                    'You have been assigned a new task: ' . $task->title,
                    function ($message) use ($user) {
                        $message
                            ->to($user->email)
                            ->subject('New Task Assigned');
                    }
                );
            }
        }

        return response()->json([
            'success' => true,
            'task' => $task
        ], 201);
    }

    // public function getTasks(Request $request)
    // {
    //     $tasks = Task::all();

    //     return response()->json([
    //         'success' => true,
    //         'tasks' => $tasks
    //     ], 200);
    // }

    // Filtering tasks based on various criteria
    // Duedate, status,title, description, priority, project_name, assignee, reporter, created_by
    public function filterTasks(Request $request)
    {
        $query = Task::query();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q
                    ->where('title', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('project_name')) {
            $query->where('project_name', $request->input('project_name'));
        }

        if ($request->filled('assignee')) {
            $query->where('assignee', $request->input('assignee'));
        }

        if ($request->filled('reporter')) {
            $query->where('created_by', $request->input('reporter'));
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->input('created_by'));
        }

        $start = $request->input('due_date_start');
        $end = $request->input('due_date_end');
        if ($start && $end) {
            $query->whereBetween('due_date', [$start, $end]);
        } elseif ($start) {
            $query->where('due_date', '>=', $start);
        } elseif ($end) {
            $query->where('due_date', '<=', $end);
        }

        $sortField = $request->input('sort', 'title');
        $sortOrder = $request->input('order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        $perPage = $request->input('per_page', 10);
        $tasks = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'tasks' => $tasks->items(),
            'current_page' => $tasks->currentPage(),
            'per_page' => $tasks->perPage(),
            'total' => $tasks->total(),
            'last_page' => $tasks->lastPage(),
        ], 200);
    }

    public function countTasks(Request $request)
    {
        $query = Task::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('due_date')) {
            $query->whereDate('due_date', $request->input('due_date'));
        }

        if ($request->filled('project_name')) {
            $query->where('project_name', 'like', '%' . $request->input('project_name') . '%');
        }

        if ($request->filled('assignee')) {
            $query->where('assignee', $request->input('assignee'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('start_date', $request->input('start_date'));
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->input('created_by'));
        }

        if ($request->filled('assigned_by')) {
            $query->where('assigned_by', $request->input('assigned_by'));
        }

        $count = $query->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ], 200);
    }

    // Updating a task details
    // This would also trigger an event to notify the assignee
    public function updateTask(Request $request, $id)
    {
        $user = Auth::user();

        $task = Task::find($id);
        if (!$task) {
            return response()->json(['error' => 'Task not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'status' => 'sometimes',
            'priority' => 'sometimes',
            'start_date' => 'sometimes|nullable|date',
            'due_date' => 'sometimes|nullable|date',
            'description' => 'sometimes|nullable|string',
            'assignee' => 'sometimes|nullable|integer|exists:users,id',
        ]);

        $data = $request->all();

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        foreach ($validated as $key => $value) {
            $task->$key = $value;
        }

        if (array_key_exists('assignee', $data) && !empty($data['assignee']) && empty($task->start_date)) {
            $task->start_date = Carbon::today()->toDateString();
            $task->status = 'pending';
            $task->reporter = $user->id;
        }

        if (array_key_exists('assignee', $data) && empty($data['assignee'])) {
            $task->assignee = null;
            $task->status = 'Unassigned';
            $task->start_date = null;
        }

        if (array_key_exists('status', $data) && $data['status'] === 'completed') {
            $task->end_date = Carbon::today()->toDateString();
        }

        $task->save();

        if ($task->created_by) {
            $notification = Notification::create([
                'assignee' => (string) $task->created_by,
                'status' => 'unread',
                'title' => 'Task Status Changed',
                'description' => "Your task status has been changed: {$task->title} to {$task->status}",
            ]);
            event(new NotificationCreated($notification, $task->created_by));
        }

        return response()->json([
            'message' => 'Task updated successfully.',
            'task' => $task
        ]);
    }

    // Apis for getting tasks completed per day for the last 7 days
    // For analytics
    public function getCompletedTasksPerDay(Request $request)
    {
        $today = Carbon::today();
        $startDate = $today->copy()->subDays(6);

        $tasksQuery = Task::selectRaw('end_date as date, COUNT(*) as count')
            ->where('status', 'completed')
            ->whereBetween('end_date', [$startDate->toDateString(), $today->toDateString()]);

        // Apply assignee filter if provided
        if ($request->filled('assignee')) {
            $tasksQuery->where('assignee', $request->input('assignee'));
        }

        $tasks = $tasksQuery
            ->groupBy('end_date')
            ->get();

        $countsByDate = [];
        foreach ($tasks as $task) {
            $countsByDate[$task->date] = $task->count;
        }

        $result = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateString = $date->toDateString();
            $result[] = isset($countsByDate[$dateString]) ? intval($countsByDate[$dateString]) : 0;
        }

        return response()->json([
            'data' => $result
        ]);
    }

    // Returning the count of tasks that are overdue
    // Analytics
    public function overDueTasks(Request $request)
    {
        $tasks = Task::where('due_date', '<', Carbon::today())
            ->where('status', '!=', 'completed')
            ->orWhere('status', '==', 'overdue');

        if ($request->filled('assignee')) {
            $tasks->where('assignee', $request->input('assignee'));
        }

        $count = $tasks->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ], 200);
    }

    // Count of tasks completed this month
    // Analytics
    public function taskCompletedThisMonth(Request $request)
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $tasks = Task::where('status', 'completed')
            ->whereBetween('end_date', [$startOfMonth, $endOfMonth]);

        if ($request->filled('assignee')) {
            $tasks->where('assignee', $request->input('assignee'));
        }

        $count = $tasks->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ], 200);
    }

    // Get task countof tasks created per VS Completed per month for the last 12 months
    // Analytics
    public function byMonths(Request $request)
    {
        $now = Carbon::now();
        $start = $now->copy()->subMonths(11)->startOfMonth();
        $end = $now->copy()->endOfMonth();

        $created = Task::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->keyBy(function ($item) {
                return $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
            });

        $completed = Task::selectRaw('YEAR(end_date) as year, MONTH(end_date) as month, COUNT(*) as count')
            ->where('status', 'completed')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$start, $end])
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->keyBy(function ($item) {
                return $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
            });

        $createdArr = [];
        $completedArr = [];
        for ($i = 0; $i < 12; $i++) {
            $date = $start->copy()->addMonths($i);
            $key = $date->year . '-' . str_pad($date->month, 2, '0', STR_PAD_LEFT);
            $createdArr[] = isset($created[$key]) ? intval($created[$key]->count) : 0;
            $completedArr[] = isset($completed[$key]) ? intval($completed[$key]->count) : 0;
        }

        return response()->json([
            'created' => $createdArr,
            'completed' => $completedArr
        ]);
    }

    // Updating a task' status
    // updating would triggger an event to notify the assignee
    public function updateTaskStatus(Request $request, $id)
    {
        $user = Auth::user();

        $task = Task::find($id);
        if (!$task) {
            return response()->json(['error' => 'Task not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        $oldStatus = $task->status;
        
        $newStatus = $request->input('status');

        if( $newStatus === 'unassigned'){
            $task->assignee = null;
            $task->start_date = null;
        }

        if( $oldStatus === 'unassigned' && $newStatus !== 'cancelled'){
            return response()->json(['error' => 'Select an Assignee before changing the status.'], 422);
        }

        
        if ($newStatus === 'completed'){
            $task->end_date = Carbon::today()->toDateString();
        }
        

        if ($oldStatus !== 'completed' && $task->status === 'completed') {
            $task->end_date = Carbon::today()->toDateString();
        }

        if( $oldStatus === 'pending' && $newStatus ==='overdue'){
            return response()->json(['error' => 'Due date is yet to come'], 422);
        }

        if( $oldStatus === 'overdue' && $newStatus ==='pending'){
            return response()->json(['error' => 'Due date has passed'], 422);
        }

        $task->status = $newStatus;
        $task->save();
        if ($task->created_by) {
            $notification = Notification::create([
                'assignee' => (string) $task->created_by,
                'status' => 'unread',
                'title' => 'Task Status Changed',
                'description' => "Your task status has been changed: {$task->title} to {$task->status}"
            ]);
            event(new NotificationCreated($notification, $task->created_by));
        }

        return response()->json([
            'success' => true,
            'task' => $task
        ]);
    }
}
