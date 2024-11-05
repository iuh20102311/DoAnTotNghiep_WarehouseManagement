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
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $query = User::query()
                ->where('deleted', false)
                ->with([
                    'orders',
                    'role',
                    'profile',
                    'createdInventoryChecks',
                    'inventoryHistory'
                ]);

            if (isset($_GET['email'])) {
                $email = urldecode($_GET['email']);
                $query->where('email', 'like', '%' . $email . '%');
            }

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $query->where('status', $status);
            }

            if (isset($_GET['role_id'])) {
                $roleId = urldecode($_GET['role_id']);
                $query->where('role_id', $roleId);
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $query->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $query->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['verified'])) {
                $verified = filter_var(urldecode($_GET['verified']), FILTER_VALIDATE_BOOLEAN);
                if ($verified) {
                    $query->whereNotNull('email_verified_at');
                } else {
                    $query->whereNull('email_verified_at');
                }
            }

            return $this->paginateResults($query, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getUsers: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getUserById($id): array
    {
        try {
            $user = User::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->with(['orders', 'role', 'profile', 'createdInventoryChecks', 'inventoryHistory'])
                ->first();

            if (!$user) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy người dùng'
                ];
            }

            return $user->toArray();

        } catch (\Exception $e) {
            error_log("Error in getUserById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getRolesByUser($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $user = User::where('deleted', false)->find($id);

            if (!$user) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy người dùng'
                ];
            }

            $query = $user->role()
                ->where('deleted', false)
                ->with(['users'])
                ->getQuery();

            return $this->paginateResults($query, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getRolesByUser: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getOrdersByUser($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $user = User::where('deleted', false)->find($id);

            if (!$user) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy người dùng'
                ];
            }

            $query = $user->orders()
                ->where('deleted', false)
                ->with(['orderDetails', 'customer', 'creator'])
                ->getQuery();

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $query->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $query->where('created_at', '<=', $createdTo);
            }

            return $this->paginateResults($query, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getOrdersByUser: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProfileByUser($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $user = User::where('deleted', false)->find($id);

            if (!$user) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy người dùng'
                ];
            }

            $query = $user->profile()
                ->where('deleted', false)
                ->with(['user', 'createdOrders'])
                ->getQuery();

            return $this->paginateResults($query, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProfileByUser: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryHistoryByUser($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $user = User::where('deleted', false)->find($id);

            if (!$user) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy người dùng'
                ];
            }

            $query = $user->inventoryHistory()
                ->with(['storageArea', 'product', 'material'])
                ->getQuery();

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $query->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $query->where('created_at', '<=', $createdTo);
            }

            return $this->paginateResults($query, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getInventoryHistoryByUser: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateUserById($id): array
    {
        try {
            $user = User::where('deleted', false)->find($id);

            if (!$user) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy người dùng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Không cho phép cập nhật password qua API này
            if (isset($data['password'])) {
                unset($data['password']);
            }

            $errors = $user->validate($data, true);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $user->fill($data);
            $user->save();

            return [
                'success' => true,
                'data' => $user->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateUserById: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteUser($id): array
    {
        try {
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Token không tồn tại'
                ];
            }

            // Kiểm tra cấu trúc chuỗi JWT
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Token không hợp lệ'
                ];
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);

            $currentUserId = $parsedToken->claims()->get('id');

            if (!$currentUserId) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Token không hợp lệ'
                ];
            }

            // Kiểm tra người dùng hiện tại
            $currentUser = User::where('deleted', false)->find($currentUserId);
            if (!$currentUser) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Người dùng không tồn tại'
                ];
            }

            // Kiểm tra quyền SUPER_ADMIN
            $role = Role::where('deleted', false)->find($currentUser->role_id);
            if (!$role || $role->name !== 'SUPER_ADMIN') {
                http_response_code(403);
                return [
                    'success' => false,
                    'error' => 'Không có quyền thực hiện thao tác này'
                ];
            }

            // Kiểm tra user cần xóa
            $userToDelete = User::where('deleted', false)->find($id);
            if (!$userToDelete) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy người dùng cần xóa'
                ];
            }

            // Không cho phép xóa chính mình
            if ($currentUserId == $id) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Không thể tự xóa tài khoản của mình'
                ];
            }

            // Thực hiện xóa mềm user và profile
            $userToDelete->deleted = true;
            $userToDelete->save();

            if ($userToDelete->profile) {
                $userToDelete->profile->deleted = true;
                $userToDelete->profile->save();
            }

            return [
                'success' => true,
                'message' => 'Xóa người dùng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteUser: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Lỗi khi xóa người dùng',
                'details' => $e->getMessage()
            ];
        }
    }
}