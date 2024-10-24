<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductPrice;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductController
{
    use PaginationTrait;

    public function countProducts()
    {
        $total = Product::where('status', 'IN_STOCK')->count();
        $result = ['total' => $total];
        return json_encode($result);
    }


    public function getProducts() : array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('status', '!=' , 'DELETED')
            ->with(['categories','discounts','prices','storageLocations','orderDetails', 'exportReceiptDetails',
                    'importReceiptDetails','giftSets','inventoryCheckDetails','inventoryHistory']);

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
            $product->where('sku', 'like',  '%' . $sku . '%');
        }

        if (isset($_GET['quantity'])) {
            $quantity = urldecode($_GET['quantity']);
            $product->where('quantity', $quantity);
        }

        if (isset($_GET['weight'])) {
            $weight = urldecode($_GET['weight']);
            $product->where('weight', $weight);
        }

        if (isset($_GET['price'])) {
            $price = urldecode($_GET['price']);
            $product->where('price', $price);
        }

        if (isset($_GET['price_min'])) {
            $price_min = urldecode($_GET['price_min']);
            $product->where('price', '>=', $price_min);
        }

        if (isset($_GET['price_max'])) {
            $price_max = urldecode($_GET['price_max']);
            $product->where('price', '<=', $price_max);
        }

        return $this->paginateResults($product, $perPage, $page)->toArray();
    }

    public function getProductById($id) : false|string
    {
        $product = Product::query()->where('id',$id)
            ->with(['categories','discounts','prices','storageLocations','orderDetails', 'exportReceiptDetails',
                    'importReceiptDetails','giftSets','inventoryCheckDetails','inventoryHistory'])
            ->first();

        if (!$product) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($product->toArray());
    }

    public function getCategoryByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $categoriesQuery = $product->categories()
            ->with(['products','discounts','materials'])
            ->getQuery();

        return $this->paginateResults($categoriesQuery, $perPage, $page)->toArray();
    }

    public function addCategoryToProduct($id): string
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['category_id'])) {
                http_response_code(400);
                return json_encode(['message' => 'Category ID không được để trống']);
            }

            // Tìm product
            $product = Product::query()->find($id);
            if (!$product) {
                http_response_code(404);
                return json_encode(['message' => 'Không tìm thấy sản phẩm']);
            }

            // Tìm category
            $category = Category::query()->find($data['category_id']);
            if (!$category) {
                http_response_code(404);
                return json_encode(['message' => 'Không tìm thấy danh mục']);
            }

            // Kiểm tra xem category đã được thêm chưa
            $exists = $product->categories()->where('category_id', $category->id)->exists();
            if ($exists) {
                http_response_code(400);
                return json_encode(['message' => 'Danh mục đã tồn tại cho sản phẩm này']);
            }

            // Thêm category vào product
            $product->categories()->attach($category->id);

            http_response_code(200);
            return json_encode(['message' => 'Thêm danh mục thành công']);

        } catch (\Exception $e) {
            http_response_code(500);
            return json_encode(['message' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
        }
    }

    public function getDiscountByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $categoriesQuery = $product->discounts()
            ->with(['products','categories'])
            ->getQuery();

        return $this->paginateResults($categoriesQuery, $perPage, $page)->toArray();
    }

    public function addDiscountToProduct($id): string
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['discount_id'])) {
                http_response_code(400);
                return json_encode(['message' => 'Discount ID không được để trống']);
            }

            // Tìm product
            $product = Product::query()->find($id);
            if (!$product) {
                http_response_code(404);
                return json_encode(['message' => 'Không tìm thấy sản phẩm']);
            }

            // Tìm category
            $discount = Category::query()->find($data['discount_id']);
            if (!$discount) {
                http_response_code(404);
                return json_encode(['message' => 'Không tìm thấy mã giảm giá']);
            }

            // Kiểm tra xem category đã được thêm chưa
            $exists = $product->discounts()->where('discount_id', $discount->id)->exists();
            if ($exists) {
                http_response_code(400);
                return json_encode(['message' => 'Mã giảm giá đã được áp dụng cho sản phẩm này']);
            }

            // Thêm category vào product
            $product->discounts()->attach($discount->id);

            http_response_code(200);
            return json_encode(['message' => 'Thêm mã giảm giá cho sản phẩm thành công']);

        } catch (\Exception $e) {
            http_response_code(500);
            return json_encode(['message' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
        }
    }

    public function getOrderDetailsByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $orderDetailsQuery = $product->orderDetails()
            ->with(['product','order'])
            ->getQuery();

        return $this->paginateResults($orderDetailsQuery, $perPage, $page)->toArray();
    }

    public function getPriceByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $pricesQuery = $product->prices()
            ->with(['product'])
            ->getQuery();

        return $this->paginateResults($pricesQuery, $perPage, $page)->toArray();
    }

    public function getProductStorageLocationByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $productStorageLocationsQuery = $product->storageLocations()
            ->with(['product','storageArea'])
            ->getQuery();

        return $this->paginateResults($productStorageLocationsQuery, $perPage, $page)->toArray();
    }

    public function getProductImportReceiptDetailsByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $productImportReceiptDetailsQuery = $product->importReceiptDetails()
            ->with(['product','productImportReceipt','storageArea'])
            ->getQuery();

        return $this->paginateResults($productImportReceiptDetailsQuery, $perPage, $page)->toArray();
    }

    public function getProductExportReceiptDetailsByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $productExportReceiptDetailsQuery = $product->exportReceiptDetails()
            ->with(['product','productExportReceipt','storageArea'])
            ->getQuery();

        return $this->paginateResults($productExportReceiptDetailsQuery, $perPage, $page)->toArray();
    }

    public function getGiftSetsByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $giftSetsQuery = $product->giftSets()
            ->with(['products','prices','orders'])
            ->getQuery();

        return $this->paginateResults($giftSetsQuery, $perPage, $page)->toArray();
    }

    public function getInventoryCheckDetailsByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $inventoryCheckDetailsQuery = $product->inventoryCheckDetails()
            ->with(['product','inventoryCheck'])
            ->getQuery();

        return $this->paginateResults($inventoryCheckDetailsQuery, $perPage, $page)->toArray();
    }

    public function getInventoryHistoryByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $inventoryHistoryQuery = $product->inventoryHistory()
            ->with(['product','creator','storageArea'])
            ->getQuery();

        return $this->paginateResults($inventoryHistoryQuery, $perPage, $page)->toArray();
    }

    public function getProductDiscountsByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $productDiscountsQuery = $product->productDiscounts()
            ->with(['product','discount'])
            ->getQuery();

        return $this->paginateResults($productDiscountsQuery, $perPage, $page)->toArray();
    }

    public function getProductCategoriesByProduct($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $product = Product::query()->where('id', $id)->firstOrFail();
        $productCategoriesQuery = $product->productCategories()
            ->with(['product','category'])
            ->getQuery();

        return $this->paginateResults($productCategoriesQuery, $perPage, $page)->toArray();
    }

    public function createProduct(): Model | string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $product = new Product();
        $error = $product->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $product->fill($data);
        $product->save();
        return $product;
    }

    public function updateProductById($id): bool | int | string
    {
        $product = Product::find($id);

        if (!$product) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $product->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $product->fill($data);
        $product->save();

        return $product;
    }

    public function deleteProduct($id)
    {
        $product = Product::find($id);

        if ($product) {
            $product->status = 'DELETED';
            $product->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
        //$results = Product::destroy($id);
        //$results === 0 && http_response_code(404);
        //return $results === 1 ? "Xóa thành công" : "Không tìm thấy";
    }
}

