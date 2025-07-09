<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Task; 
use App\Services\AuthService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tymon\JWTAuth\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use GuzzleHttp\Client;
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
        ]);

        $client = new Client();
        $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
            'form_params' => [
                'secret' => env('RECAPTCHA_SECRET_KEY'),
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
            if (!$token = auth()->attempt($request->only('email', 'password'))) {
                return response()->json(['error' => 'Invalid credentials.'], 401);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expired.'], 500);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token invalid.'], 500);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not create token.'], 500);
        }

        $this->authService->updateLoginTimestamps($user);
        return response()->json(['token' => $token], 200);
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

        $user = $this->userService->createUser([
            'name' => $request->name,
            'email' => $request->email,
            'password' => app('hash')->make($request->password),
            'post' => $request->post,
            'verification_token' => $verificationToken,
        ]);

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

        $user = $this->userService->findByEmail($request->email);
        $verificationToken = Str::random(60);
        
        $this->userService->updateUser($user, [
            'verification_token' => $verificationToken,
            'email_verified_at' => null,
            'deleted_by' => null
        ]);

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

        $user = $this->userService->createUser([
            'name' => $request->name,
            'email' => $request->email,
            'password' => app('hash')->make($passwordValue),
            'post' => $request->post,
            'verification_token' => $verificationToken,
        ]);

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
        
        $this->userService->softDeleteUser($user, $authUser->email);

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

        $users = $this->userService->findUsersByIds($ids);
        foreach ($users as $user) {
            if ($user->post === 'Master') {
                return response()->json([
                    'message' => 'You selected a Master. Cannot delete master user.'
                ], 403);
            }
        }

        foreach ($users as $user) {
            $this->userService->softDeleteUser($user, $authUser->email);
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
        
        $allowedRoles = config('constants.allowed_roles');

        if (!in_array($role, $allowedRoles)) {
            return response()->json(['error' => 'Invalid role specified.'], 400);
        }

        $users = $this->userService->findUsersByIds($ids);
        foreach ($users as $user) {
            if ($user->post === 'Master') {
                return response()->json(['error' => 'You selected a Master. Cannot change role of master user.'], 403);
            }
        }
        
        $this->userService->bulkUpdatePost($users, $role);
        
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

        $user = $this->userService->findByVerificationToken($token);
        if (!$user) {
            return response()->json(['message' => 'Invalid or expired verification token.'], 400);
        }
        
        $this->userService->verifyUser($user);

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
        if ($authUser->post === 'User') {
            return response()->json(['error' => "Forbidden: You can't edit users."], 403);
        }

        $user = $this->userService->findById($id);
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }
        if ($user->deleted_by !== null) {
            return response()->json(['error' => 'User has been deleted by admin.'], 403);
        }
        if ($user->post === 'Master') {
            return response()->json(['error' => 'You cannot edit a Master user.'], 403);
        }

        $updateData = ['name' => $request->input('name')];

        $newEmail = $request->input('email');
        if ($newEmail && $newEmail !== $user->email) {
            $updateData['email'] = $newEmail;
            $updateData['email_verified_at'] = null;
            $verificationToken = Str::random(60);
            $updateData['verification_token'] = $verificationToken;

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
        
        $updateData['post'] = $request->input('post', $user->post);
        $user = $this->userService->updateUser($user, $updateData);
        
        return response()->json(['message' => 'User updated successfully.', 'user' => $user]);
    }

    // Sending a password reset link to the user's email
    public function forgetPassword(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email|exists:users,email',
        ]);

        $user = $this->userService->findByEmail($request->email);

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

        $user = $this->userService->findByEmail($request->email);
        $this->userService->updatePassword($user, $request->password);

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

        $user = $this->userService->findByEmail($email);

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

            $this->userService->updateLogoutTimestamps($user);
            
            return response()->json(['message' => 'Successfully logged out'], 200);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Failed to logout, please try again.'], 500);
        }
    }

    // Getting the authenticated user's details
    public function me(Request $request)
    {
        $user = Auth::user();
        return response()->json($user);
    }

    //Master Verify in case required to bring back the deleted user
    public function masterVerify(Request $request, $id)
    {
        $user = $this->userService->findById($id);
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'User already verified.'], 200);
        }
        
        $this->userService->restoreDeletedUser($user);

        $frontendUrl = config('constants.BASE_URL') . '/verified?token=' . $user->verification_token;
        Mail::html(
            "Please verify your email by clicking this link: <a href=\"$frontendUrl\">$frontendUrl</a>",
            function ($message) use ($user) {
                $message
                    ->to($user->email)
                    ->subject('Verify Your Email');
            }
        );

        return response()->json(['message' => 'Verification email sent to user.']);
    }

    // Exporting the users to a CSV file
    public function exportCSV(Request $request)
    {
        $params = $request->only(['search', 'post', 'sort', 'order']);
        $users = $this->userService->getAllUsersForExport($params);

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
        $users = $this->userService->getActiveUsersForDropdown();
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