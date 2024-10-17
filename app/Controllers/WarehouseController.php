<?php

namespace App\Controllers;

use App\Models\StorageArea;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class StorageController
{
    public function getStorages(): Collection
    {
        $storage = StorageArea::query()->where('status', '!=' , 'DELETED');

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $storage->where('status', $status);
        }

        if (isset($_GET['name'])) {
            $name = urldecode($_GET['name']);
            $storage->where('name', 'like', '%' . $name . '%');
        }

        if (isset($_GET['address'])) {
            $address = urldecode($_GET['address']);
            $storage->where('address', 'like', '%' . $address . '%');
        }

        if (isset($_GET['city'])) {
            $city = urldecode($_GET['city']);
            $storage->where('city', 'like', '%' . $city . '%');
        }

        if (isset($_GET['district'])) {
            $district = urldecode($_GET['district']);
            $storage->where('district', 'like', '%' . $district . '%');
        }

        if (isset($_GET['ward'])) {
            $ward = urldecode($_GET['ward']);
            $storage->where('ward', 'like', '%' . $ward . '%');
        }

        return $storage->get();
    }

    public function getStorageById($id) : Model
    {
        return StorageArea::query()->where('id',$id)->first();
    }

    public function getProductInventoryByStorage($id) : ?Collection
    {
        $storage = StorageArea::query()->where('id', $id)->first();
        return $storage->inventories;
    }

    public function createStorage(): Model | string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $storage = new StorageArea();
        $error = $storage->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $storage->fill($data);
        $storage->save();
        return $storage;
    }

    public function updateStorageById($id): bool | int | string
    {
        $storage = StorageArea::find($id);

        if (!$storage) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $storage->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $storage->fill($data);
        $storage->save();

        return $storage;
    }

    public function deleteStorage($id)
    {
        $storage = StorageArea::find($id);

        if ($storage) {
            $storage->status = 'DELETED';
            $storage->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}