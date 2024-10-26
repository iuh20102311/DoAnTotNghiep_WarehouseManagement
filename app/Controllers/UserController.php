<?php

namespace App\Controllers;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Utils\PaginationTrait;
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
    use PaginationTrait;

    public function getUsers(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        try {
            $query = User::query()->where('deleted', false)
                ->with(['orders','role','profile','createdInventoryChecks','inventoryHistory']);

            if (isset($_GET['email'])) {
                $email = urldecode($_GET['email']);
                $query->where('email', 'like', '%' . $email . '%');
            }

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $query->where('status', 'like', '%' . $status . '%');
            }

            return $this->paginateResults($query, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            error_log("Error in getUsers: " . $e->getMessage());
            return ['error' => 'Database error occurred', 'details' => $e->getMessage()];
        }
    }

    public function getUserById($id): false|string
    {
        $user = User::query()->where('id', $id)
            ->with(['orders','role','profile','createdInventoryChecks','inventoryHistory'])
            ->first();

        if (!$user) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($user->toArray());
    }

    public function getRolesByUser($id) : array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $user = User::query()->where('id', $id)->firstOrFail();
        $rolesQuery = $user->role()
            ->with(['users'])
            ->getQuery();

        return $this->paginateResults($rolesQuery, $perPage, $page)->toArray();
    }

    public function getOrdersByUser($id) : array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $user = User::query()->where('id', $id)->firstOrFail();
        $ordersQuery = $user->orders()
            ->with(['users'])
            ->getQuery();

        return $this->paginateResults($ordersQuery, $perPage, $page)->toArray();
    }

    public function getProfileByUser($id) : array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $user = User::query()->where('id', $id)->firstOrFail();
        $profilesQuery = $user->profile()
            ->with(['user','createdOrders'])
            ->getQuery();

        return $this->paginateResults($profilesQuery, $perPage, $page)->toArray();
    }

    public function getInventoryHistoryByUser($id) : array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $user = User::query()->where('id', $id)->firstOrFail();
        $inventoryHistoryQuery = $user->inventoryHistory()
            ->with(['storageArea','product','material'])
            ->getQuery();

        return $this->paginateResults($inventoryHistoryQuery, $perPage, $page)->toArray();
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