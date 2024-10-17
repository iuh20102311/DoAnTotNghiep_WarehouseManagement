<?php

namespace App\Controllers;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;

class UserController
{
    public function getUsers(): Collection
    {
        $users = User::query()->where('status', '!=' , 'DELETED');

        if (isset($_GET['email'])) {
            $email = urldecode($_GET['email']);
            $users->where('email', 'like', '%' . $email . '%');
        }

        if (isset($_GET['name'])) {
            $name = urldecode($_GET['name']);
            $users->where('name', 'like', '%' . $name . '%');

        }

        if (isset($_GET['role_name'])) {
            $roleName = urldecode($_GET['role_name']);
            $users->whereHas('role', function ($query) use ($roleName) {
                $query->where('name', 'like', $roleName . '%');
            });
        }

        $users = $users->get();
        foreach ($users as $index => $user) {
            $role = Role::query()->where('id',$user->role_id)->first();
            unset($user->customer_id);
            unset($user->password);
            unset($user->role_id);
            $user->role = $role;

            $profile = Profile::query()->where('user_id', $user->id)->first();
            $user->profile = $profile;
        }
        return $users;
    }

    public function getUserById($id): ?Model
    {
        $user = User::query()->where('id', $id)->first();
        $role = Role::query()->where('id',$user->role_id)->first();
        if ($user) {
            unset($user->role_id);
            unset($user->password);
            $user->role = $role;
            return $user;
        } else {
            return null;
        }
    }

    public function getInventoryTransactionByUser($id) : ?Collection
    {
        $user = User::query()->where('id', $id)->first();

        if ($user) {
            return $user->inventorytransactions()->get();
        } else {
            return null;
        }
    }

    public function getOrderByUser($id) : ?Collection
    {
        $user = User::query()->where('id', $id)->first();

        if ($user) {
            return $user->orders()->get();
        } else {
            return null;
        }
    }

    public function getProfileByUser($id) : ?Collection
    {
        $user = User::query()->where('id', $id)->first();

        if ($user) {
            return $user->profile()->get();
        } else {
            return null;
        }
    }

    public function updateUserById(int $id): Model | string
    {
        $user = User::find($id);

        if (!$user) {
            http_response_code(404);
            return json_encode(["error" => "User not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        unset($user->password);
        $error = $user->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        foreach ($data as $key => $value) {
            $user->$key = $value;
        }
        $user->save();
        return $user;
    }

    public function deleteUser($id)
    {
        $headers = apache_request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

        if (!$token) {
            http_response_code(400);
            echo json_encode(['error' => 'Token không tồn tại'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Kiểm tra cấu trúc chuỗi JWT
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            http_response_code(400);
            echo json_encode(['error' => 'Token không hợp lệ'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);

            $userId = $parsedToken->claims()->get('id');

            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'Token không hợp lệ'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $currentUser = User::find($userId);
            if (!$currentUser) {
                http_response_code(404);
                echo json_encode(['error' => 'Người dùng không tồn tại'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $role = Role::find($currentUser->role_id);
            error_log($role);
            if ($role && $role->name === 'SUPER_ADMIN') {
                $userToDelete = User::find($id);
                if (!$userToDelete) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found'], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $userToDelete->status = 'DELETED';
                $userToDelete->save();

                if ($userToDelete->profile) {
                    $userToDelete->profile->status = 'DELETED';
                    $userToDelete->profile->save();
                }

                http_response_code(200);
                echo json_encode(['message' => 'User and profile deleted successfully'], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied'], JSON_UNESCAPED_UNICODE);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete user and profile: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}