<?php

namespace App\Controllers;

use App\Models\MaterialStorageLocation;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Model;

class MaterialStorageLocationController
{
    use PaginationTrait;

    public function getMaterialStorageLocations() : array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $materialStorageLocation = MaterialStorageLocation::query()->where('deleted', false)
            ->with(['material', 'provider', 'storageArea']);

        if (isset($_GET['quantity'])) {
            $quantity = urldecode($_GET['quantity']);
            $materialStorageLocation->where('quantity', $quantity);
        }

        return $this->paginateResults($materialStorageLocation, $perPage, $page)->toArray();
    }

    public function getMaterialStorageLocationById($id) : false|string
    {
        $materialStorageLocation = MaterialStorageLocation::query()->where('id', $id)
            ->with(['material', 'provider', 'storageArea'])
            ->first();

        if (!$materialStorageLocation) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($materialStorageLocation->toArray());
    }

    public function getMaterialByMaterialStorageLocation($id)
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $materialStorageLocation = MaterialStorageLocation::query()->where('id', $id)->firstOrFail();
        $materialsDetailsQuery = $materialStorageLocation->material()
            ->with(['categories', 'providers', 'storageLocations','exportReceiptDetails','importReceiptDetails','inventoryCheckDetails','inventoryHistory'])
            ->getQuery();

        return $this->paginateResults($materialsDetailsQuery, $perPage, $page)->toArray();
    }

    public function getProvidersByMaterialStorageLocation($id)
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $materialStorageLocation = MaterialStorageLocation::query()->where('id', $id)->firstOrFail();
        $providersDetailsQuery = $materialStorageLocation->provider()
            ->with(['materials', 'materialImportReceipts'])
            ->getQuery();

        return $this->paginateResults($providersDetailsQuery, $perPage, $page)->toArray();
    }

    public function getStorageAreaByMaterialStorageLocation($id)
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $materialStorageLocation = MaterialStorageLocation::query()->where('id', $id)->firstOrFail();
        $materialsDetailsQuery = $materialStorageLocation->storageArea()
            ->with(['productStorageLocations', 'materialStorageLocations', 'inventoryChecks','inventoryHistory'])
            ->getQuery();

        return $this->paginateResults($materialsDetailsQuery, $perPage, $page)->toArray();
    }

    public function createMaterialStorageLocation(): Model | string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $materialStorageLocation = new MaterialStorageLocation();
        $error = $materialStorageLocation->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $materialStorageLocation->fill($data);
        $materialStorageLocation->save();
        return $materialStorageLocation;
    }

    public function updateMaterialStorageLocationById($id): bool | int | string
    {
        $materialStorageLocation = MaterialStorageLocation::find($id);

        if (!$materialStorageLocation) {
            http_response_code(404);
            return json_encode(["error" => "Material Storage Location not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $materialStorageLocation->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $materialStorageLocation->fill($data);
        $materialStorageLocation->save();

        return $materialStorageLocation;
    }

    public function deleteMaterialStorageLocation($id)
    {
        $materialStorageLocation = MaterialStorageLocation::find($id);

        if ($materialStorageLocation) {
            $materialStorageLocation->status = 'DISABLE';
            $materialStorageLocation->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}

 