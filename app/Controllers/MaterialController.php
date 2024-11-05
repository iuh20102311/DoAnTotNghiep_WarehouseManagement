<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\InventoryCheckDetail;
use App\Models\Material;
use App\Models\MaterialExportReceiptDetail;
use App\Models\MaterialImportReceiptDetail;
use App\Models\MaterialStorageLocation;
use App\Models\Provider;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Model;


class MaterialController
{
    use PaginationTrait;

    public function countMaterials(): false|string
    {
        $total = Material::where('status', 'IN_STOCK')->count();
        $result = ['total' => $total];
        return json_encode($result);
    }

    public function getMaterials(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $material = Material::query()
            ->where('deleted',false)
            ->with(['categories', 'providers']);

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $material->where('status', $status);
        }

        if (isset($_GET['name'])) {
            $name = urldecode($_GET['name']);
            $material->where('name', 'like', $name . '%');
        }

        if (isset($_GET['unit'])) {
            $unit = urldecode($_GET['unit']);
            $material->where('unit', 'like', $unit . '%');
        }

        if (isset($_GET['weight'])) {
            $weight = urldecode($_GET['weight']);
            $material->where('weight', $weight);
        }

        if (isset($_GET['weight_min'])) {
            $weight_min = urldecode($_GET['weight_min']);
            $material->where('weight', '>=', $weight_min);
        }

        if (isset($_GET['weight_max'])) {
            $weight_max = urldecode($_GET['weight_max']);
            $material->where('weight', '<=', $weight_max);
        }

        if (isset($_GET['quantity'])) {
            $quantity = urldecode($_GET['quantity']);
            $material->where('quantity', $quantity);
        }

        if (isset($_GET['quantity_min'])) {
            $quantity_min = urldecode($_GET['quantity_min']);
            $material->where('quantity', '>=', $quantity_min);
        }

        if (isset($_GET['quantity_max'])) {
            $quantity_max = urldecode($_GET['quantity_max']);
            $material->where('quantity', '<=', $quantity_max);
        }

        if (isset($_GET['origin'])) {
            $origin = urldecode($_GET['origin']);
            $material->where('origin', 'like', $origin . '%');
        }

        return $this->paginateResults($material, $perPage, $page)->toArray();
    }

    public function getMaterialById($id): string
    {
        $material = Material::query()->where('id', $id)
            ->where('deleted',false)
            ->with(['categories', 'providers'])
            ->first();

        if (!$material) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($material->toArray());
    }

    public function getProviderByMaterial($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $material = Material::findOrFail($id);
        $providersQuery = $material->providers()
            ->with('materials')
            ->getQuery();

        return $this->paginateResults($providersQuery, $perPage, $page)->toArray();
    }

    public function addProviderToMaterial($id): string
    {
        $material = Material::query()->where('id', $id)->first();
        $data = json_decode(file_get_contents('php://input'), true);
        $provider = Provider::query()->where('id', $data['provider_id'])->first();

        if ($material->providers()->where('provider_id', $provider->id)->exists()) {
            return 'Nhà cung cấp đã tồn tại cho vật liệu này';

        }
        $material->providers()->attach($provider);
        return 'Thêm thành công';
    }

    public function getCategoryByMaterial($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $material = Material::findOrFail($id);
        $categoriesQuery = $material->categories()
            ->with('materials')
            ->getQuery();

        return $this->paginateResults($categoriesQuery, $perPage, $page)->toArray();
    }

    public function addCategoryToMaterial($id)
    {
        $material = Material::query()->where('id', $id)->first();
        $data = json_decode(file_get_contents('php://input'), true);
        $category = Category::query()->where('id', $data['category_id'])->first();

        if ($material->categories()->where('category_id', $category->id)->exists()) {
            return 'Danh mục đã tồn tại cho vật liệu này';
        }

        $material->categories()->attach($category);
        return 'Thêm thành công';
    }

    public function getExportReceiptDetailsByMaterial($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $material = Material::findOrFail($id);
        $exportReceiptDetailsQuery = $material->exportReceiptDetails()
            ->with(['material','storageArea','materialExportReceipt'])
            ->getQuery();

        return $this->paginateResults($exportReceiptDetailsQuery, $perPage, $page)->toArray();
    }

    public function getImportReceiptDetailsByMaterial($id)
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $material = Material::findOrFail($id);
        $importReceiptDetailsQuery = $material->importReceiptDetails()
            ->with(['material','storageArea','materialImportReceipt'])
            ->getQuery();

        return $this->paginateResults($importReceiptDetailsQuery, $perPage, $page)->toArray();
    }

    public function getMaterialStorageLocationsByMaterial($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $material = Material::findOrFail($id);
        $materialStorageLocationsQuery = $material->storageLocations()
            ->with(['material','storageArea','provider'])
            ->getQuery();

        return $this->paginateResults($materialStorageLocationsQuery, $perPage, $page)->toArray();
    }

    public function getInventoryCheckDetailsByMaterial($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $material = Material::findOrFail($id);
        $inventoryCheckDetailsQuery = $material->inventoryCheckDetails()
            ->with(['material','inventoryCheck'])
            ->whereNull('product_id')
            ->getQuery();

        return $this->paginateResults($inventoryCheckDetailsQuery, $perPage, $page)->toArray();
    }

    public function getInventoryHistoryByMaterial($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $material = Material::findOrFail($id);
        $inventoryHistoryQuery = $material->inventoryHistory()
            ->with(['material','storageArea','creator'])
            ->whereNull('product_id')
            ->getQuery();

        return $this->paginateResults($inventoryHistoryQuery, $perPage, $page)->toArray();
    }

    public function createMaterial(): Model|string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $material = new Material();
        $error = $material->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $material->fill($data);
        $material->save();
        return $material;
    }

    public function updateMaterialById($id): bool|int|string
    {
        $material = Material::find($id);

        if (!$material) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $material->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $material->fill($data);
        $material->save();

        return $material;
    }

    public function deleteMaterial($id): string
    {
        $material = Material::find($id);

        if ($material) {
            $material->status = 'DELETED';
            $material->save();
            return "Xóa thành công";
        } else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}