<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modesl\Task;
use App\Services\AuthService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tymon\JWTAuth\JWTAuth;
use Hash;

class UserController extends Controller
{
    /**
     * @var \Tymon\JWTAuth\JWTAuth
     */
    protected $jwt,
        $authService;

    public function __construct(
        JWTAuth $jwt,
        UserService $userService,
        AuthService $authService
    ) {
        $this->jwt = $jwt;
        $this->userService = $userService;
        $this->authService = $authService;
    }

    // login api, generates JWT token, sets up the login timestamps, and returns the token
    public function postLogin(Request $request)
{
    $this->validate($request, [
        'email' => 'required|email|max:255',
        'password' => 'required',
        'recaptchaToken' => 'required',
        'rememberMe' => 'boolean', // Add validation for rememberMe
    ]);

    $client = new \GuzzleHttp\Client();
    $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
        'form_params' => [
            'secret' => '6LcDu3IrAAAAACLcc8FQ2YvpRT7l8M4MkbhcCOIM',
            'response' => $request->recaptchaToken,
            'remoteip' => $request->ip(),
        ],
    ]);
    $body = json_decode((string) $response->getBody(), true);

    if (empty($body['success']) || !$body['success']) {
        return response()->json(['error' => 'CAPTCHA verification failed'], 422);
    }

    $result = $this->authService->loginService($request->email, $request->password);

    if (isset($result['error'])) {
        return response()->json(['error' => $result['error']], $result['code']);
    }

    $user = $result['user'];

    try {
        // Set token TTL based on rememberMe
        $rememberMe = $request->boolean('rememberMe', false);
        $ttl = $rememberMe ? 43200 : 10080; // 30 days (43200 min) vs 7 days (10080 min)
        
        // Set the token TTL
        auth()->factory()->setTTL($ttl);
        
        if (!$token = auth()->attempt($request->only('email', 'password'))) {
            return response()->json(['error' => 'Invalid credentials.'], 401);
        }
    } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
        return response()->json(['error' => 'Token expired.'], 500);
    } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
        return response()->json(['error' => 'Token invalid.'], 500);
    } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
        return response()->json(['error' => 'Could not create token.'], 500);
    }

    $this->authService->updateLoginTimestamps($user);
    return response()->json([
        'token' => $token,
        'expires_in' => $ttl * 60, // Return expiration time in seconds
        'remember_me' => $rememberMe
    ], 200);
}

    // Registering a new User, Checking if it already exists
    // Using a random verification token to verify the email, by opening a link in the email
    public function RegisteringUser(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'post' => 'required|string|in:User,Admin,Master',
        ]);

        $user = $this->userService->findByEmail($request->email);

        if ($user) {
            if ($user->deleted_by !== null) {
                return response()->json(['error' => 'User has been deleted by admin.'], 403);
            }
            if ($user->email_verified_at !== null) {
                return response()->json(['error' => 'User already exists. Please log in.'], 409);
            }
            return response()->json(['error' => 'User already exists. Please verify your account.'], 409);
        }

        $verificationToken = Str::random(60);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => app('hash')->make($request->password),
            'post' => $request->post,
            'verification_token' => $verificationToken,
        ]);

        // $frontendUrl = 'http://localhost:3000/verified?token=' . $verificationToken;
        $frontendUrl = config('constants.BASE_URL') . '/verified?token=' . $verificationToken;
        Mail::html(
            "Please verify your email by clicking this link: <a href=\"$frontendUrl\">$frontendUrl</a>",
            function ($message) use ($user) {
                $message
                    ->to($user->email)
                    ->subject('Verify Your Email');
            }
        );

        return response()->json(['message' => 'User registered successfully. Please check your email to verify your account.'], 201);
    }

    // Re-registering a user, if the user has deactivated his account and not been deleted by admin,
    // this will generate a new verification token and send it to the user's email
    public function reRegisteringUser(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();
        $verificationToken = Str::random(60);
        $user->verification_token = $verificationToken;
        $user->email_verified_at = null;
        $user->deleted_by = null;
        $user->save();

        // $frontendUrl = 'http://localhost:3000/verified?token=' . $verificationToken;
        $frontendUrl = config('constants.BASE_URL') . '/verified?token=' . $verificationToken;
        Mail::html(
            "Please verify your email by clicking this link: <a href=\"$frontendUrl\">$frontendUrl</a>",
            function ($message) use ($user) {
                $message
                    ->to($user->email)
                    ->subject('Verify Your Email');
            }
        );
        return response()->json(['message' => 'User registered successfully. Please check your email to verify your account.'], 201);
    }

    // Master being able to add a new user,
    // generate a random password and send it to the user's email along with a verification link
    public function AddUser(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'post' => 'required|string',
        ]);

        $verificationToken = Str::random(60);
        $passwordValue = Str::random(10);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => app('hash')->make($passwordValue),
            'post' => $request->post,
            'verification_token' => $verificationToken,
        ]);

        // $frontendUrl = 'http://localhost:3000/verified?token=' . $verificationToken;
        $frontendUrl = config('constants.BASE_URL') . '/verified?token=' . $verificationToken;

        $changePasswordUrl = 'http://localhost:3000/reset?email=' . $user->email;
        Mail::html(
            "Please verify your email first by clicking this link: <a href=\"$frontendUrl\" target=\"_blank\">Verify Email</a>
            Please change the password after verification. 

            <a href=\"$changePasswordUrl\" target=\"_blank\">Change Password</a> 

            
            Your temporary password is: $passwordValue",
            function ($message) use ($user) {
                $message
                    ->to($user->email)
                    ->subject('Verify Your Email and change password');
            }
        );

        return response()->json(['message' => 'User registered successfully. Please check your email to verify your account.'], 201);
    }

    // Filtering users based on name, email, post, and using pagination and sorting
    public function getUser(Request $request)
    {
        $params = $request->only(['search', 'post', 'sort', 'order']);
        $query = $this->userService->findUsers($params);

        $perPage = $request->input('per_page', 10);
        $users = $query->paginate($perPage);
        return response()->json($users);
    }

    // public function all()
    // {
    //     $users = User::all();
    //     return response()->json($users);
    // }

    // Deleting a particular user by ID, only Master can delete users
    // We are soft deleting the user by setting email_verified_at to null and deleted_by to the auth user's email
    public function delete($id)
    {
        $authUser = Auth::user();
        if ($authUser->post !== 'Master') {
            return response()->json(['error' => 'Forbidden: Only master can delete users.'], 403);
        }

        $user = $this->userService->findById($id);
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }
        $user->email_verified_at = NULL;
        $user->deleted_by = $authUser->email;
        $user->save();

        return response()->json(['message' => 'User deleted.']);
    }

    // Deleting multiple users by IDs, only Master can delete users
    // if selected a user with post Master, then return an error
    public function bulkDelete(Request $request)
    {
        $authUser = Auth::user();
        if ($authUser->post !== 'Master') {
            return response()->json([
                'message' => 'Forbidden: Only master can delete users.'
            ], 403);
        }

        $ids = $request->input('ids');
        if (!is_array($ids) || empty($ids)) {
            return response()->json([
                'message' => 'No IDs provided.'
            ], 400);
        }

        $users = User::whereIn('id', $ids)->get();
        foreach ($users as $user) {
            if ($user->post === 'Master') {
                return response()->json([
                    'message' => 'You selected a Master. Cannot delete master user.'
                ], 403);
            }
        }

        foreach ($users as $user) {
            $user->email_verified_at = null;
            $user->deleted_by = $authUser->email;
            $user->save();
        }

        return response()->json([
            'message' => count($users) . ' users deleted successfully.',
            'count' => count($users)
        ]);
    }

    // Bulk change the role of multiple users
    // role:User cant' do the changes other can
    // if selected a user with post Master, then return an error
    public function bulkRole(Request $request)
    {
        $authUser = Auth::user();
        if ($authUser->post === 'User') {
            return response()->json(['error' => 'Forbidden: You can not change user role.'], 403);
        }
        $ids = $request->input('ids');
        $role = $request->input('role');
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['error' => 'No IDs provided.'], 400);
        }
        if (!$role) {
            return response()->json(['error' => 'No role specified.'], 400);
        }
        // $allowedRoles = ['User', 'Admin', 'Master'];
        $allowedRoles = config('constants.allowed_roles');

        if (!in_array($role, $allowedRoles)) {
            return response()->json(['error' => 'Invalid role specified.'], 400);
        }

        $users = User::whereIn('id', $ids)->get();
        foreach ($users as $user) {
            if ($user->post === 'Master') {
                return response()->json(['error' => 'You selected a Master. Cannot change role of master user.'], 403);
            }
        }
        foreach ($users as $user) {
            $user->post = $role;
            $user->save();
        }
        return response()->json(['message' => 'Users role changed.', 'count' => count($users)]);
    }

    // Verifying the email of the user after he clicked on the verification link
    // If the token is valid, verify
    public function verifyEmail(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return response()->json(['message' => 'Missing verification token.'], 400);
        }

        $user = User::where('verification_token', $token)->first();
        if (!$user) {
            return response()->json(['message' => 'Invalid or expired verification token.'], 400);
        }
        // $nuser = User::where('verification_token', $token)->first();
        $user->email_verified_at = Carbon::now();
        $user->verification_token = null;
        $user->save();

        Mail::html(
            'Welcome to our application, your email has been verified successfully. You can now log in with your credentials.',
            function ($message) use ($user) {
                $message
                    ->to($user->email)
                    ->subject('Welcome');
            }
        );

        return response()->json(['message' => 'Email verified successfully! You can now log in.']);
    }

    // Updating the user details, only Master can update users
    // If the user is trying to change the email, send a verification link to the new email
    // If trying to edit master error
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'post' => 'required|string',
        ]);

        $authUser = Auth::user();
        if ($authUser->post === 'User' && $authUser->id !== $id) {
            return response()->json(['error' => "Forbidden: You can't edit users."], 403);
        }

        $user = User::where('id', $id)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }
        if ($user->deleted_by !== null) {
            return response()->json(['error' => 'User has been deleted by admin.'], 403);
        }
        if ($user->post === 'Master' && $authUser->id != $id) {
            return response()->json(['error' => 'You cannot edit a Master user.'], 403);
        }

        $user->name = $request->input('name');

        $newEmail = $request->input('email');
        if ($newEmail && $newEmail !== $user->email) {
            $user->email = $newEmail;
            $user->email_verified_at = null;
            $verificationToken = Str::random(60);
            $user->verification_token = $verificationToken;

            // $frontendUrl = 'http://localhost:3000/verified?token=' . $verificationToken;
            $frontendUrl = config('constants.BASE_URL') . '/verified?token=' . $verificationToken;
            Mail::html(
                "Please verify your new email by clicking this link: <a href=\"$frontendUrl\">$frontendUrl</a>",
                function ($message) use ($user) {
                    $message
                        ->to($user->email)
                        ->subject('Verify Your New Email');
                }
            );
        }
        $user->post = $request->input('post', $user->post);
        $user->save();
        return response()->json(['message' => 'User updated successfully.', 'user' => $user]);
    }

    // Sending a password reset link to the user's email
    public function forgetPassword(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // $resetUrl = url('/resetForm?email=' . urlencode($user->email));
        // $frontendUrl = 'http://localhost:3000/reset?email=' . $user->email;
        $frontendUrl = config('constants.BASE_URL') . '/reset?email=' . $user->email;

        Mail::html(
            "Click this link to reset your password: <a href=\"$frontendUrl\">Reset Password</a>",
            function ($message) use ($user) {
                $message
                    ->to($user->email)
                    ->subject('Reset Your Password');
            }
        );

        return response()->json([
            'message' => 'Password reset link sent. Please check your email.'
        ], 200);
    }

    // Resetting the password after the user clicks on the reset link
    public function resetPassword(Request $request)
    {
        $this->validate($request, [
            'password' => 'required|min:8',
        ]);

        $user = User::where('email', $request->email)->first();
        $user->password = app('hash')->make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password reset successfully.'
        ], 200);
    }

    // Checking the availability of the email use,
    // Whether it exists, verified, or deleted
    public function checkEmail(Request $request)
    {
        $email = $request->input('email');
        if (!$email) {
            return response()->json(['error' => 'Email is required'], 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'exists' => false,
                'verified' => false,
                'deleted' => null
            ]);
        }
        if ($user->deleted_by !== null) {
            return response()->json([
                'exists' => true,
                'verified' => false,
                'deleted' => $user->deleted_by
            ]);
        }
        return response()->json([
            'exists' => true,
            'verified' => $user->email_verified_at !== null,
            'deleted' => null
        ]);
    }

    // Logging out the user, updating the last logout timestamp and total duration logged in
    public function postLogout(Request $request)
    {
        try {
            $user = app('auth')->user();
            Auth::logout();

            $user->last_logout = Carbon::now();
            if ($user->total_duration_loggedin === null) {
                $user->total_duration_loggedin = 0;
            }
            $user->total_duration_loggedin += Carbon::now()->diffInSeconds($user->last_login);
            $user->save();
            return response()->json(['message' => 'Successfully logged out'], 200);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Failed to logout, please try again.'], 500);
        }
    }

    // Getting the authenticated user's details
    public function me(Request $request)
    {
        $user = Auth::user();
        return response()->json($user);
    }

    // Master Verify in case required to bring back the deleted user
    public function masterVerify(Request $request, $id)
    {
        $user = User::where('id', $id)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'User already verified.'], 200);
        }
        $user->deleted_by = null;
        $user->email_verified_at = Carbon::now();
        $user->save();
        return response()->json(['message' => 'User verified successfully.']);

        // $frontendUrl = config('constants.BASE_URL') . '/verified?token=' . $user->verification_token;
        // Mail::html(
        //     "Please verify your email by clicking this link: <a href=\"$frontendUrl\">$frontendUrl</a>",
        //     function ($message) use ($user) {
        //         $message
        //             ->to($user->email)
        //             ->subject('Verify Your Email');
        //     }
        // );

        // return response()->json(['message' => 'Verification email sent to user.']);
    }

    // Exporting the users to a CSV file
    public function exportCSV(Request $request)
    {
        $params = $request->only(['search', 'post', 'sort', 'order']);
        $query = $this->userService->findUsers($params);

        $users = $query->get();

        if ($users->isEmpty()) {
            return response()->json(['message' => 'No users found.'], 404);
        }

        $output = fopen('php://temp', 'r+');

        fputcsv($output, array_keys($users->first()->toArray()));
        foreach ($users as $user) {
            fputcsv($output, $user->toArray());
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users.csv"',
        ];
        return response($csv, 200, $headers);
    }

    // Exporting the username and id, to ease the mapping while task assignment
    // This is used in the task assignment dropdown
    public function userName()
    {
        $users = User::select('id', 'name')->whereNull('deleted_by')->get();
        return response()->json($users);
    }

    // Based on the user's role, the tasks are fetched
    // If the user is a Master, all tasks are fetched
    // If the user is not a Master, only the tasks assigned to the user or created by the user are fetched
    public function getTasks(Request $request)
    {
        $user = Auth::user();
        if ($user->post === 'Master') {
            $tasks = \App\Models\Task::all();
        } else {
            $tasks = \App\Models\Task::where('assignee', $user->id)
                ->orWhere('created_by', $user->id)
                ->get();
        }

        return response()->json([
            'success' => true,
            'tasks' => $tasks
        ], 200);
    }
}
