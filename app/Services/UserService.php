<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function findByEmail($email)
    {
        return User::where('email', $email)->first();
    }

    public function findById($id)
    {
        return User::find($id);
    }

    public function checkEmailExists($email)
    {
        $user = $this->findByEmail($email);
        return [
            'exists' => (bool) $user,
            'verified' => $user && $user->email_verified_at !== null,
        ];
    }

    public function findUsers($params = [])
    {
        $query = User::query();

        if (!empty($params['search'])) {
            $query->where(function($q) use ($params) {
                $q->where('name', 'like', "{$params['search']}%")
                  ->orWhere('email', 'like', "{$params['search']}%");
            });
        }

        if (!empty($params['post'])) {
            $query->where('post', $params['post']);
        }

        $sortField = $params['sort'] ?? 'name';
        $sortOrder = $params['order'] ?? 'asc';
        $query->orderBy($sortField, $sortOrder);

        return $query;
    }

    public function createUser($data)
    {
        return User::create($data);
    }

    public function updateUser($user, $data)
    {
        foreach ($data as $key => $value) {
            $user->$key = $value;
        }
        $user->save();
        return $user;
    }

    public function softDeleteUser($user, $deletedBy)
    {
        $user->email_verified_at = null;
        $user->deleted_by = $deletedBy;
        $user->save();
        return $user;
    }

    public function findUsersByIds($ids)
    {
        return User::whereIn('id', $ids)->get();
    }

    public function bulkUpdatePost($users, $post)
    {
        foreach ($users as $user) {
            $user->post = $post;
            $user->save();
        }
    }

    public function findByVerificationToken($token)
    {
        return User::where('verification_token', $token)->first();
    }

    public function verifyUser($user)
    {
        $user->email_verified_at = Carbon::now();
        $user->verification_token = null;
        $user->save();
        return $user;
    }

    public function setVerificationToken($user, $token = null)
    {
        $user->verification_token = $token ?? Str::random(60);
        $user->save();
        return $user;
    }

    public function updatePassword($user, $password)
    {
        $user->password = app('hash')->make($password);
        $user->save();
        return $user;
    }

    public function updateLogoutTimestamps($user)
    {
        $user->last_logout = Carbon::now();
        if ($user->total_duration_loggedin === null) {
            $user->total_duration_loggedin = 0;
        }
        $user->total_duration_loggedin += Carbon::now()->diffInSeconds($user->last_login);
        $user->save();
        return $user;
    }

    public function restoreDeletedUser($user)
    {
        $user->deleted_by = null;
        $user->save();
        return $user;
    }

    public function getActiveUsersForDropdown()
    {
        return User::select('id', 'name')->whereNull('deleted_by')->get();
    }

    public function getAllUsersForExport($params)
    {
        return $this->findUsers($params)->get();
    }
}