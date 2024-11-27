<?php

namespace App\Controllers;

use App\Models\MaterialStorageHistory;
use App\Models\StorageArea;
use App\Utils\PaginationTrait;

class MaterialStorageHistoryController
{
    use PaginationTrait;

    public function getMaterialStorageHistory(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialStorageHistory = MaterialStorageHistory::query()
                ->where('deleted', false)
                ->with(['material', 'provider', 'storageArea'])
                ->orderBy('created_at', 'desc');

            // Search by specific date
            if (isset($_GET['date'])) {
                $date = urldecode($_GET['date']);
                $materialStorageHistory->whereDate('created_at', $date);
            }

            // General search for material name or SKU
            if (isset($_GET['search'])) {
                $search = urldecode($_GET['search']);
                $materialStorageHistory->whereHas('storageArea', function($query) use ($search) {
                    $query->where('code', 'LIKE', '%' . $search . '%')
                        ->orWhere('name', 'LIKE', '%' . $search . '%');
                });
            }

            // Material name/SKU specific search
            if (isset($_GET['material_search'])) {
                $materialSearch = urldecode($_GET['material_search']);
                $materialStorageHistory->whereHas('material', function($query) use ($materialSearch) {
                    $query->where('name', 'LIKE', '%' . $materialSearch . '%')
                        ->orWhere('sku', 'LIKE', '%' . $materialSearch . '%');
                });
            }

            // Rest of your existing filters...
            if (isset($_GET['material_id'])) {
                $materialId = urldecode($_GET['material_id']);
                $materialStorageHistory->where('material_id', $materialId);
            }

            if (isset($_GET['provider_id'])) {
                $providerId = urldecode($_GET['provider_id']);
                $materialStorageHistory->where('provider_id', $providerId);
            }

            if (isset($_GET['storage_area_id'])) {
                $storageAreaId = urldecode($_GET['storage_area_id']);
                $materialStorageHistory->where('storage_area_id', $storageAreaId);
            }

            // Quantity filters
            if (isset($_GET['quantity'])) {
                $quantity = urldecode($_GET['quantity']);
                $materialStorageHistory->where('quantity', $quantity);
            }
            if (isset($_GET['quantity_min'])) {
                $quantityMin = urldecode($_GET['quantity_min']);
                $materialStorageHistory->where('quantity', '>=', $quantityMin);
            }
            if (isset($_GET['quantity_max'])) {
                $quantityMax = urldecode($_GET['quantity_max']);
                $materialStorageHistory->where('quantity', '<=', $quantityMax);
            }

            // Quantity Available filters
            if (isset($_GET['quantity_available'])) {
                $quantityAvailable = urldecode($_GET['quantity_available']);
                $materialStorageHistory->where('quantity_available', $quantityAvailable);
            }
            if (isset($_GET['quantity_available_min'])) {
                $quantityAvailableMin = urldecode($_GET['quantity_available_min']);
                $materialStorageHistory->where('quantity_available', '>=', $quantityAvailableMin);
            }
            if (isset($_GET['quantity_available_max'])) {
                $quantityAvailableMax = urldecode($_GET['quantity_available_max']);
                $materialStorageHistory->where('quantity_available', '<=', $quantityAvailableMax);
            }

            // Expiry Date filters
            if (isset($_GET['expiry_date'])) {
                $expiryDate = urldecode($_GET['expiry_date']);
                $materialStorageHistory->whereDate('expiry_date', $expiryDate);
            }
            if (isset($_GET['expiry_date_from'])) {
                $expiryDateFrom = urldecode($_GET['expiry_date_from']);
                $materialStorageHistory->whereDate('expiry_date', '>=', $expiryDateFrom);
            }
            if (isset($_GET['expiry_date_to'])) {
                $expiryDateTo = urldecode($_GET['expiry_date_to']);
                $materialStorageHistory->whereDate('expiry_date', '<=', $expiryDateTo);
            }

            // Status filter
            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $materialStorageHistory->where('status', $status);
            }

