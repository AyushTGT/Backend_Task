<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function addTask(Request $request)
    {
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
            $this->taskService->sendTaskNotification($task, 'assigned');
            
            // Send email
            $user = User::find($task->assignee);
            if ($user && $user->email) {
                Mail::html(
                    'You have been assigned a new task: ' . $task->title,
                    function ($message) use ($user) {
                        $message->to($user->email)->subject('New Task Assigned');
                    }
                );
            }
        }

        return response()->json(['success' => true, 'task' => $task], 201);
    }

    public function filterTasks(Request $request)
    {
        $query = Task::query();
        
        // Use service for complex filtering
        $this->taskService->applyFilters($query, $request);

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
        $this->taskService->applyFilters($query, $request);
        $count = $query->count();

        return response()->json(['success' => true, 'count' => $count], 200);
    }

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

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $data = $request->all();

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

        // Use service for notification
        $this->taskService->sendTaskNotification($task, 'updated');

        return response()->json(['message' => 'Task updated successfully.', 'task' => $task]);
    }

    public function updateTaskStatus(Request $request, $id)
    {
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

        // Use service for validation
        $error = $this->taskService->validateStatusTransition($oldStatus, $newStatus);
        if ($error) {
            return response()->json(['error' => $error], 422);
        }

        if ($newStatus === 'unassigned') {
            $task->assignee = null;
            $task->start_date = null;
        }

        if ($newStatus === 'completed') {
            $task->end_date = Carbon::today()->toDateString();
        }

        $task->status = $newStatus;
        $task->save();

        // Use service for notification
        $this->taskService->sendTaskNotification($task, 'updated');

        return response()->json(['success' => true, 'task' => $task]);
    }

    // Keep analytics methods as-is since they're simple and don't need extraction
    public function getCompletedTasksPerDay(Request $request)
    {
        $today = Carbon::today();
        $startDate = $today->copy()->subDays(6);

        $tasksQuery = Task::selectRaw('end_date as date, COUNT(*) as count')
            ->where('status', 'completed')
            ->whereBetween('end_date', [$startDate->toDateString(), $today->toDateString()]);

        if ($request->filled('assignee')) {
            $tasksQuery->where('assignee', $request->input('assignee'));
        }

        $tasks = $tasksQuery->groupBy('end_date')->get();
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

        return response()->json(['data' => $result]);
    }

    public function overDueTasks(Request $request)
    {
        $tasks = Task::where('due_date', '<', Carbon::today())
            ->where('status', '!=', 'completed')
            ->orWhere('status', '==', 'overdue');

        if ($request->filled('assignee')) {
            $tasks->where('assignee', $request->input('assignee'));
        }

        return response()->json(['success' => true, 'count' => $tasks->count()], 200);
    }

    public function taskCompletedThisMonth(Request $request)
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $tasks = Task::where('status', 'completed')
            ->whereBetween('end_date', [$startOfMonth, $endOfMonth]);

        if ($request->filled('assignee')) {
            $tasks->where('assignee', $request->input('assignee'));
        }

        return response()->json(['success' => true, 'count' => $tasks->count()], 200);
    }

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

        return response()->json(['created' => $createdArr, 'completed' => $completedArr]);
    }
}