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

    public function countMaterials(): array
    {
        try {
            $total = Material::where('status', 'IN_STOCK')
                ->where('deleted', false)
                ->count();

            return [
                'success' => true,
                'data' => ['total' => $total]
            ];

        } catch (\Exception $e) {
            error_log("Error in countMaterials: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterials(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $material = Material::query()
                ->where('deleted', false)
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

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $material->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $material->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $material->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $material->where('updated_at', '<=', $updatedTo);
            }

             return $this->paginateResults($material, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterials: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }


    public function getMaterialById($id): array
    {
        try {
            $material = Material::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->with(['categories', 'providers'])
                ->first();

            if (!$material) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            return $material->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialById: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createMaterial(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $material = new Material();
            $errors = $material->validate($data);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $material->fill($data);
            $material->save();

            return [
                'success' => true,
                'data' => $material->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createMaterial: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateMaterialById($id): array
    {
        try {
            $material = Material::where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $material->validate($data, true);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $material->fill($data);
            $material->save();

            return [
                'success' => true,
                'data' => $material->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateMaterial: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteMaterial($id): array
    {
        try {
            $material = Material::where('deleted', false)->where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            if ($material->status == 'ACTIVE') {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Không thể xóa khách hàng đang ở trạng thái Active'
                ];
            }

            $material->deleted = true;
            $material->save();

            return [
                'success' => true,
                'message' => 'Xóa vật liệu thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteMaterial: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProviderByMaterial($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $material = Material::where('deleted', false)->where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $providersQuery = $material->providers()
                ->where('deleted', false)
                ->with('materials')
                ->getQuery();

            return $this->paginateResults($providersQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProviderByMaterial: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addProviderToMaterial($id): array
    {
        try {
            $material = Material::where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $provider = Provider::where('deleted', false)->find($data['provider_id']);

            if (!$provider) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhà cung cấp'
                ];
            }

            if ($material->providers()->where('provider_id', $provider->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Nhà cung cấp đã tồn tại cho vật liệu này'
                ];
            }

            $material->providers()->attach($provider);
            return [
                'success' => true,
                'message' => 'Thêm nhà cung cấp thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in addProviderToMaterial: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProviderInMaterial($id): array
    {
        try {
            $material = Material::where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['old_provider_id']) || !isset($data['new_provider_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin nhà cung cấp cũ hoặc mới'
                ];
            }

            $oldProvider = Provider::where('deleted', false)->find($data['old_provider_id']);
            $newProvider = Provider::where('deleted', false)->find($data['new_provider_id']);

            if (!$oldProvider || !$newProvider) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhà cung cấp cũ hoặc mới'
                ];
            }

            if (!$material->providers()->where('provider_id', $oldProvider->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Nhà cung cấp cũ không tồn tại trong danh sách nhà cung cấp của vật liệu này'
                ];
            }

            if ($material->providers()->where('provider_id', $newProvider->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Nhà cung cấp mới đã tồn tại trong danh sách nhà cung cấp của vật liệu này'
                ];
            }

            $material->providers()->detach($oldProvider);
            $material->providers()->attach($newProvider);

            return [
                'success' => true,
                'message' => 'Cập nhật nhà cung cấp thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in updateProviderInMaterial: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeProviderFromMaterial($id): array
    {
        try {
            $material = Material::where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['provider_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin nhà cung cấp'
                ];
            }

            $provider = Provider::where('deleted', false)->find($data['provider_id']);

            if (!$provider) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhà cung cấp'
                ];
            }

            if (!$material->providers()->where('provider_id', $provider->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Nhà cung cấp không tồn tại trong danh sách nhà cung cấp của vật liệu này'
                ];
            }

            $material->providers()->detach($provider);

            return [
                'success' => true,
                'message' => 'Xóa nhà cung cấp khỏi vật liệu thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removeProviderFromMaterial: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCategoryByMaterial($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $material = Material::where('deleted', false)->where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $categoriesQuery = $material->categories()
                ->where('deleted', false)
                ->with('materials')
                ->getQuery();

            return $this->paginateResults($categoriesQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getCategoryByMaterial: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addCategoryToMaterial($id): array
    {
        try {
            $material = Material::where('deleted', false)->where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $category = Category::where('deleted', false)
                ->find($data['category_id']);

            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            if ($material->categories()->where('category_id', $category->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Danh mục đã tồn tại cho vật liệu này'
                ];
            }

            $material->categories()->attach($category);
            return [
                'success' => true,
                'message' => 'Thêm danh mục thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in addCategoryToMaterial: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateCategoryInMaterial($id): array
    {
        try {
            $material = Material::where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['old_category_id']) || !isset($data['new_category_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin danh mục cũ hoặc mới'
                ];
            }

            $oldCategory = Category::where('deleted', false)->find($data['old_category_id']);
            $newCategory = Category::where('deleted', false)->find($data['new_category_id']);

            if (!$oldCategory || !$newCategory) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục cũ hoặc mới'
                ];
            }

            if (!$material->categories()->where('category_id', $oldCategory->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Danh mục cũ không tồn tại trong danh sách danh mục của vật liệu này'
                ];
            }

            if ($material->categories()->where('category_id', $newCategory->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Danh mục mới đã tồn tại trong danh sách danh mục của vật liệu này'
                ];
            }

            $material->categories()->detach($oldCategory);
            $material->categories()->attach($newCategory);

            return [
                'success' => true,
                'message' => 'Cập nhật danh mục thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in updateCategoryInMaterial: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeCategoryFromMaterial($id): array
    {
        try {
            $material = Material::where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['category_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin danh mục'
                ];
            }

            $category = Category::where('deleted', false)->find($data['category_id']);

            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            if (!$material->categories()->where('category_id', $category->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Danh mục không tồn tại trong danh sách danh mục của vật liệu này'
                ];
            }

            $material->categories()->detach($category);

            return [
                'success' => true,
                'message' => 'Xóa danh mục khỏi vật liệu thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removeCategoryFromMaterial: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getExportReceiptDetailsByMaterial($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $material = Material::where('deleted', false)->where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $exportReceiptDetailsQuery = $material->exportReceiptDetails()
                ->with(['material', 'storageArea', 'materialExportReceipt'])
                ->getQuery();

            return $this->paginateResults($exportReceiptDetailsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getExportReceiptDetailsByMaterial: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getImportReceiptDetailsByMaterial($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $material = Material::where('deleted', false)->where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $importReceiptDetailsQuery = $material->importReceiptDetails()
                ->with(['material', 'storageArea', 'materialImportReceipt'])
                ->getQuery();

            return $this->paginateResults($importReceiptDetailsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getImportReceiptDetailsByMaterial: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialStorageLocationsByMaterial($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $material = Material::where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $materialStorageLocationsQuery = $material->storageLocations()
                ->with(['material', 'storageArea', 'provider'])
                ->getQuery();

            return $this->paginateResults($materialStorageLocationsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialStorageLocationsByMaterial: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryCheckDetailsByMaterial($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $material = Material::where('deleted', false)->where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $inventoryCheckDetailsQuery = $material->inventoryCheckDetails()
                ->with(['material', 'inventoryCheck'])
                ->whereNull('product_id')
                ->getQuery();

            return $this->paginateResults($inventoryCheckDetailsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getInventoryCheckDetailsByMaterial: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryHistoryByMaterial($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $material = Material::where('deleted', false)->where('deleted', false)->find($id);

            if (!$material) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            $inventoryHistoryQuery = $material->inventoryHistory()
                ->with(['material', 'storageArea', 'creator'])
                ->whereNull('product_id')
                ->getQuery();

            return $this->paginateResults($inventoryHistoryQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getInventoryHistoryByMaterial: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}