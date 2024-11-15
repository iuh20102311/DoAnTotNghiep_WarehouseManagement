<?php

namespace App\Controllers;

use App\Models\MaterialStorageLocation;
use App\Utils\PaginationTrait;

class MaterialStorageLocationController
{
    use PaginationTrait;

    public function getMaterialStorageLocations(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialStorageLocation = MaterialStorageLocation::query()
                ->where('deleted', false)
                ->with(['material', 'provider', 'storageArea'])
                ->orderBy('created_at', 'desc');

            if (isset($_GET['material_id'])) {
                $materialId = urldecode($_GET['material_id']);
                $materialStorageLocation->where('material_id', $materialId);
            }

            if (isset($_GET['provider_id'])) {
                $providerId = urldecode($_GET['provider_id']);
                $materialStorageLocation->where('provider_id', $providerId);
            }

            if (isset($_GET['storage_area_id'])) {
                $storageAreaId = urldecode($_GET['storage_area_id']);
                $materialStorageLocation->where('storage_area_id', $storageAreaId);
            }

            if (isset($_GET['quantity'])) {
                $quantity = urldecode($_GET['quantity']);
                $materialStorageLocation->where('quantity', $quantity);
            }

            if (isset($_GET['quantity_min'])) {
                $quantityMin = urldecode($_GET['quantity_min']);
                $materialStorageLocation->where('quantity', '>=', $quantityMin);
            }

            if (isset($_GET['quantity_max'])) {
                $quantityMax = urldecode($_GET['quantity_max']);
                $materialStorageLocation->where('quantity', '<=', $quantityMax);
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $materialStorageLocation->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $materialStorageLocation->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $materialStorageLocation->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $materialStorageLocation->where('updated_at', '<=', $updatedTo);
            }

            return $this->paginateResults($materialStorageLocation, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialStorageLocations: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialStorageLocationById($id): array
    {
        try {
            $materialStorageLocation = MaterialStorageLocation::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->with(['material', 'provider', 'storageArea'])
                ->first();

            if (!$materialStorageLocation) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            return $materialStorageLocation->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialStorageLocationById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialByMaterialStorageLocation($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialStorageLocation = MaterialStorageLocation::where('deleted', false)->find($id);

            if (!$materialStorageLocation) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            $materialsDetailsQuery = $materialStorageLocation->material()
                ->with(['categories', 'providers', 'storageLocations', 'exportReceiptDetails', 'importReceiptDetails', 'inventoryCheckDetails', 'inventoryHistory'])
                ->getQuery();

            $result = $this->paginateResults($materialsDetailsQuery, $perPage, $page);
            return $result->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialByMaterialStorageLocation: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProvidersByMaterialStorageLocation($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialStorageLocation = MaterialStorageLocation::where('deleted', false)->find($id);

            if (!$materialStorageLocation) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            $providersDetailsQuery = $materialStorageLocation->provider()
                ->with(['materials', 'materialImportReceipts'])
                ->getQuery();

            $result = $this->paginateResults($providersDetailsQuery, $perPage, $page);
            return $result->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProvidersByMaterialStorageLocation: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getStorageAreaByMaterialStorageLocation($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialStorageLocation = MaterialStorageLocation::where('deleted', false)->find($id);

            if (!$materialStorageLocation) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            $materialsDetailsQuery = $materialStorageLocation->storageArea()
                ->with(['productStorageLocations', 'materialStorageLocations', 'inventoryChecks', 'inventoryHistory'])
                ->getQuery();

            $result = $this->paginateResults($materialsDetailsQuery, $perPage, $page);
            return $result->toArray();

        } catch (\Exception $e) {
            error_log("Error in getStorageAreaByMaterialStorageLocation: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createMaterialStorageLocation(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $materialStorageLocation = new MaterialStorageLocation();
            $errors = $materialStorageLocation->validate($data);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $materialStorageLocation->fill($data);
            $materialStorageLocation->save();

            return [
                'success' => true,
                'data' => $materialStorageLocation->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createMaterialStorageLocation: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateMaterialStorageLocationById($id): array
    {
        try {
            $materialStorageLocation = MaterialStorageLocation::where('deleted', false)->find($id);

            if (!$materialStorageLocation) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $materialStorageLocation->validate($data, true);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $materialStorageLocation->fill($data);
            $materialStorageLocation->save();

            return [
                'success' => true,
                'data' => $materialStorageLocation->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateMaterialStorageLocationById: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteMaterialStorageLocation($id): array
    {
        try {
            $materialStorageLocation = MaterialStorageLocation::where('deleted', false)->find($id);

            if (!$materialStorageLocation) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vị trí lưu trữ vật liệu'
                ];
            }

            $materialStorageLocation->deleted = true;
            $materialStorageLocation->save();

            return [
                'success' => true,
                'message' => 'Xóa vị trí lưu trữ vật liệu thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteMaterialStorageLocation: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}