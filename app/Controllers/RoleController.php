<?php

namespace App\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class RoleController
{
    public function getRoles(): Collection
    {
        $role = Role::query()->where('status', '!=' , 'DELETED');

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $role->where('status', $status);
        }

        if (isset($_GET['name'])) {
            $name = urldecode($_GET['name']);
            $role->where('name', 'like', '%' . $name . '%');
        }

        return $role->get();
    }

    public function getRoleById($id) : Model
    {
        $role = Role::query()->where('id',$id)->first();
        return $role;
    }

    public function getUserByRole($id) : ?Collection
    {
        $role = Role::query()->where('id', $id)->first();

        if ($role) {
            return $role->users()->get();
        } else {
            return null;
        }
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