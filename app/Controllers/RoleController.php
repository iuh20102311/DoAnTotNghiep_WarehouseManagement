<?php

namespace App\Controllers;

use App\Models\Role;
use App\Utils\PaginationTrait;

class RoleController
{
    use PaginationTrait;

    public function getRoles(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $role = Role::query()
                ->where('deleted', false)
                ->with(['users']);

            // Add all filters
            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $role->where('status', $status);
            }

            if (isset($_GET['name'])) {
                $name = urldecode($_GET['name']);
                $role->where('name', 'like', '%' . $name . '%');
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $role->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $role->where('created_at', '<=', $createdTo);
            }

            return $this->paginateResults($role, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getRoles: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getRoleById($id): array
    {
        try {
            $role = Role::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->with(['users'])
                ->first();

            if (!$role) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vai trò'
                ];
            }

            return $role->toArray();

        } catch (\Exception $e) {
            error_log("Error in getRoleById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getUserByRole($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $role = Role::where('deleted', false)->find($id);

            if (!$role) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vai trò'
                ];
            }

            $usersQuery = $role->users()
                ->where('deleted', false)
                ->with(['profile', 'role', 'createdInventoryChecks'])
                ->getQuery();

            return $this->paginateResults($usersQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getUserByRole: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createRole(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $role = new Role();
            $errors = $role->validate($data);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $role->fill($data);
            $role->save();

            return [
                'success' => true,
                'data' => $role->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createRole: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateRoleById($id): array
    {
        try {
            $role = Role::where('deleted', false)->find($id);

            if (!$role) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vai trò'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $role->validate($data, true);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $role->fill($data);
            $role->save();

            return [
                'success' => true,
                'data' => $role->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateRoleById: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteRole($id): array
    {
        try {
            $role = Role::where('deleted', false)->find($id);

            if (!$role) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vai trò'
                ];
            }

            // Check if role has associated users
            if ($role->users()->where('deleted', false)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Không thể xóa vai trò đang được sử dụng bởi người dùng'
                ];
            }

            $role->deleted = true;
            $role->save();

            return [
                'success' => true,
                'message' => 'Xóa vai trò thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteRole: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}