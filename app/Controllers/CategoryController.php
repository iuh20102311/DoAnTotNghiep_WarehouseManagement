<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Material;
use App\Models\Product;
use App\Utils\PaginationTrait;

class CategoryController
{
    use PaginationTrait;

    public function getCategories(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->with(['products', 'discounts', 'materials']);

            // Basic filters
            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $category->where('status', $status);
            }

            if (isset($_GET['name'])) {
                $name = urldecode($_GET['name']);
                $category->where('name', 'like', '%' . $name . '%');
            }

            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                $category->where('type', $type);
            }

            if (isset($_GET['description'])) {
                $description = urldecode($_GET['description']);
                $category->where('description', 'like', '%' . $description . '%');
            }

            // Date range filters
            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $category->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $category->where('created_at', '<=', $createdTo);
            }

            // Relationship filters
            if (isset($_GET['product_id'])) {
                $productId = urldecode($_GET['product_id']);
                $category->whereHas('products', function ($query) use ($productId) {
                    $query->where('products.id', $productId);
                });
            }

            if (isset($_GET['material_id'])) {
                $materialId = urldecode($_GET['material_id']);
                $category->whereHas('materials', function ($query) use ($materialId) {
                    $query->where('materials.id', $materialId);
                });
            }

            if (isset($_GET['discount_id'])) {
                $discountId = urldecode($_GET['discount_id']);
                $category->whereHas('discounts', function ($query) use ($discountId) {
                    $query->where('discounts.id', $discountId);
                });
            }

            // Sorting
            if (isset($_GET['sort_by']) && isset($_GET['sort_direction'])) {
                $sortBy = urldecode($_GET['sort_by']);
                $sortDirection = urldecode($_GET['sort_direction']);
                $validDirections = ['asc', 'desc'];
                $validColumns = ['name', 'type', 'status', 'created_at', 'updated_at'];

                if (in_array($sortBy, $validColumns) && in_array(strtolower($sortDirection), $validDirections)) {
                    $category->orderBy($sortBy, $sortDirection);
                }
            } else {
                $category->orderBy('created_at', 'desc');
            }

            return [
                'success' => true,
                'data' => $this->paginateResults($category, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getCategories: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCategoryById($id): array
    {
        try {
            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->with(['products', 'discounts', 'materials'])
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            return [
                'success' => true,
                'data' => $category->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getCategoryById: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductByCategory($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $productsQuery = $category->products()
                ->with(['categories', 'prices', 'storageLocations'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($productsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getProductByCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addProductToCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['product_id'])) {
                return [
                    'success' => false,
                    'error' => 'Product ID là bắt buộc'
                ];
            }

            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $product = Product::find($data['product_id']);
            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $exists = $category->products()->where('product_id', $product->id)->exists();
            if ($exists) {
                return [
                    'success' => false,
                    'error' => 'Sản phẩm đã tồn tại trong danh mục này'
                ];
            }

            $category->products()->attach($product->id);

            return [
                'success' => true,
                'message' => 'Thêm sản phẩm vào danh mục thành công',
                'data' => $category->fresh()->load(['products'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in addProductToCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeProductFromCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['product_id'])) {
                return [
                    'success' => false,
                    'error' => 'Product ID là bắt buộc'
                ];
            }

            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $exists = $category->products()->where('product_id', $data['product_id'])->exists();
            if (!$exists) {
                return [
                    'success' => false,
                    'error' => 'Sản phẩm không tồn tại trong danh mục này'
                ];
            }

            $category->products()->detach($data['product_id']);

            return [
                'success' => true,
                'message' => 'Xóa sản phẩm khỏi danh mục thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removeProductFromCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getDiscountByCategory($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 15;
            $page = $_GET['page'] ?? 1;

            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $discountsQuery = $category->discounts()
                ->with(['categories', 'products'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($discountsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getDiscountByCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addDiscountToCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['discount_id'])) {
                return [
                    'success' => false,
                    'error' => 'Discount ID là bắt buộc'
                ];
            }

            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $discount = Discount::find($data['discount_id']);
            if (!$discount) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá'
                ];
            }

            $exists = $category->discounts()->where('discount_id', $discount->id)->exists();
            if ($exists) {
                return [
                    'success' => false,
                    'error' => 'Mã giảm giá đã tồn tại trong danh mục này'
                ];
            }

            $category->discounts()->attach($discount->id);

            return [
                'success' => true,
                'message' => 'Thêm mã giảm giá vào danh mục thành công',
                'data' => $category->fresh()->load(['discounts'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in addDiscountToCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeDiscountFromCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['discount_id'])) {
                return [
                    'success' => false,
                    'error' => 'Discount ID là bắt buộc'
                ];
            }

            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $exists = $category->discounts()->where('discount_id', $data['discount_id'])->exists();
            if (!$exists) {
                return [
                    'success' => false,
                    'error' => 'Mã giảm giá không tồn tại trong danh mục này'
                ];
            }

            $category->discounts()->detach($data['discount_id']);

            return [
                'success' => true,
                'message' => 'Xóa mã giảm giá khỏi danh mục thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removeDiscountFromCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialByCategory($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 15;
            $page = $_GET['page'] ?? 1;

            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $materialsQuery = $category->materials()
                ->with(['categories', 'providers'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($materialsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getMaterialByCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addMaterialToCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['material_id'])) {
                return [
                    'success' => false,
                    'error' => 'Material ID là bắt buộc'
                ];
            }

            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $material = Material::find($data['material_id']);
            if (!$material) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nguyên liệu'
                ];
            }

            $exists = $category->materials()->where('material_id', $material->id)->exists();
            if ($exists) {
                return [
                    'success' => false,
                    'error' => 'Nguyên liệu đã tồn tại trong danh mục này'
                ];
            }

            $category->materials()->attach($material->id);

            return [
                'success' => true,
                'message' => 'Thêm nguyên liệu vào danh mục thành công',
                'data' => $category->fresh()->load(['materials'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in addMaterialToCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeMaterialFromCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['material_id'])) {
                return [
                    'success' => false,
                    'error' => 'Material ID là bắt buộc'
                ];
            }

            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $exists = $category->materials()->where('material_id', $data['material_id'])->exists();
            if (!$exists) {
                return [
                    'success' => false,
                    'error' => 'Nguyên liệu không tồn tại trong danh mục này'
                ];
            }

            $category->materials()->detach($data['material_id']);

            return [
                'success' => true,
                'message' => 'Xóa nguyên liệu khỏi danh mục thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removeMaterialFromCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createCategory(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $category = new Category();
            $errors = $category->validate($data);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $category->fill($data);
            $category->save();

            return [
                'success' => true,
                'data' => $category->fresh()->load([
                    'products',
                    'materials',
                    'discounts'
                ])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateCategoryById($id): array
    {
        try {
            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $category->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $category->fill($data);
            $category->save();

            return [
                'success' => true,
                'data' => $category->fresh()->load([
                    'products',
                    'materials',
                    'discounts'
                ])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateCategoryById: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteCategory($id): array
    {
        try {
            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            // Soft delete
            $category->status = 'DELETED';
            $category->save();

            return [
                'success' => true,
                'message' => 'Xóa danh mục thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCategoryDiscountsByCategory($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $category = Category::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $categoryDiscountsQuery = $category->categoryDiscounts()
                ->with(['category', 'discount'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($categoryDiscountsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getCategoryDiscountsByCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}