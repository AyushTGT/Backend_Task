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
}