<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AuthService
{
    public function loginService($email, $password)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return ['error' => 'User not found', 'code' => 404];
        }
        if (is_null($user->email_verified_at)) {
            return ['error' => 'Email not verified', 'code' => 403];
        }
        if ($user->deleted_by !== null)
        {
            return ['error' => 'User account is deleted', 'code' => 403];
        }
        if (!Hash::check($password, $user->password)) {
            return ['error' => 'Invalid credentials', 'code' => 401];
        }
        return ['user' => $user];
    }

    public function updateLoginTimestamps(User $user)
    {
        $user->last_login = Carbon::now();
        $user->last_logout = null;
        $user->save();
    }

    public function updateLogoutTimestamps(User $user)
    {
        $user->last_logout = Carbon::now();
        if($user->total_duration_loggedin === null){
            $user->total_duration_loggedin = 0;
        }
        $user->total_duration_loggedin += Carbon::now()->diffInHours($user->last_login);
        $user->save();
    }
}