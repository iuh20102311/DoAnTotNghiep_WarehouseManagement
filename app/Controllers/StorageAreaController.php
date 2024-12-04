<?php

namespace App\Controllers;

use App\Models\StorageArea;
use App\Models\InventoryCheck;
use App\Utils\PaginationTrait;

class StorageAreaController
{
    use PaginationTrait;

    public function getStorageAreas(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $storage = StorageArea::query()
                ->where('deleted', false)
                ->with(['productStorageHistories', 'materialStorageHistories', 'inventoryChecks'])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
                ->orderBy('created_at', 'desc');

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $storage->where('status', $status);
            }

            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                $storage->where('type', $type);
            }

            if (isset($_GET['name'])) {
                $name = urldecode($_GET['name']);
                $storage->where('name', 'like', '%' . $name . '%');
            }

            if (isset($_GET['code'])) {
                $code = urldecode($_GET['code']);
                $storage->where('code', 'like', '%' . $code . '%');
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $storage->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $storage->where('created_at', '<=', $createdTo);
            }

            return $this->paginateResults($storage, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getStorageAreas: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getStorageAreaByCode($code): array
    {
        try {
            $storage = StorageArea::query()
                ->where('code', $code)
                ->where('deleted', false)
                ->with(['productStorageHistories', 'materialStorageHistories', 'inventoryChecks'])
                ->first();

            if (!$storage) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            if (!$storage) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy khu vực có code: ' . $code
                ];
            }


            return $storage->toArray();

        } catch (\Exception $e) {
            error_log("Error in getStorageAreaById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createStorageArea(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Unset code if provided by user
            if (isset($data['code'])) {
                unset($data['code']);
            }

            $storage = new StorageArea();
            $errors = $storage->validate($data);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            // Get latest storage area code
            $latestStorage = StorageArea::query()
                ->where('code', 'LIKE', 'KVLT%')
                ->orderBy('code', 'desc')
                ->first();

            if ($latestStorage) {
                $sequence = intval(substr($latestStorage->code, -5)) + 1;
            } else {
                $sequence = 1;
            }

            $data['code'] = 'KVLT' . str_pad($sequence, 5, '0', STR_PAD_LEFT);

            $storage->fill($data);
            $storage->save();

            return [
                'success' => true,
                'data' => $storage->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createStorageArea: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateStorageAreaById($id): array
    {
        try {
            $storage = StorageArea::where('deleted', false)->find($id);

            if (!$storage) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Remove code from update data to prevent modification
            if (isset($data['code'])) {
                unset($data['code']);
            }

            $errors = $storage->validate($data, true);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $storage->fill($data);
            $storage->save();

            return [
                'success' => true,
                'data' => $storage->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateStorageAreaById: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteStorageArea($id): array
    {
        try {
            $storage = StorageArea::where('deleted', false)->find($id);

            if (!$storage) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            // Kiểm tra xem có sản phẩm hoặc vật liệu trong kho không
            if ($storage->productStorageHistories()->where('deleted', false)->exists() ||
                $storage->materialStorageHistories()->where('deleted', false)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Không thể xóa khu vực kho đang chứa sản phẩm hoặc vật liệu'
                ];
            }

            $storage->deleted = true;
            $storage->save();

            return [
                'success' => true,
                'message' => 'Xóa khu vực kho thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteStorageArea: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductStorageHistoryByStorageArea($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $storage = StorageArea::where('deleted', false)->find($id);

            if (!$storage) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            $query = $storage->productStorageHistories()
                ->where('deleted', false)
                ->with(['product'])
                ->getQuery();

            if (isset($_GET['quantity_min'])) {
                $minQuantity = urldecode($_GET['quantity_min']);
                $query->where('quantity', '>=', $minQuantity);
            }

            if (isset($_GET['quantity_max'])) {
                $maxQuantity = urldecode($_GET['quantity_max']);
                $query->where('quantity', '<=', $maxQuantity);
            }

            return $this->paginateResults($query, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProductStorageHistoryByStorageArea: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialStorageHistoryByStorageArea($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $storage = StorageArea::where('deleted', false)->find($id);

            if (!$storage) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            $query = $storage->materialStorageHistories()
                ->where('deleted', false)
                ->with(['material', 'provider'])
                ->getQuery();

            if (isset($_GET['quantity_min'])) {
                $minQuantity = urldecode($_GET['quantity_min']);
                $query->where('quantity', '>=', $minQuantity);
            }

            if (isset($_GET['quantity_max'])) {
                $maxQuantity = urldecode($_GET['quantity_max']);
                $query->where('quantity', '<=', $maxQuantity);
            }

            return $this->paginateResults($query, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialStorageHistoryByStorageArea: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryChecksByStorageArea($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $storage = StorageArea::where('deleted', false)->find($id);

            if (!$storage) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            $query = $storage->inventoryChecks()
                ->where('deleted', false)
                ->with(['creator', 'details'])
                ->getQuery();

            if (isset($_GET['check_date_from'])) {
                $dateFrom = urldecode($_GET['check_date_from']);
                $query->where('check_date', '>=', $dateFrom);
            }

            if (isset($_GET['check_date_to'])) {
                $dateTo = urldecode($_GET['check_date_to']);
                $query->where('check_date', '<=', $dateTo);
            }

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $query->where('status', $status);
            }

            return $this->paginateResults($query, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getInventoryChecksByStorageArea: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addInventoryCheckToStorageArea($id): array
    {
        try {
            $storage = StorageArea::where('deleted', false)->find($id);

            if (!$storage) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $data['storage_area_id'] = $id;

            $inventoryCheck = new InventoryCheck();
            $errors = $inventoryCheck->validate($data);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $inventoryCheck->fill($data);
            $inventoryCheck->save();

            return [
                'success' => true,
                'message' => 'Thêm phiếu kiểm kê thành công',
                'data' => $inventoryCheck->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in addInventoryCheckToStorageArea: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getStorageContents(): array
    {
        try {
            $storages = StorageArea::query()
                ->where('deleted', false)
                ->with([
                    'productStorageHistories.product:id,name,sku',
                    'materialStorageHistories.material:id,name,sku'
                ])
                ->get();

            return $storages->toArray();

        } catch (\Exception $e) {
            error_log("Error getting storage contents: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getStorageContentByCode(string $code): array
    {
        try {
            $storage = StorageArea::query()
                ->where('code', $code)
                ->where('deleted', false)
                ->with([
                    'productStorageHistories' => function($query) {
                        $query->where('quantity_available', '>', 0)
                            ->where('deleted', false)
                            ->where('status', 'ACTIVE')
                            ->with('product:id,name,sku,packing,unit,weight');
                    },
                    'materialStorageHistories' => function($query) {
                        $query->where('quantity', '>', 0)
                            ->where('deleted', false)
                            ->with('material:id,name,sku');
                    }
                ])
                ->first();

            if (!$storage) {
                http_response_code(404);
                return [
                    'error' => 'Storage not found',
                    'message' => "Storage with code '{$code}' does not exist"
                ];
            }

            return $storage->toArray();

        } catch (\Exception $e) {
            error_log("Error getting storage contents for code {$code}: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}