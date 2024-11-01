<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Utils\PaginationTrait;

class ProductController
{
    use PaginationTrait;

    public function countProducts(): array
    {
        try {
            $total = Product::where('status', 'IN_STOCK')->count();
            return [
                'data' => [
                    'total' => $total
                ]
            ];
        } catch (\Exception $e) {
            error_log("Error in countProducts: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProducts(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->with([
                    'categories',
                    'prices',
                    'storageLocations',
                    'exportReceiptDetails',
                    'importReceiptDetails',
                    'giftSets',
                    'inventoryCheckDetails',
                    'inventoryHistory'
                ]);

            // Filters for basic fields
            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $product->where('status', $status);
            }

            if (isset($_GET['name'])) {
                $name = urldecode($_GET['name']);
                $product->where('name', 'like', '%' . $name . '%');
            }

            if (isset($_GET['packing'])) {
                $packing = urldecode($_GET['packing']);
                $product->where('packing', 'like', '%' . $packing . '%');
            }

            if (isset($_GET['sku'])) {
                $sku = urldecode($_GET['sku']);
                $product->where('sku', 'like', '%' . $sku . '%');
            }

            // Filters for numeric fields with exact and range search
            if (isset($_GET['quantity'])) {
                $quantity = urldecode($_GET['quantity']);
                $product->where('quantity', $quantity);
            }
            if (isset($_GET['quantity_min'])) {
                $quantity_min = urldecode($_GET['quantity_min']);
                $product->where('quantity', '>=', $quantity_min);
            }
            if (isset($_GET['quantity_max'])) {
                $quantity_max = urldecode($_GET['quantity_max']);
                $product->where('quantity', '<=', $quantity_max);
            }

            if (isset($_GET['quantity_available'])) {
                $quantity_available = urldecode($_GET['quantity_available']);
                $product->where('quantity_available', $quantity_available);
            }
            if (isset($_GET['quantity_available_min'])) {
                $quantity_available_min = urldecode($_GET['quantity_available_min']);
                $product->where('quantity_available', '>=', $quantity_available_min);
            }
            if (isset($_GET['quantity_available_max'])) {
                $quantity_available_max = urldecode($_GET['quantity_available_max']);
                $product->where('quantity_available', '<=', $quantity_available_max);
            }

            if (isset($_GET['minimum_stock_level'])) {
                $minimum_stock_level = urldecode($_GET['minimum_stock_level']);
                $product->where('minimum_stock_level', $minimum_stock_level);
            }
            if (isset($_GET['minimum_stock_level_min'])) {
                $minimum_stock_level_min = urldecode($_GET['minimum_stock_level_min']);
                $product->where('minimum_stock_level', '>=', $minimum_stock_level_min);
            }
            if (isset($_GET['minimum_stock_level_max'])) {
                $minimum_stock_level_max = urldecode($_GET['minimum_stock_level_max']);
                $product->where('minimum_stock_level', '<=', $minimum_stock_level_max);
            }

            if (isset($_GET['weight'])) {
                $weight = urldecode($_GET['weight']);
                $product->where('weight', $weight);
            }
            if (isset($_GET['weight_min'])) {
                $weight_min = urldecode($_GET['weight_min']);
                $product->where('weight', '>=', $weight_min);
            }
            if (isset($_GET['weight_max'])) {
                $weight_max = urldecode($_GET['weight_max']);
                $product->where('weight', '<=', $weight_max);
            }

            if (isset($_GET['price']) || isset($_GET['price_min']) || isset($_GET['price_max'])) {
                $product->whereHas('prices', function ($query) {
                    if (isset($_GET['price'])) {
                        $price = urldecode($_GET['price']);
                        $query->where('price', $price);
                    }
                    if (isset($_GET['price_min'])) {
                        $price_min = urldecode($_GET['price_min']);
                        $query->where('price', '>=', $price_min);
                    }
                    if (isset($_GET['price_max'])) {
                        $price_max = urldecode($_GET['price_max']);
                        $query->where('price', '<=', $price_max);
                    }
                });
            }

            if (isset($_GET['description'])) {
                $description = urldecode($_GET['description']);
                $product->where('description', 'like', '%' . $description . '%');
            }

            if (isset($_GET['usage_time'])) {
                $usage_time = urldecode($_GET['usage_time']);
                $product->where('usage_time', 'like', '%' . $usage_time . '%');
            }

            if (isset($_GET['created_from'])) {
                $created_from = urldecode($_GET['created_from']);
                $product->where('created_at', '>=', $created_from);
            }
            if (isset($_GET['created_to'])) {
                $created_to = urldecode($_GET['created_to']);
                $product->where('created_at', '<=', $created_to);
            }

