<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductPriceController
{
    use PaginationTrait;

    public function getProductPrices(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $productprices = ProductPrice::query()->where('deleted', false)
                ->with(['product'])
                ->orderByRaw("CASE 
                    WHEN status = 'ACTIVE' THEN 1 
                    ELSE 2 
                    END")
                ->orderBy('created_at', 'desc');

            // Filter by product_id
            if (isset($_GET['product_id'])) {
                $productId = urldecode($_GET['product_id']);
                $productprices->where('product_id', $productId);
            }

            // Filter by date range for date_start
            if (isset($_GET['date_start_from'])) {
                $startFrom = urldecode($_GET['date_start_from']);
                $productprices->where('date_start', '>=', $startFrom);
            }
            if (isset($_GET['date_start_to'])) {
                $startTo = urldecode($_GET['date_start_to']);
                $productprices->where('date_start', '<=', $startTo);
            }

            // Filter by date range for date_end
            if (isset($_GET['date_end_from'])) {
                $endFrom = urldecode($_GET['date_end_from']);
                $productprices->where('date_end', '>=', $endFrom);
            }
            if (isset($_GET['date_end_to'])) {
                $endTo = urldecode($_GET['date_end_to']);
                $productprices->where('date_end', '<=', $endTo);
            }

            // Filter by status
            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $productprices->where('status', $status);
            }

            // Filter by price range
            if (isset($_GET['price'])) {
                $price = urldecode($_GET['price']);
                $productprices->where('price', $price);
            }
            if (isset($_GET['price_min'])) {
                $priceMin = urldecode($_GET['price_min']);
                $productprices->where('price', '>=', $priceMin);
            }
            if (isset($_GET['price_max'])) {
                $priceMax = urldecode($_GET['price_max']);
                $productprices->where('price', '<=', $priceMax);
            }

            // Filter by created_at range
            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $productprices->where('created_at', '>=', $createdFrom);
            }
            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $productprices->where('created_at', '<=', $createdTo);
            }

            // Filter by updated_at range
            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $productprices->where('updated_at', '>=', $updatedFrom);
            }
            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $productprices->where('updated_at', '<=', $updatedTo);
            }

            if (isset($_GET['product_name'])) {
                $productName = urldecode($_GET['product_name']);
                $productprices->whereHas('product', function ($query) use ($productName) {
                    $query->where('name', 'like', '%' . $productName . '%');
                });
            }

            if (isset($_GET['product_sku'])) {
                $productSku = urldecode($_GET['product_sku']);
                $productprices->whereHas('product', function ($query) use ($productSku) {
                    $query->where('sku', 'like', '%' . $productSku . '%');
                });
            }

            if (isset($_GET['search'])) {
                $search = urldecode($_GET['search']);
                $productprices->whereHas('product', function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('sku', 'like', '%' . $search . '%');
                });
            }

            return $this->paginateResults($productprices, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            error_log("Error in getProductPrices: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductPriceById($id): false|string
    {
        try {
            $productprice = ProductPrice::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->with(['product'])
                ->first();

            if (!$productprice) {
                http_response_code(404);
                return json_encode(['error' => 'Không tìm thấy giá sản phẩm với ID: ' . $id]);
            }

            return json_encode($productprice->toArray());
        } catch (\Exception $e) {
            error_log("Error in getProductPriceById: " . $e->getMessage());
            http_response_code(500);
            return json_encode([
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ]);
        }
    }

    public function getProductsByProductPrice($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $productprice = ProductPrice::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->firstOrFail();

            $productsQuery = $productprice->product()->getQuery();

            return $this->paginateResults($productsQuery, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            error_log("Error in getProductsByProductPrice: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createProductPrice()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return json_encode(['error' => 'Invalid JSON data']);
            }

            // Kiểm tra product có tồn tại không
            $product = Product::query()
                ->where('id', $data['product_id'])
                ->where('deleted', false)
                ->first();

            if (!$product) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm với ID: ' . $data['product_id']
                ];
            }

            $productprice = new ProductPrice();
            $errors = $productprice->validate($data);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $productprice->fill($data);
            $productprice->save();

            return [
                'success' => true,
                'data' => $productprice->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createProductPrice: " . $e->getMessage());
            http_response_code(500);
            return json_encode([
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ]);
        }
    }

    public function updateProductPriceById($id)
    {
        try {
            $productprice = ProductPrice::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$productprice) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy giá sản phẩm với ID: ' . $id
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                return json_encode(['error' => 'Invalid JSON data']);
            }

            // Nếu có thay đổi product_id thì kiểm tra product mới có tồn tại không
            if (isset($data['product_id']) && $data['product_id'] != $productprice->product_id) {
                $product = Product::query()
                    ->where('id', $data['product_id'])
                    ->where('deleted', false)
                    ->first();

                if (!$product) {
                    http_response_code(404);
                    return [
                        'success' => false,
                        'error' => 'Không tìm thấy sản phẩm với ID: ' . $data['product_id']
                    ];
                }
            }

            $error = $productprice->validate($data);
            if ($error !== null) {
                http_response_code(400);
                return json_encode(['error' => $error]);
            }

            $productprice->fill($data);
            $productprice->save();

            return $productprice;
        } catch (\Exception $e) {
            error_log("Error in updateProductPriceById: " . $e->getMessage());
            http_response_code(500);
            return json_encode([
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ]);
        }
    }

    public function deleteProductPrice($id): string
    {
        try {
            $productprice = ProductPrice::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$productprice) {
                http_response_code(404);
                return json_encode(['error' => 'Không tìm thấy giá sản phẩm với ID: ' . $id]);
            }

            $productprice->deleted = true;
            $productprice->save();

            return json_encode(['message' => 'Xóa thành công']);
        } catch (\Exception $e) {
            error_log("Error in deleteProductPrice: " . $e->getMessage());
            http_response_code(500);
            return json_encode([
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ]);
        }
    }
}