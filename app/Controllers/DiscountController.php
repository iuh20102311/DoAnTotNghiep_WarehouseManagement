<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DiscountController
{
    use PaginationTrait;

    public function getDiscounts(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $discount = Discount::query()->where('status', '!=' , 'DELETED')->with(['categories', 'products']);

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $discount->where('status', $status);
            }

            if (isset($_GET['coupon_code'])) {
                $coupon_code = urldecode($_GET['coupon_code']);
                $discount->where('coupon_code', $coupon_code);
            }

            if (isset($_GET['discount_value'])) {
                $discount_value = urldecode($_GET['discount_value']);
                $discount->where('discount_value', $discount_value);
            }

            return $this->paginateResults($discount, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getDiscounts: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getDiscountById($id) : array
    {
        try {
            $discount = Discount::query()->where('id',$id)
                ->with(['categories', 'products'])
                ->first();

            if (!$discount) {
                return [
                    'error' => 'Không tìm thấy mã giảm giá'
                ];
            }

            return $discount->toArray();

        } catch (\Exception $e) {
            error_log("Error in getDiscountById: " . $e->getMessage());
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
            $discount = new Discount();

            $errors = $discount->validate($data);

            if ($errors) {
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
            $discount = Discount::find($id);

            if (!$discount) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $discount->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }

            $discount->fill($data);
            $discount->save();

            return $discount->toArray()
                ;
        } catch (\Exception $e) {
            error_log("Error in updateDiscountById: " . $e->getMessage());
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
            $discount = Discount::find($id);

            if (!$discount) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá'
                ];
            }

            if ($discount->status == 'ACTIVE') {
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
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $discount = Discount::query()->where('id', $id)->firstOrFail();
            $productsQuery = $discount->products()->with(['discounts'])->getQuery();

            return $this->paginateResults($productsQuery, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            error_log("Error in getProductByDiscount: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addProductToDiscount($id): array
    {
        try {
            $discount = Discount::query()->where('id',$id)->first();
            $data = json_decode(file_get_contents('php://input'),true);

            if (empty($data['product_id'])) {
                return [
                    'success' => false,
                    'error' => 'Product ID là bắt buộc'
                ];
            }

            $product = Product::query()->where('id',$data['product_id'])->first();
            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $exists = $discount->products()->where('product_id', $product->id)->exists();
            if ($exists) {
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
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProductInDiscount($discountId, $productId): array
    {
        try {
            $discount = Discount::find($discountId);
            if (!$discount) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá'
                ];
            }

            $product = Product::find($productId);
            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $exists = $discount->products()->where('product_id', $product->id)->exists();
            if (!$exists) {
                return [
                    'success' => false,
                    'error' => 'Sản phẩm chưa được áp dụng mã giảm giá này'
                ];
            }

            $discount->products()->updateExistingPivot($product->id, $data);

            return [
                'success' => true,
                'message' => 'Cập nhật mối quan hệ giữa sản phẩm và mã giảm giá thành công',
                'data' => $discount->fresh()->load(['products'])->toArray()
            ];
        } catch (\Exception $e) {
            error_log("Error in updateProductDiscount: " . $e->getMessage());
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
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $discount = Discount::query()->where('id', $id)->firstOrFail();
            $categoriesQuery = $discount->categories()->with(['discounts'])->getQuery();

            return $this->paginateResults($categoriesQuery, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            error_log("Error in getCategoryByDiscount: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeProductFromDiscount($discountId, $productId): array
    {
        try {
            $discount = Discount::find($discountId);
            if (!$discount) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá'
                ];
            }

            $product = Product::find($productId);
            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $exists = $discount->products()->where('product_id', $product->id)->exists();
            if (!$exists) {
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
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addCategoryToDiscount($id): array
    {
        try {
            $discount = Discount::query()->where('id',$id)->first();
            $data = json_decode(file_get_contents('php://input'),true);

            if (empty($data['category_id'])) {
                return [
                    'success' => false,
                    'error' => 'Category ID là bắt buộc'
                ];
            }

            $category = Category::query()->where('id',$data['category_id'])->first();
            if (!$category) {
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
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateCategoryByDiscount($discountId, $categoryId): array
    {
        try {
            $discount = Discount::find($discountId);
            if (!$discount) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá'
                ];
            }

            $category = Category::find($categoryId);
            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $exists = $discount->categories()->where('category_id', $category->id)->exists();
            if (!$exists) {
                return [
                    'success' => false,
                    'error' => 'Danh mục chưa được áp dụng mã giảm giá này'
                ];
            }

            $discount->categories()->updateExistingPivot($category->id, $data);

            return [
                'success' => true,
                'message' => 'Cập nhật mối quan hệ giữa danh mục và mã giảm giá thành công',
                'data' => $discount->fresh()->load(['categories'])->toArray()
            ];
        } catch (\Exception $e) {
            error_log("Error in updateCategoryDiscount: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeCategoryFromDiscount($discountId, $categoryId): array
    {
        try {
            $discount = Discount::find($discountId);
            if (!$discount) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá'
                ];
            }

            $category = Category::find($categoryId);
            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $exists = $discount->categories()->where('category_id', $category->id)->exists();
            if (!$exists) {
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
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}