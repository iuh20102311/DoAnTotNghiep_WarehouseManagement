<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Utils\PaginationTrait;

class DiscountController
{
    use PaginationTrait;

    public function getDiscounts(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $discount = Discount::query()
                ->where('status', '!=', 'INACTIVE')
                ->where('deleted', false)
                ->with(['categories', 'products'])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
                ->orderBy('created_at', 'desc');

            $columns = [
                'coupon_code',
                'discount_value',
                'discount_unit',
                'minimum_order_value',
                'maximum_discount_value',
                'status',
                'note'
            ];

            $enumColumns = [
                'status',
                'discount_unit'
            ];

            foreach ($columns as $column) {
                if (isset($_GET[$column]) && $_GET[$column] !== '') {
                    $value = urldecode($_GET[$column]);

                    // Nếu là enum thì tìm chính xác
                    if (in_array($column, $enumColumns)) {
                        $discount->where($column, $value);
                    } // Các trường còn lại tìm tương đối
                    else {
                        $discount->where($column, 'LIKE', '%' . $value . '%');
                    }
                }
            }

            $dateColumns = ['valid_until', 'valid_start', 'created_at', 'updated_at'];

            foreach ($dateColumns as $column) {
                if (isset($_GET[$column . '_from'])) {
                    $discount->where($column, '>=', urldecode($_GET[$column . '_from']));
                }
                if (isset($_GET[$column . '_to'])) {
                    $discount->where($column, '<=', urldecode($_GET[$column . '_to']));
                }
            }

            $results = $this->paginateResults($discount, $perPage, $page);

            if ($results->isEmpty()) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy mã giảm giá nào'
                ];
            }

            return $results->toArray();
        } catch (\Exception $e) {
            error_log("Error in getDiscounts: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getDiscountById($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(404);
                return [
                    'error' => 'ID mã giảm giá không được để trống'
                ];
            }

            $discount = Discount::query()->where('id', $id)
                ->where('deleted', false)
                ->with(['categories', 'products'])
                ->first();

            if (!$discount) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy mã giảm giá với ID: ' . $id
                ];
            }

            return $discount->toArray();
        } catch (\Exception $e) {
            error_log("Error in getDiscountById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createDiscount(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu đầu vào không hợp lệ'
                ];
            }

            $discount = new Discount();
            $errors = $discount->validate($data);

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }

            $discount->fill($data);
            $discount->save();

            return $discount->toArray();

        } catch (\Exception $e) {
            error_log("Error in createDiscount: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateDiscountById($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'ID mã giảm giá không được để trống'
                ];
            }

            $discount = Discount::find($id);

            if (!$discount) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá với ID: ' . $id
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu cập nhật không hợp lệ'
                ];
            }

            $errors = $discount->validate($data, true);

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }

            $discount->fill($data);
            $discount->save();

            return $discount->toArray();
        } catch (\Exception $e) {
            error_log("Error in updateDiscountById: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteDiscount($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'ID mã giảm giá không được để trống'
                ];
            }

            $discount = Discount::find($id);

            if (!$discount) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá với ID: ' . $id
                ];
            }

            if ($discount->status == 'ACTIVE') {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Không thể xóa mã giảm giá đang ở trạng thái Active'
                ];
            }

            $discount->deleted = true;
            $discount->save();

            return [
                'success' => true,
                'message' => 'Xóa mã giảm giá thành công'
            ];
        } catch (\Exception $e) {
            error_log("Error in deleteDiscountById: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductByDiscount($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(404);
                return [
                    'error' => 'ID mã giảm giá không được để trống'
                ];
            }

            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $discount = Discount::query()->where('id', $id)->firstOrFail();

            if (!$discount) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy mã giảm giá với ID: ' . $id
                ];
            }

            $productsQuery = $discount->products()
                ->where('products.deleted', false)
                ->with(['discounts'])
                ->getQuery();

            $results = $this->paginateResults($productsQuery, $perPage, $page);

            if ($results->isEmpty()) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy sản phẩm nào áp dụng mã giảm giá này'
                ];
            }

            return $results->toArray();
        } catch (\Exception $e) {
            error_log("Error in getProductByDiscount: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addProductToDiscount($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'ID mã giảm giá không được để trống'
                ];
            }

            $discount = Discount::query()
                ->where('deleted', false)
                ->where('id', $id)
                ->first();

            if (!$discount) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá với ID: ' . $id
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['product_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Product ID là bắt buộc'
                ];
            }

            $product = Product::query()
                ->where('products.deleted', false)
                ->where('id', $data['product_id'])
                ->first();

            if (!$product) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm với ID: ' . $data['product_id']
                ];
            }

            $exists = $discount->products()
                ->where('deleted', false)
                ->where('product_id', $product->id)
                ->exists();

            if ($exists) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Sản phẩm đã được áp dụng mã giảm giá này'
                ];
            }

            $discount->products()->attach($product);

            return [
                'success' => true,
                'message' => 'Thêm sản phẩm vào mã giảm giá thành công'
            ];
        } catch (\Exception $e) {
            error_log("Error in addProductToDiscount: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeProductFromDiscount($id, $productId): array
    {
        try {
            if (empty($id)) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'ID mã giảm giá không được để trống'
                ];
            }

            if (empty($productId)) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'ID sản phẩm không được để trống'
                ];
            }

            $discount = Discount::find($id);
            if (!$discount) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá với ID: ' . $id
                ];
            }

            $product = Product::find($productId);
            if (!$product) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm với ID: ' . $productId
                ];
            }

            $exists = $discount->products()->where('product_id', $product->id)->exists();
            if (!$exists) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Sản phẩm chưa được áp dụng mã giảm giá này'
                ];
            }

            $discount->products()->detach($product->id);

            return [
                'success' => true,
                'message' => 'Xóa sản phẩm khỏi mã giảm giá thành công',
                'data' => $discount->fresh()->load(['products'])->toArray()
            ];
        } catch (\Exception $e) {
            error_log("Error in removeProductFromDiscount: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCategoryByDiscount($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            if (empty($id)) {
                http_response_code(404);
                return [
                    'error' => 'ID mã giảm giá không được để trống'
                ];
            }

            $discount = Discount::query()->where('id', $id)->firstOrFail();

            if (!$discount) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy mã giảm giá với ID: ' . $id
                ];
            }

            $categoriesQuery = $discount->categories()
                ->where('categories.deleted', false)
                ->with(['discounts'])
                ->getQuery();

            $results = $this->paginateResults($categoriesQuery, $perPage, $page);

            if ($results->isEmpty()) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy danh mục nào áp dụng mã giảm giá này'
                ];
            }

            return $results->toArray();
        } catch (\Exception $e) {
            error_log("Error in getCategoryByDiscount: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addCategoryToDiscount($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'ID mã giảm giá không được để trống'
                ];
            }

            $discount = Discount::query()->where('deleted', false)->where('id', $id)->first();

            if (!$discount) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá với ID: ' . $id
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['category_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Category ID là bắt buộc'
                ];
            }

            $category = Category::query()->where('id', $data['category_id'])->first();
            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $exists = $discount->categories()->where('category_id', $category->id)->exists();
            if ($exists) {
                return [
                    'success' => false,
                    'error' => 'Danh mục đã được áp dụng mã giảm giá này'
                ];
            }

            $discount->categories()->attach($category);

            return [
                'success' => true,
                'message' => 'Thêm danh mục vào mã giảm giá thành công'
            ];
        } catch (\Exception $e) {
            error_log("Error in addCategoryToDiscount: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeCategoryFromDiscount($id, $categoryId): array
    {
        try {
            $discount = Discount::find($id);

            if (!$discount) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá'
                ];
            }

            $category = Category::find($categoryId);
            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $exists = $discount->categories()->where('category_id', $category->id)->exists();
            if (!$exists) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Danh mục chưa được áp dụng mã giảm giá này'
                ];
            }

            $discount->categories()->detach($category->id);

            return [
                'success' => true,
                'message' => 'Xóa danh mục khỏi mã giảm giá thành công',
                'data' => $discount->fresh()->load(['categories'])->toArray()
            ];
        } catch (\Exception $e) {
            error_log("Error in removeCategoryFromDiscount: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}