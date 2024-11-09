<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Material;
use App\Models\Product;
use App\Utils\PaginationTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Capsule\Manager as Capsule;

class CategoryController
{
    use PaginationTrait;

    public function getAllCategoriesProductCount(): array
    {
        try {
            $categories = Category::where('deleted', false)->get();

            if ($categories->isEmpty()) {
                return [
                    'success' => false,
                    'error' => 'Không có danh mục nào'
                ];
            }

            $result = [];

            foreach ($categories as $category) {
                if ($category->type === 'PRODUCT') {
                    // Đếm từ bảng product_categories
                    $query = $category->products()
                        ->where('products.deleted', false)
                        ->where('product_categories.deleted', false);

                    $totalItems = $query->count();
                    $availableCount = $query->sum('quantity_available');
                    $belowMinimumStock = $query->whereRaw('quantity_available < minimum_stock_level')->count();
                    $outOfStock = $query->where('quantity_available', 0)->count();
                    $inStock = $query->where('quantity_available', '>', 0)->count();

                    // Stock by area cho products
                    $stockByArea = Capsule::table('product_categories')
                        ->join('products', 'product_categories.product_id', '=', 'products.id')
                        ->join('product_storage_locations', 'products.id', '=', 'product_storage_locations.product_id')
                        ->join('storage_areas', 'product_storage_locations.storage_area_id', '=', 'storage_areas.id')
                        ->where('product_categories.category_id', $category->id)
                        ->where('products.deleted', false)
                        ->where('product_categories.deleted', false)
                        ->groupBy('storage_areas.id', 'storage_areas.name')  // Thêm storage_areas.id vào group by
                        ->select(
                            'storage_areas.name as storage_area',
                            Capsule::raw('SUM(product_storage_locations.quantity) as total_quantity')
                        )
                        ->get();

                } else {
                    // Đếm từ bảng material_categories
                    $query = $category->materials()
                        ->where('materials.deleted', false)
                        ->where('material_categories.deleted', false);

                    $totalItems = $query->count();
                    $availableCount = $query->sum('quantity_available');
                    $belowMinimumStock = $query->whereRaw('quantity_available < minimum_stock_level')->count();
                    $outOfStock = $query->where('quantity_available', 0)->count();
                    $inStock = $query->where('quantity_available', '>', 0)->count();

                    // Stock by area cho materials
                    $stockByArea = Capsule::table('material_categories')
                        ->join('materials', 'material_categories.material_id', '=', 'materials.id')
                        ->join('material_storage_locations', 'materials.id', '=', 'material_storage_locations.material_id')
                        ->join('storage_areas', 'material_storage_locations.storage_area_id', '=', 'storage_areas.id')
                        ->where('material_categories.category_id', $category->id)
                        ->where('materials.deleted', false)
                        ->where('material_categories.deleted', false)
                        ->groupBy('storage_areas.id', 'storage_areas.name')  // Thêm storage_areas.id vào group by
                        ->select(
                            'storage_areas.name as storage_area',
                            Capsule::raw('SUM(material_storage_locations.quantity) as total_quantity')
                        )
                        ->get();
                }

                $result[] = [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'category_type' => $category->type,
                    'totals_by_type' => [
                        'PRODUCT' => $category->type === 'PRODUCT' ? (int)$availableCount : 0,
                        'MATERIAL' => $category->type === 'MATERIAL' ? (int)$availableCount : 0
                    ],
                    'available_quantity' => (int)$availableCount,
                    'total_items' => (int)$totalItems,
                    'below_minimum_stock' => (int)$belowMinimumStock,
                    'stock_by_area' => $stockByArea->map(function ($item) {
                        return [
                            'storage_area' => $item->storage_area,
                            'total_quantity' => (int)$item->total_quantity
                        ];
                    }),
                    'statistics' => [
                        $category->type => [
                            'out_of_stock' => (int)$outOfStock,
                            'low_stock' => (int)$belowMinimumStock,
                            'in_stock' => (int)$inStock
                        ]
                    ]
                ];
            }

            return $result;

        } catch (\Exception $e) {
            error_log("Error in getAllCategoriesProductCount: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCategories(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $category = Category::query()
                ->where('deleted', false)
                ->with([
                    'products' => function($query) {
                        $query->where('products.deleted', false);
                    },
                    'materials' => function($query) {
                        $query->where('materials.deleted', false);
                    },
                    'discounts'
                ])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1
                WHEN status = 'INACTIVE' THEN 2  
                WHEN status = 'OUT_OF_STOCKS' THEN 3
                ELSE 4
            END")
                ->orderByRaw("CASE 
                WHEN type = 'PRODUCT' THEN 1
                WHEN type = 'MATERIAL' THEN 2
                ELSE 3
            END")
                ->orderBy('created_at', 'desc');

            if ($perPage <= 0 || $page <= 0) {
                http_response_code(400);
                return [
                    'error' => 'Invalid pagination parameters'
                ];
            }

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

            $results = $this->paginateResults($category, $perPage, $page)->toArray();

            // Tính total_product và total_material cho mỗi category
            $data = collect($results['data'])->map(function($category) {
                // Tính tổng quantity_available của products
                $total_product = collect($category['products'] ?? [])
                    ->sum('quantity_available');

                // Tính tổng quantity_available của materials
                $total_material = collect($category['materials'] ?? [])
                    ->sum('quantity_available');

                // Thêm totals vào dữ liệu hiện tại
                $category['total_product'] = (int)$total_product;
                $category['total_material'] = (int)$total_material;

                return $category;
            })->all();

            $results['data'] = $data;

            return $results;

        } catch (\Exception $e) {
            error_log("Error in getCategories: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCategoryById($id): array
    {
        try {
            $category = Category::query()
                ->where('deleted', false)
                ->where('status', 'ACTIVE')
                ->with(['products', 'discounts', 'materials'])
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            return $category->toArray();

        } catch (\Exception $e) {
            error_log("Error in getCategoryById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCategoryList(): array
    {
        try {
            $categoryQuery = Category::query()
                ->where('deleted', false)
                ->where('status', 'ACTIVE')
                ->select('id', 'name', 'type')
                ->orderBy('name', 'asc');

            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                $categoryQuery->where('type', $type);
            }

            $categories = $categoryQuery->get();

            return $categories->toArray();

        } catch (\Exception $e) {
            error_log("Error in getCategoryList: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createCategory(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ];
            }

            $category = new Category();
            $errors = $category->validate($data);

            if ($errors) {
                http_response_code(422);
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
            http_response_code(500);
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
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ];
            }

            $errors = $category->validate($data, true);

            if ($errors) {
                http_response_code(422);
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
            http_response_code(500);
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
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            if ($category->status == 'ACTIVE') {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Không thể xóa sản phẩm đang ở trạng thái active'
                ];
            }

            // Soft delete
            $category->deleted = true;
            $category->save();

            return [
                'success' => true,
                'message' => 'Xóa danh mục thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteCategory: " . $e->getMessage());
            http_response_code(500);
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

            if ($perPage <= 0 || $page <= 0) {
                http_response_code(400);
                return [
                    'error' => 'Invalid pagination parameters'
                ];
            }

            $category = Category::query()
                ->where('status', '!=', 'INACTIVE')
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $productsQuery = $category->products()
                ->with(['categories', 'prices', 'storageLocations'])
                ->getQuery();

            return $this->paginateResults($productsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProductByCategory: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addProductToCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ];
            }

            if (empty($data['product_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Product ID là bắt buộc'
                ];
            }

            $category = Category::query()
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $product = Product::find($data['product_id']);
            if (!$product) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $exists = $category->products()->where('product_id', $product->id)->exists();
            if ($exists) {
                http_response_code(409);
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

    public function deleteProductFromCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ];
            }

            $category = Category::query()
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $exists = $category->products()->where('product_id', $data['product_id'])->exists();
            if (!$exists) {
                http_response_code(404);
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
            error_log("Error in deleteProductFromCategory: " . $e->getMessage());
            http_response_code(500);
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

            if ($perPage <= 0 || $page <= 0) {
                http_response_code(400);
                return [
                    'error' => 'Invalid pagination parameters'
                ];
            }

            $category = Category::query()
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $discountsQuery = $category->discounts()
                ->with(['categories', 'products'])
                ->getQuery();

            return $this->paginateResults($discountsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getDiscountByCategory: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addDiscountToCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ];
            }

            if (empty($data['discount_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Discount ID là bắt buộc'
                ];
            }

            $category = Category::query()
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $discount = Discount::find($data['discount_id']);
            if (!$discount) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá'
                ];
            }

            $exists = $category->discounts()->where('discount_id', $discount->id)->exists();
            if ($exists) {
                http_response_code(409);
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
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteDiscountFromCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ];
            }

            if (empty($data['discount_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Discount ID là bắt buộc'
                ];
            }

            $category = Category::query()
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $exists = $category->discounts()->where('discount_id', $data['discount_id'])->exists();
            if (!$exists) {
                http_response_code(404);
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
            error_log("Error in deleteDiscountFromCategory: " . $e->getMessage());
            http_response_code(500);
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

            if ($perPage <= 0 || $page <= 0) {
                http_response_code(400);
                return [
                    'error' => 'Invalid pagination parameters'
                ];
            }

            $category = Category::query()
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $materialsQuery = $category->materials()
                ->where('materials.deleted', false)
                ->with(['categories', 'providers'])
                ->getQuery();

            return $this->paginateResults($materialsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialByCategory: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addMaterialToCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ];
            }

            if (empty($data['material_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Material ID là bắt buộc'
                ];
            }

            $category = Category::query()
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $material = Material::find($data['material_id']);
            if (!$material) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nguyên liệu'
                ];
            }

            $exists = $category->materials()->where('material_id', $material->id)->exists();
            if ($exists) {
                http_response_code(409);
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
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteMaterialFromCategory($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ];
            }

            if (empty($data['material_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Material ID là bắt buộc'
                ];
            }

            $category = Category::query()
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $exists = $category->materials()->where('material_id', $data['material_id'])->exists();
            if (!$exists) {
                http_response_code(404);
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
            error_log("Error in deleteMaterialFromCategory: " . $e->getMessage());
            http_response_code(500);
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

            if ($perPage <= 0 || $page <= 0) {
                http_response_code(400);
                return [
                    'error' => 'Invalid pagination parameters'
                ];
            }

            $category = Category::query()
                ->where('deleted', false)
                ->find($id);

            if (!$category) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $categoryDiscountsQuery = $category->categoryDiscounts()
                ->with(['category', 'discount'])
                ->getQuery();

            return $this->paginateResults($categoryDiscountsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getCategoryDiscountsByCategory: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}