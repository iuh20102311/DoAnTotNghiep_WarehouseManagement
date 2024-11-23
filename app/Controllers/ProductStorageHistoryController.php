<?php

namespace App\Controllers;

use App\Models\ProductStorageHistory;
use App\Models\StorageArea;
use App\Utils\PaginationTrait;

class ProductStorageHistoryController
{
    use PaginationTrait;

    public function getProductStorageHistory(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $productStorageHistory = ProductStorageHistory::query()
                ->where('deleted', false)
                ->with(['product', 'storageArea'])
                ->orderBy('created_at', 'desc');

            // Product ID filter
            if (isset($_GET['product_id'])) {
                $productId = urldecode($_GET['product_id']);
                $productStorageHistory->where('product_id', $productId);
            }

            // Storage Area ID filter
            if (isset($_GET['storage_area_id'])) {
                $storageAreaId = urldecode($_GET['storage_area_id']);
                $productStorageHistory->where('storage_area_id', $storageAreaId);
            }

            // Quantity filters
            if (isset($_GET['quantity'])) {
                $quantity = urldecode($_GET['quantity']);
                $productStorageHistory->where('quantity', $quantity);
            }
            if (isset($_GET['quantity_min'])) {
                $quantityMin = urldecode($_GET['quantity_min']);
                $productStorageHistory->where('quantity', '>=', $quantityMin);
            }
            if (isset($_GET['quantity_max'])) {
                $quantityMax = urldecode($_GET['quantity_max']);
                $productStorageHistory->where('quantity', '<=', $quantityMax);
            }

            // Quantity Available filters
            if (isset($_GET['quantity_available'])) {
                $quantityAvailable = urldecode($_GET['quantity_available']);
                $productStorageHistory->where('quantity_available', $quantityAvailable);
            }
            if (isset($_GET['quantity_available_min'])) {
                $quantityAvailableMin = urldecode($_GET['quantity_available_min']);
                $productStorageHistory->where('quantity_available', '>=', $quantityAvailableMin);
            }
            if (isset($_GET['quantity_available_max'])) {
                $quantityAvailableMax = urldecode($_GET['quantity_available_max']);
                $productStorageHistory->where('quantity_available', '<=', $quantityAvailableMax);
            }

            // Expiry Date filters
            if (isset($_GET['expiry_date'])) {
                $expiryDate = urldecode($_GET['expiry_date']);
                $productStorageHistory->whereDate('expiry_date', $expiryDate);
            }
            if (isset($_GET['expiry_date_from'])) {
                $expiryDateFrom = urldecode($_GET['expiry_date_from']);
                $productStorageHistory->whereDate('expiry_date', '>=', $expiryDateFrom);
            }
            if (isset($_GET['expiry_date_to'])) {
                $expiryDateTo = urldecode($_GET['expiry_date_to']);
                $productStorageHistory->whereDate('expiry_date', '<=', $expiryDateTo);
            }

            // Status filter
            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $productStorageHistory->where('status', $status);
            }

            // Created At filters
            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $productStorageHistory->where('created_at', '>=', $createdFrom);
            }
            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $productStorageHistory->where('created_at', '<=', $createdTo);
            }

            // Updated At filters
            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $productStorageHistory->where('updated_at', '>=', $updatedFrom);
            }
            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $productStorageHistory->where('updated_at', '<=', $updatedTo);
            }

            return $this->paginateResults($productStorageHistory, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProductStorageHistory: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductStorageHistoryById($id): array
    {
        try {
            $productStorageHistory = ProductStorageHistory::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->with(['product', 'storageArea'])
                ->first();

            if (!$productStorageHistory) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ sản phẩm'
                ];
            }

            return $productStorageHistory->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProductStorageHistoryById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductsByProductStorageHistory($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $productStorageHistory = ProductStorageHistory::where('deleted', false)->find($id);

            if (!$productStorageHistory) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ sản phẩm'
                ];
            }

            $productsQuery = $productStorageHistory->product()
                ->with(['categories', 'prices'])
                ->getQuery();

            return $this->paginateResults($productsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProductsByProductStorageHistory: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getStorageAreasByProductStorageHistory($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $productStorageHistory = ProductStorageHistory::where('deleted', false)->find($id);

            if (!$productStorageHistory) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ sản phẩm'
                ];
            }

            $storageAreasQuery = $productStorageHistory->storageArea()
                ->with(['productStorageHistories', 'materialStorageHistories', 'inventoryChecks', 'inventoryHistory'])
                ->getQuery();

            return $this->paginateResults($storageAreasQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getStorageAreasByProductStorageHistory: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createProductStorageHistory(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Kiểm tra xem area có loại là "PRODUCT" hay không
            $storageArea = StorageArea::find($data['storage_area_id']);
            if (!$storageArea || $storageArea->type !== 'PRODUCT') {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Chỉ có thể tạo lịch sử lưu trữ sản phẩm cho khu vực có loại là PRODUCT'
                ];
            }

            $productStorageHistory = new ProductStorageHistory();
            $errors = $productStorageHistory->validate($data);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $productStorageHistory->fill($data);
            $productStorageHistory->save();

            return [
                'success' => true,
                'data' => $productStorageHistory->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createProductStorageHistory: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProductStorageHistoryById($id): array
    {
        try {
            $productStorageHistory = ProductStorageHistory::where('deleted', false)->find($id);

            if (!$productStorageHistory) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vị trí lưu trữ sản phẩm'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Kiểm tra xem area có loại là "PRODUCT" hay không
            $storageArea = StorageArea::find($data['storage_area_id'] ?? $productStorageHistory->storage_area_id);
            if (!$storageArea || $storageArea->type !== 'PRODUCT') {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Chỉ có thể cập nhật lịch sử lưu trữ sản phẩm cho khu vực có loại là PRODUCT'
                ];
            }

            $errors = $productStorageHistory->validate($data, true);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $productStorageHistory->fill($data);
            $productStorageHistory->save();

            return [
                'success' => true,
                'data' => $productStorageHistory->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateProductStorageHistoryById: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteProductStorageHistory($id): array
    {
        try {
            $productStorageHistory = ProductStorageHistory::where('deleted', false)->find($id);

            if (!$productStorageHistory) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vị trí lưu trữ sản phẩm'
                ];
            }

            $productStorageHistory->deleted = true;
            $productStorageHistory->save();

            return [
                'success' => true,
                'message' => 'Xóa vị trí lưu trữ sản phẩm thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteProductStorageHistory: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}