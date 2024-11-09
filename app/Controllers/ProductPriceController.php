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

            if (!$data || !isset($data['product_id']) || !is_array($data['product_id']) || empty($data['product_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Phải có ít nhất một product_id'
                ];
            }

            // Kiểm tra tất cả products có tồn tại không
            $existingProducts = Product::query()
                ->whereIn('id', $data['product_id'])
                ->where('deleted', false)
                ->pluck('id')
                ->toArray();

            $notFoundProducts = array_diff($data['product_id'], $existingProducts);
            if (!empty($notFoundProducts)) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm với ID: ' . implode(', ', $notFoundProducts)
                ];
            }

            $results = [];
            foreach ($data['product_id'] as $productId) {
                $priceData = array_merge($data, ['product_id' => $productId]);
                unset($priceData['product_id']); // Xóa mảng product_id cũ
                $priceData['product_id'] = $productId; // Thêm product_id mới

                $productprice = new ProductPrice();
                $errors = $productprice->validate($priceData);

                if ($errors) {
                    http_response_code(400);
                    return [
                        'success' => false,
                        'error' => 'Validation failed for product ' . $productId,
                        'details' => $errors
                    ];
                }

                $productprice->fill($priceData);
                $productprice->save();

                $results[] = $productprice->toArray();
            }

            return [
                'success' => true,
                'data' => $results
            ];

        } catch (\Exception $e) {
            error_log("Error in createProductPrice: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
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
                return [
                    'success' => false,
                    'error' => 'Invalid JSON data'
                ];
            }

            $results = [];

            // Nếu có product_id mới và là array
            if (isset($data['product_id']) && is_array($data['product_id'])) {
                // Kiểm tra products tồn tại
                $existingProducts = Product::query()
                    ->whereIn('id', $data['product_id'])
                    ->where('deleted', false)
                    ->pluck('id')
                    ->toArray();

                $notFoundProducts = array_diff($data['product_id'], $existingProducts);
                if (!empty($notFoundProducts)) {
                    http_response_code(404);
                    return [
                        'success' => false,
                        'error' => 'Không tìm thấy sản phẩm với ID: ' . implode(', ', $notFoundProducts)
                    ];
                }

                // Kiểm tra những product_id đã có giá
                $existingPrices = ProductPrice::query()
                    ->whereIn('product_id', $data['product_id'])
                    ->where('deleted', false)
                    ->where('id', '!=', $id) // Loại trừ record hiện tại
                    ->pluck('product_id')
                    ->toArray();

                // Update record hiện tại nếu product_id mới không tồn tại trong bảng giá
                if (!in_array($productprice->product_id, $data['product_id'])) {
                    $updateData = $data;
                    unset($updateData['product_id']);
                    $updateData['product_id'] = $data['product_id'][0];

                    $error = $productprice->validate($updateData);
                    if ($error !== null) {
                        http_response_code(400);
                        return [
                            'success' => false,
                            'error' => $error
                        ];
                    }

                    $productprice->fill($updateData);
                    $productprice->save();

                    $results[] = [
                        'action' => 'updated',
                        'data' => $productprice->toArray()
                    ];
                } else {
                    $results[] = [
                        'action' => 'unchanged',
                        'data' => $productprice->toArray()
                    ];
                }

                // Thêm mới cho các product_id chưa có giá
                $newProductIds = array_diff($data['product_id'], $existingPrices);
                foreach ($newProductIds as $productId) {
                    // Bỏ qua nếu là product_id của record hiện tại
                    if ($productId == $productprice->product_id) {
                        continue;
                    }

                    $newPriceData = array_merge($data, ['product_id' => $productId]);
                    unset($newPriceData['product_id']); // Xóa mảng product_id cũ
                    $newPriceData['product_id'] = $productId; // Thêm product_id mới

                    $newProductPrice = new ProductPrice();
                    $error = $newProductPrice->validate($newPriceData);

                    if ($error === null) {
                        $newProductPrice->fill($newPriceData);
                        $newProductPrice->save();
                        $results[] = [
                            'action' => 'created',
                            'data' => $newProductPrice->toArray()
                        ];
                    }
                }

                // Thêm thông tin về product_id đã tồn tại vào kết quả
                foreach ($existingPrices as $existingProductId) {
                    $results[] = [
                        'action' => 'skipped',
                        'data' => [
                            'product_id' => $existingProductId,
                            'message' => 'Giá cho sản phẩm này đã tồn tại'
                        ]
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'Cập nhật và thêm mới giá thành công',
                    'data' => $results
                ];
            } else {
                // Update bình thường nếu không có mảng product_id
                $error = $productprice->validate($data);
                if ($error !== null) {
                    http_response_code(400);
                    return [
                        'success' => false,
                        'error' => $error
                    ];
                }

                $productprice->fill($data);
                $productprice->save();

                return [
                    'success' => true,
                    'data' => $productprice->toArray()
                ];
            }
        } catch (\Exception $e) {
            error_log("Error in updateProductPriceById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
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