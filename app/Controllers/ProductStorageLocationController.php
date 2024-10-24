<?php

namespace App\Controllers;

use App\Models\ProductInventory;
use App\Models\ProductStorageLocation;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Model;

class ProductStorageLocationController
{
    use PaginationTrait;

    public function getProductStorageLocations(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $productStorageLocation = ProductStorageLocation::query()
            ->where('deleted', false)
            ->with(['product', 'storageArea']);

        if (isset($_GET['quantity'])) {
            $quantity = urldecode($_GET['quantity']);
            $productStorageLocation->where('quantity', $quantity);
        }

        return $this->paginateResults($productStorageLocation, $perPage, $page)->toArray();
    }

    public function getProductStorageLocationById($id): false|string
    {
        $productStorageLocation = ProductStorageLocation::query()->where('id', $id)
            ->with(['product', 'storageArea'])
            ->first();

        if (!$productStorageLocation) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($productStorageLocation->toArray());
    }

    public function getProductsByProductStorageLocation($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $productStorageLocation = ProductStorageLocation::query()->where('id', $id)->firstOrFail();
        $productsQuery = $productStorageLocation->product()->getQuery();

        return $this->paginateResults($productsQuery, $perPage, $page)->toArray();
    }

    public function getStorageAreasByProductStorageLocation($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $productStorageLocation = ProductStorageLocation::query()->where('id', $id)->firstOrFail();
        $storageAreasQuery = $productStorageLocation->storageArea()
            ->with(['productStorageLocations', 'materialStorageLocations', 'inventoryChecks', 'inventoryHistory'])
            ->getQuery();

        return $this->paginateResults($storageAreasQuery, $perPage, $page)->toArray();
    }

    public function createProductStorageLocation(): Model|string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $productStorageLocation = new ProductInventory();
        $error = $productStorageLocation->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $productStorageLocation->fill($data);
        $productStorageLocation->save();
        return $productStorageLocation;
    }

    public function updateProductStorageLocationById($id): bool|int|string
    {
        $productStorageLocation = ProductInventory::find($id);

        if (!$productStorageLocation) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $productStorageLocation->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $productStorageLocation->fill($data);
        $productStorageLocation->save();

        return $productStorageLocation;
    }

    public function deleteProductStorageLocation($id)
    {
        $productStorageLocation = ProductInventory::find($id);

        if ($productStorageLocation) {
            $productStorageLocation->status = 'DISABLE';
            $productStorageLocation->save();
            return "Xóa thành công";
        } else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}