            // Created At filters
            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $materialStorageHistory->whereDate('created_at', '>=', $createdFrom);
            }
            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $materialStorageHistory->whereDate('created_at', '<=', $createdTo);
            }

            // Updated At filters
            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $materialStorageHistory->where('updated_at', '>=', $updatedFrom);
            }
            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $materialStorageHistory->where('updated_at', '<=', $updatedTo);
            }

            return $this->paginateResults($materialStorageHistory, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialStorageHistory: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialStorageHistoryById($id): array
    {
        try {
            $materialStorageHistory = MaterialStorageHistory::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->with(['material', 'provider', 'storageArea'])
                ->first();

            if (!$materialStorageHistory) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            return $materialStorageHistory->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialStorageHistoryById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialByMaterialStorageHistory($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialStorageHistory = MaterialStorageHistory::where('deleted', false)->find($id);

            if (!$materialStorageHistory) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            $materialsDetailsQuery = $materialStorageHistory->material()
                ->with(['categories', 'providers', 'storageHistories', 'exportReceiptDetails', 'importReceiptDetails', 'inventoryCheckDetails', 'inventoryHistory'])
                ->getQuery();

            $result = $this->paginateResults($materialsDetailsQuery, $perPage, $page);
            return $result->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialByMaterialStorageHistory: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProvidersByMaterialStorageHistory($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialStorageHistory = MaterialStorageHistory::where('deleted', false)->find($id);

            if (!$materialStorageHistory) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            $providersDetailsQuery = $materialStorageHistory->provider()
                ->with(['materials', 'materialImportReceipts'])
                ->getQuery();

            $result = $this->paginateResults($providersDetailsQuery, $perPage, $page);
            return $result->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProvidersByMaterialStorageHistory: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getStorageAreaByMaterialStorageHistory($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialStorageHistory = MaterialStorageHistory::where('deleted', false)->find($id);

            if (!$materialStorageHistory) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            $materialsDetailsQuery = $materialStorageHistory->storageArea()
                ->with(['productStorageHistories', 'materialStorageHistories', 'inventoryChecks', 'inventoryHistory'])
                ->getQuery();

            $result = $this->paginateResults($materialsDetailsQuery, $perPage, $page);
            return $result->toArray();

        } catch (\Exception $e) {
            error_log("Error in getStorageAreaByMaterialStorageHistory: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createMaterialStorageHistory(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Kiểm tra xem area có loại là "MATERIAL" hay không
            $storageArea = StorageArea::find($data['storage_area_id']);
            if (!$storageArea || $storageArea->type !== 'MATERIAL') {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Chỉ có thể tạo lịch sử lưu trữ vật liệu cho khu vực có loại là MATERIAL'
                ];
            }

            $materialStorageHistory = new MaterialStorageHistory();
            $errors = $materialStorageHistory->validate($data);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $materialStorageHistory->fill($data);
            $materialStorageHistory->save();

            return [
                'success' => true,
                'data' => $materialStorageHistory->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createMaterialStorageHistory: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateMaterialStorageHistoryById($id): array
    {
        try {
            $materialStorageHistory = MaterialStorageHistory::where('deleted', false)->find($id);

            if (!$materialStorageHistory) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Kiểm tra xem area có loại là "MATERIAL" hay không
            $storageArea = StorageArea::find($data['storage_area_id'] ?? $materialStorageHistory->storage_area_id);
            if (!$storageArea || $storageArea->type !== 'MATERIAL') {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Chỉ có thể cập nhật lịch sử lưu trữ vật liệu cho khu vực có loại là MATERIAL'
                ];
            }

            $errors = $materialStorageHistory->validate($data, true);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $materialStorageHistory->fill($data);
            $materialStorageHistory->save();

            return [
                'success' => true,
                'data' => $materialStorageHistory->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateMaterialStorageHistoryById: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteMaterialStorageHistory($id): array
    {
        try {
            $materialStorageHistory = MaterialStorageHistory::where('deleted', false)->find($id);

            if (!$materialStorageHistory) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            $materialStorageHistory->deleted = true;
            $materialStorageHistory->save();

            return [
                'success' => true,
                'message' => 'Xóa vị trí lưu trữ vật liệu thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteMaterialStorageHistory: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}