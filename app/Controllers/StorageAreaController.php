<?php

namespace App\Controllers;

use App\Models\InventoryCheck;
use App\Models\StorageArea;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class StorageAreaController
{
    use PaginationTrait;

    public function getStorageAreas(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        try {
            $storage = StorageArea::query()->where('deleted', false)
                ->with(['productStorageLocations', 'materialStorageLocations', 'inventoryChecks', 'inventoryHistory']);

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

            return $this->paginateResults($storage, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            error_log("Error in getUsers: " . $e->getMessage());
            return ['error' => 'Database error occurred', 'details' => $e->getMessage()];
        }
    }

    public function getStorageAreaById($id): array
    {
        try {
            $storage = StorageArea::query()->where('id', $id)
                ->with(['productStorageLocations', 'materialStorageLocations', 'inventoryChecks', 'inventoryHistory'])
                ->first();

            if (!$storage) {
                return [
                    
                    'error' => 'Không tìm thấy'
                ];
            }

            return [
                'success' => true,
                'data' => $storage->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductStorageLocationsByStorageArea($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $storage = StorageArea::query()->where('id', $id)->first();

            if (!$storage) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $productStorageLocationsQuery = $storage->productStorageLocations()
                ->with(['product'])
                ->getQuery();

            return $this->paginateResults($productStorageLocationsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProductStorageLocationsByStorageArea: " . $e->getMessage());
            return [
                
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialStorageLocationsByStorageArea($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $storage = StorageArea::query()->where('id', $id)->first();

            if (!$storage) {
                return [
                    
                    'error' => 'Không tìm thấy'
                ];
            }

            $materialStorageLocationsQuery = $storage->materialStorageLocations()
                ->with(['material','provider'])
                ->getQuery();

            return $this->paginateResults($materialStorageLocationsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialStorageLocationsByStorageArea: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryChecksByStorageArea($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $storageArea = (new StorageArea())->find($id);

            if (!$storageArea) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            $checksQuery = $storageArea->inventoryChecks()
                ->where('deleted', false)
                ->with(['creator', 'details'])
                ->getQuery();

            $result = $this->paginateResults($checksQuery, $perPage, $page);

            return [
                'success' => true,
                'data' => $result->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getChecksByStorageArea: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addInventoryCheckToStorageArea($id): array
    {
        try {
            $storageArea = (new StorageArea())->find($id);

            if (!$storageArea) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $data['storage_area_id'] = $id;

            // Tạo instance mới của InventoryCheck
            $inventoryCheck = new InventoryCheck();

            // Validate input
            $errors = $inventoryCheck->validate($data);

            if ($errors) {
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
                'message' => 'Thêm phiếu kiểm kê thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in addCheckToStorageArea: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryHistoryByStorageArea($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $storage = StorageArea::query()->where('id', $id)->first();

            if (!$storage) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $inventoryHistoryQuery = $storage->inventoryHistory()
                ->with(['product','material','creator'])
                ->getQuery();

            return [
                'data' => $this->paginateResults($inventoryHistoryQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getInventoryHistoryByStorageArea: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createStorageArea()
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

    public function updateStorageAreaById($id): bool|int|string
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

    public function deleteStorageArea($id)
    {
        $storage = StorageArea::find($id);

        if ($storage) {
            $storage->status = 'DELETED';
            $storage->save();
            return "Xóa thành công";
        } else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}