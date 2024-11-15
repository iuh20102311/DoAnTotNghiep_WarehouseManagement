<?php

namespace App\Controllers;

use App\Models\ProductStorageLocation;
use App\Utils\PaginationTrait;

class ProductStorageLocationController
{
    use PaginationTrait;

    public function getProductStorageLocations(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $productStorageLocation = ProductStorageLocation::query()
                ->where('deleted', false)
                ->with(['product', 'storageArea'])
                ->orderBy('created_at', 'desc');

            if (isset($_GET['product_id'])) {
                $productId = urldecode($_GET['product_id']);
                $productStorageLocation->where('product_id', $productId);
            }

            if (isset($_GET['storage_area_id'])) {
                $storageAreaId = urldecode($_GET['storage_area_id']);
                $productStorageLocation->where('storage_area_id', $storageAreaId);
            }

            if (isset($_GET['quantity'])) {
                $quantity = urldecode($_GET['quantity']);
                $productStorageLocation->where('quantity', $quantity);
            }

            if (isset($_GET['quantity_min'])) {
                $quantityMin = urldecode($_GET['quantity_min']);
                $productStorageLocation->where('quantity', '>=', $quantityMin);
            }

            if (isset($_GET['quantity_max'])) {
                $quantityMax = urldecode($_GET['quantity_max']);
                $productStorageLocation->where('quantity', '<=', $quantityMax);
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $productStorageLocation->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $productStorageLocation->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $productStorageLocation->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $productStorageLocation->where('updated_at', '<=', $updatedTo);
            }

            return $this->paginateResults($productStorageLocation, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProductStorageLocations: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductStorageLocationById($id): array
    {
        try {
            $productStorageLocation = ProductStorageLocation::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->with(['product', 'storageArea'])
                ->first();

            if (!$productStorageLocation) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ sản phẩm'
                ];
            }

            return $productStorageLocation->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProductStorageLocationById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductsByProductStorageLocation($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $productStorageLocation = ProductStorageLocation::where('deleted', false)->find($id);

            if (!$productStorageLocation) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ sản phẩm'
                ];
            }

            $productsQuery = $productStorageLocation->product()
                ->with(['categories', 'prices'])
                ->getQuery();

            return $this->paginateResults($productsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProductsByProductStorageLocation: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getStorageAreasByProductStorageLocation($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $productStorageLocation = ProductStorageLocation::where('deleted', false)->find($id);

            if (!$productStorageLocation) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy vị trí lưu trữ sản phẩm'
                ];
            }

            $storageAreasQuery = $productStorageLocation->storageArea()
                ->with(['productStorageLocations', 'materialStorageLocations', 'inventoryChecks', 'inventoryHistory'])
                ->getQuery();

            return $this->paginateResults($storageAreasQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getStorageAreasByProductStorageLocation: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createProductStorageLocation(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $productStorageLocation = new ProductStorageLocation();
            $errors = $productStorageLocation->validate($data);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $productStorageLocation->fill($data);
            $productStorageLocation->save();

            return [
                'success' => true,
                'data' => $productStorageLocation->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createProductStorageLocation: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProductStorageLocationById($id): array
    {
        try {
            $productStorageLocation = ProductStorageLocation::where('deleted', false)->find($id);

            if (!$productStorageLocation) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vị trí lưu trữ sản phẩm'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $productStorageLocation->validate($data, true);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $productStorageLocation->fill($data);
            $productStorageLocation->save();

            return [
                'success' => true,
                'data' => $productStorageLocation->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateProductStorageLocationById: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteProductStorageLocation($id): array
    {
        try {
            $productStorageLocation = ProductStorageLocation::where('deleted', false)->find($id);

            if (!$productStorageLocation) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vị trí lưu trữ sản phẩm'
                ];
            }

            $productStorageLocation->deleted = true;
            $productStorageLocation->save();

            return [
                'success' => true,
                'message' => 'Xóa vị trí lưu trữ sản phẩm thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteProductStorageLocation: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}