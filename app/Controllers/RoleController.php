<?php

namespace App\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class RoleController
{
    use PaginationTrait;

    public function getRoles(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $role = Role::query()->where('status', '!=' , 'DELETED')
            ->with(['users']);

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $role->where('status', $status);
        }

        if (isset($_GET['name'])) {
            $name = urldecode($_GET['name']);
            $role->where('name', 'like', '%' . $name . '%');
        }

        return $this->paginateResults($role, $perPage, $page)->toArray();
    }

    public function getRoleById($id) : false|string
    {
        $role = Role::query()->where('id',$id)
            ->with(['users'])
            ->first();

        if (!$role) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($role->toArray());
    }

    public function getUserByRole($id) : array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $role = Role::query()->where('id', $id)->firstOrFail();
        $usersQuery = $role->users()->getQuery();

        return $this->paginateResults($usersQuery, $perPage, $page)->toArray();
    }

    public function createRole(): Model | string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $role = new Role();
        $error = $role->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $role->fill($data);
        $role->save();
        return $role;
    }

    public function updateRoleById($id): bool | int | string
    {
        $role = Role::find($id);

        if (!$role) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $role->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $role->fill($data);
        $role->save();

        return $role;
    }

    public function deleteRole($id)
    {
        $role = Role::find($id);

        if ($role) {
            $role->status = 'DELETED';
            $role->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}