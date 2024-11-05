<?php

namespace App\Controllers;

use App\Models\InventoryCheckDetail;
use App\Models\Material;
use App\Models\Product;
use App\Utils\PaginationTrait;

class InventoryCheckDetailController
{
    use PaginationTrait;

    public function getInventoryCheckDetails(): array
    {
        try {
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            
            $inventoryCheckDetail = InventoryCheckDetail::query()
                ->where('deleted', false)
                ->with(['inventoryCheck', 'product', 'material']);

            if (isset($_GET['inventory_check_id'])) {
                $inventoryCheckId = urldecode($_GET['inventory_check_id']);
                $inventoryCheckDetail->where('inventory_check_id', $inventoryCheckId);
            }

            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                if ($type === 'product') {
                    $inventoryCheckDetail->whereNotNull('product_id')->whereNull('material_id');
                } else if ($type === 'material') {
                    $inventoryCheckDetail->whereNotNull('material_id')->whereNull('product_id');
                }
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $inventoryCheckDetail->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $inventoryCheckDetail->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $inventoryCheckDetail->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $inventoryCheckDetail->where('updated_at', '<=', $updatedTo);
            }

            return $this->paginateResults($inventoryCheckDetail, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getInventoryCheckDetails: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryCheckDetailById($id): array
    {
        try {
            $detail = InventoryCheckDetail::query()
                ->where('deleted', false)
                ->with(['inventoryCheck', 'product', 'material'])
                ->find($id);

            if (!$detail) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            return $detail->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createInventoryCheckDetail(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $detail = new InventoryCheckDetail();
            $errors = $detail->validate($data);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $detail->fill($data);
            $detail->save();

            return [
                'success' => true,
                'data' => $detail->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateInventoryCheckDetailById($id): array
    {
        try {
            $detail = InventoryCheckDetail::query()
                ->where('deleted', false)
                ->find($id);

            if (!$detail) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $detail->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $detail->fill($data);
            $detail->save();

            return [
                'success' => true,
                'data' => $detail->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteInventoryCheckDetail($id): array
    {
        try {
            $detail = InventoryCheckDetail::find($id);

            if (!$detail) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $detail->deleted = true;
            $detail->save();

            return [
                'success' => false,
                'message' => 'Xóa thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductsByInventoryCheckDetail($id): array
    {
        try {
            $detail = InventoryCheckDetail::query()
                ->where('id', $id)
                ->whereNotNull('product_id')
                ->first();

            if (!$detail) {
                return [
                    'error' => 'Không tìm thấy chi tiết kiểm kho hoặc không phải sản phẩm'
                ];
            }

            return $detail->product()->with([
                'categories',
                'prices',
                'storageLocations'
            ])->get()->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialsByInventoryCheckDetail($id): array
    {
        try {
            $detail = InventoryCheckDetail::query()
                ->where('id', $id)
                ->whereNotNull('material_id')
                ->first();

            if (!$detail) {
                return [
                    'error' => 'Không tìm thấy chi tiết kiểm kho hoặc không phải nguyên liệu'
                ];
            }

            return $detail->material()->with([
                'providers',
                'storageLocations'
            ])->get()->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryChecksByInventoryCheckDetail($id): array
    {
        try {
            $detail = InventoryCheckDetail::find($id);

            if (!$detail) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = $detail->inventoryCheck()->with([
                'creator' => function($productER) {
                    $productER->select('id', 'email', 'role_id')
                        ->with(['profile' => function($q) {
                            $q->select('user_id', 'first_name', 'last_name');
                        }]);
                },
                'storageArea'
            ])->get()->toArray();

            if (isset($data['creator']['profile'])) {
                $data['creator']['full_name'] = trim($data['creator']['profile']['first_name'] . ' ' . $data['creator']['profile']['last_name']);
            }

            return $data;

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addProductToInventoryCheckDetail($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['product_id'])) {
                return [
                    'success' => false,
                    'error' => 'Product ID is required'
                ];
            }

            $detail = InventoryCheckDetail::query()
                ->where('deleted', false)
                ->find($id);

            if (!$detail) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy chi tiết kiểm kho'
                ];
            }

            // Kiểm tra sản phẩm tồn tại
            $product = Product::find($data['product_id']);
            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            // Cập nhật mối quan hệ
            $detail->material_id = null; // Đảm bảo không có material
            $detail->product_id = $data['product_id'];

            // Cập nhật các trường khác nếu có
            if (isset($data['exact_quantity'])) {
                $detail->exact_quantity = $data['exact_quantity'];
            }
            if (isset($data['actual_quantity'])) {
                $detail->actual_quantity = $data['actual_quantity'];
            }
            if (isset($data['defective_quantity'])) {
                $detail->defective_quantity = $data['defective_quantity'];
            }
            if (isset($data['error_description'])) {
                $detail->error_description = $data['error_description'];
            }

            $detail->save();

            return [
                'success' => true,
                'data' => $detail->fresh()->load(['product'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in addProductToInventoryCheckDetail: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addMaterialToInventoryCheckDetail($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['material_id'])) {
                return [
                    'success' => false,
                    'error' => 'Material ID is required'
                ];
            }

            $detail = InventoryCheckDetail::query()
                ->where('deleted', false)
                ->find($id);

            if (!$detail) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy chi tiết kiểm kho'
                ];
            }

            // Kiểm tra material tồn tại
            $material = Material::find($data['material_id']);
            if (!$material) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nguyên liệu'
                ];
            }

            // Cập nhật mối quan hệ
            $detail->product_id = null; // Đảm bảo không có product
            $detail->material_id = $data['material_id'];

            // Cập nhật các trường khác nếu có
            if (isset($data['exact_quantity'])) {
                $detail->exact_quantity = $data['exact_quantity'];
            }
            if (isset($data['actual_quantity'])) {
                $detail->actual_quantity = $data['actual_quantity'];
            }
            if (isset($data['defective_quantity'])) {
                $detail->defective_quantity = $data['defective_quantity'];
            }
            if (isset($data['error_description'])) {
                $detail->error_description = $data['error_description'];
            }

            $detail->save();

            return [
                'success' => true,
                'data' => $detail->fresh()->load(['material'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in addMaterialToInventoryCheckDetail: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProductInInventoryCheckDetail($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $detail = InventoryCheckDetail::query()
                ->where('deleted', false)
                ->whereNotNull('product_id')
                ->find($id);

            if (!$detail) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy chi tiết kiểm kho hoặc không có sản phẩm'
                ];
            }

            // Nếu có product_id mới, kiểm tra và cập nhật
            if (!empty($data['product_id'])) {
                $product = Product::find($data['product_id']);
                if (!$product) {
                    return [
                        'success' => false,
                        'error' => 'Không tìm thấy sản phẩm mới'
                    ];
                }
                $detail->product_id = $data['product_id'];
            }

            // Cập nhật các trường khác
            if (isset($data['exact_quantity'])) {
                $detail->exact_quantity = $data['exact_quantity'];
            }
            if (isset($data['actual_quantity'])) {
                $detail->actual_quantity = $data['actual_quantity'];
            }
            if (isset($data['defective_quantity'])) {
                $detail->defective_quantity = $data['defective_quantity'];
            }
            if (isset($data['error_description'])) {
                $detail->error_description = $data['error_description'];
            }

            $detail->save();

            return [
                'success' => true,
                'data' => $detail->fresh()->load(['product'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateProductInInventoryCheckDetail: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateMaterialInInventoryCheckDetail($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $detail = InventoryCheckDetail::query()
                ->where('deleted', false)
                ->whereNotNull('material_id')
                ->find($id);

            if (!$detail) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy chi tiết kiểm kho hoặc không có nguyên liệu'
                ];
            }

            // Nếu có material_id mới, kiểm tra và cập nhật
            if (!empty($data['material_id'])) {
                $material = Material::find($data['material_id']);
                if (!$material) {
                    return [
                        'success' => false,
                        'error' => 'Không tìm thấy nguyên liệu mới'
                    ];
                }
                $detail->material_id = $data['material_id'];
            }

            // Cập nhật các trường khác
            if (isset($data['exact_quantity'])) {
                $detail->exact_quantity = $data['exact_quantity'];
            }
            if (isset($data['actual_quantity'])) {
                $detail->actual_quantity = $data['actual_quantity'];
            }
            if (isset($data['defective_quantity'])) {
                $detail->defective_quantity = $data['defective_quantity'];
            }
            if (isset($data['error_description'])) {
                $detail->error_description = $data['error_description'];
            }

            $detail->save();

            return [
                'success' => true,
                'data' => $detail->fresh()->load(['material'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateMaterialInInventoryCheckDetail: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}