            if (isset($_GET['category_id'])) {
                $category_id = urldecode($_GET['category_id']);
                $product->whereHas('categories', function ($query) use ($category_id) {
                    $query->where('categories.id', $category_id);
                });
            }

            if (isset($_GET['storage_area_id'])) {
                $storage_area_id = urldecode($_GET['storage_area_id']);
                $product->whereHas('storageLocations', function ($query) use ($storage_area_id) {
                    $query->where('storage_area_id', $storage_area_id);
                });
            }

            // Sorting
            if (isset($_GET['sort_by']) && isset($_GET['sort_direction'])) {
                $sortBy = urldecode($_GET['sort_by']);
                $sortDirection = urldecode($_GET['sort_direction']);
                $validDirections = ['asc', 'desc'];
                $validColumns = ['name', 'sku', 'quantity', 'weight', 'created_at', 'updated_at'];

                if (in_array($sortBy, $validColumns) && in_array(strtolower($sortDirection), $validDirections)) {
                    $product->orderBy($sortBy, $sortDirection);
                }
            } else {
                $product->orderBy('created_at', 'desc');
            }

            return [
                'data' => $this->paginateResults($product, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getProducts: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductById($id): array
    {
        try {
            $product = Product::query()
                ->where('id', $id)
                ->with([
                    'categories',
                    'prices',
                    'storageLocations',
                    'exportReceiptDetails',
                    'importReceiptDetails',
                    'giftSets',
                    'inventoryCheckDetails',
                    'inventoryHistory'
                ])
                ->first();

            if (!$product) {
                return [
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            return [
                'data' => $product->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getProductById: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createProduct(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $product = new Product();
            $errors = $product->validate($data);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $product->fill($data);
            $product->save();

            return [
                'success' => true,
                'data' => $product->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProductById($id): array
    {
        try {
            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $product->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $product->fill($data);
            $product->save();

            return [
                'success' => true,
                'data' => $product->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateProductById: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteProduct($id): array
    {
        try {
            $product = Product::query()->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $product->status = 'DELETED';
            $product->save();

            return [
                'success' => true,
                'message' => 'Xóa sản phẩm thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCategoryByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $categoriesQuery = $product->categories()
                ->with(['products', 'discounts', 'materials'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($categoriesQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getCategoryByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addCategoryToProduct($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['category_id'])) {
                return [
                    'success' => false,
                    'error' => 'Category ID là bắt buộc'
                ];
            }

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $category = Category::find($data['category_id']);
            if (!$category) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy danh mục'
                ];
            }

            $exists = $product->categories()->where('category_id', $category->id)->exists();
            if ($exists) {
                return [
                    'success' => false,
                    'error' => 'Danh mục đã tồn tại cho sản phẩm này'
                ];
            }

            $product->categories()->attach($category->id);

            return [
                'success' => true,
                'data' => $product->fresh()->load(['categories'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in addCategoryToProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeCategoryFromProduct($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['category_id'])) {
                return [
                    'success' => false,
                    'error' => 'Category ID là bắt buộc'
                ];
            }

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $exists = $product->categories()->where('category_id', $data['category_id'])->exists();
            if (!$exists) {
                return [
                    'success' => false,
                    'error' => 'Danh mục không tồn tại cho sản phẩm này'
                ];
            }

            $product->categories()->detach($data['category_id']);

            return [
                'success' => true,
                'message' => 'Xóa danh mục khỏi sản phẩm thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removeCategoryFromProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getDiscountByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $discountsQuery = $product->discounts()
                ->with(['products', 'categories'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($discountsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getDiscountByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addDiscountToProduct($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['discount_id'])) {
                return [
                    'success' => false,
                    'error' => 'Discount ID là bắt buộc'
                ];
            }

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $discount = Discount::find($data['discount_id']);
            if (!$discount) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy mã giảm giá'
                ];
            }

            $exists = $product->discounts()->where('discount_id', $discount->id)->exists();
            if ($exists) {
                return [
                    'success' => false,
                    'error' => 'Mã giảm giá đã được áp dụng cho sản phẩm này'
                ];
            }

            $product->discounts()->attach($discount->id);

            return [
                'success' => true,
                'data' => $product->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in addDiscountToProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeDiscountFromProduct($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['discount_id'])) {
                return [
                    'success' => false,
                    'error' => 'Discount ID là bắt buộc'
                ];
            }

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $exists = $product->discounts()->where('discount_id', $data['discount_id'])->exists();
            if (!$exists) {
                return [
                    'success' => false,
                    'error' => 'Mã giảm giá không được áp dụng cho sản phẩm này'
                ];
            }

            $product->discounts()->detach($data['discount_id']);

            return [
                'success' => true,
                'message' => 'Xóa mã giảm giá khỏi sản phẩm thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removeDiscountFromProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getOrderDetailsByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $orderDetailsQuery = $product->orderDetails()
                ->with(['product', 'order'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($orderDetailsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getOrderDetailsByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getPriceByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $pricesQuery = $product->prices()
                ->with(['product'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($pricesQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getPriceByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addPriceToProduct($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['price']) || !isset($data['effective_date'])) {
                return [
                    'success' => false,
                    'error' => 'Price và effective_date là bắt buộc'
                ];
            }

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $productPrice = new ProductPrice([
                'product_id' => $id,
                'price' => $data['price'],
                'effective_date' => $data['effective_date'],
                'note' => $data['note'] ?? null
            ]);

            $product->prices()->save($productPrice);

            return [
                'success' => true,
                'data' => $product->fresh()->load(['prices'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in addPriceToProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProductPrice($id, $priceId): array
    {
        try {
            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $price = $product->prices()->find($priceId);
            if (!$price) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy giá sản phẩm'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (isset($data['price'])) {
                $price->price = $data['price'];
            }
            if (isset($data['effective_date'])) {
                $price->effective_date = $data['effective_date'];
            }
            if (isset($data['note'])) {
                $price->note = $data['note'];
            }

            $price->save();

            return [
                'success' => true,
                'data' => $product->fresh()->load(['prices'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateProductPrice: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductStorageLocationByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $storageLocationsQuery = $product->storageLocations()
                ->with(['product', 'storageArea'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($storageLocationsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getProductStorageLocationByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addStorageLocationToProduct($id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['storage_area_id']) || !isset($data['quantity'])) {
                return [
                    'success' => false,
                    'error' => 'Storage area ID và quantity là bắt buộc'
                ];
            }

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            // Kiểm tra xem đã có location này chưa
            $exists = $product->storageLocations()
                ->where('storage_area_id', $data['storage_area_id'])
                ->exists();

            if ($exists) {
                return [
                    'success' => false,
                    'error' => 'Sản phẩm đã có trong khu vực này'
                ];
            }

            $product->storageLocations()->create([
                'storage_area_id' => $data['storage_area_id'],
                'quantity' => $data['quantity'],
                'note' => $data['note'] ?? null
            ]);

            return [
                'success' => true,
                'data' => $product->fresh()->load(['storageLocations.storageArea'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in addStorageLocationToProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProductStorageLocation($id, $locationId): array
    {
        try {
            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $location = $product->storageLocations()->find($locationId);
            if (!$location) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vị trí lưu trữ'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (isset($data['quantity'])) {
                $location->quantity = $data['quantity'];
            }
            if (isset($data['note'])) {
                $location->note = $data['note'];
            }

            $location->save();

            return [
                'success' => true,
                'data' => $product->fresh()->load(['storageLocations.storageArea'])->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateProductStorageLocation: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeStorageLocationFromProduct($id, $locationId): array
    {
        try {
            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $location = $product->storageLocations()->find($locationId);
            if (!$location) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vị trí lưu trữ'
                ];
            }

            $location->delete();

            return [
                'success' => true,
                'message' => 'Xóa vị trí lưu trữ thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removeStorageLocationFromProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductImportReceiptDetailsByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $importReceiptDetailsQuery = $product->importReceiptDetails()
                ->with(['product', 'productImportReceipt', 'storageArea'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($importReceiptDetailsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getProductImportReceiptDetailsByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductExportReceiptDetailsByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $exportReceiptDetailsQuery = $product->exportReceiptDetails()
                ->with(['product', 'productExportReceipt', 'storageArea'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($exportReceiptDetailsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getProductExportReceiptDetailsByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGiftSetsByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $giftSetsQuery = $product->giftSets()
                ->with(['products', 'prices', 'orders'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($giftSetsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getGiftSetsByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryCheckDetailsByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $inventoryCheckDetailsQuery = $product->inventoryCheckDetails()
                ->with(['product', 'inventoryCheck'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($inventoryCheckDetailsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getInventoryCheckDetailsByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryHistoryByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $inventoryHistoryQuery = $product->inventoryHistory()
                ->with(['product', 'creator', 'storageArea'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($inventoryHistoryQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getInventoryHistoryByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductDiscountsByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $productDiscountsQuery = $product->productDiscounts()
                ->with(['product', 'discount'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($productDiscountsQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getProductDiscountsByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductCategoriesByProduct($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $product = Product::query()
                ->where('status', '!=', 'DELETED')
                ->find($id);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            $productCategoriesQuery = $product->productCategories()
                ->with(['product', 'category'])
                ->getQuery();

            return [
                'success' => true,
                'data' => $this->paginateResults($productCategoriesQuery, $perPage, $page)->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getProductCategoriesByProduct: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }


}

