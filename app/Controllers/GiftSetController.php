<?php

namespace App\Controllers;

use App\Models\GiftSet;
use App\Models\GiftSetPrice;
use App\Models\Order;
use App\Models\Product;
use App\Utils\PaginationTrait;

class GiftSetController
{
    use PaginationTrait;

    public function getGiftSets(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $giftSet = GiftSet::query()
                ->where('deleted', false)
                ->with(['products', 'prices', 'orders'])
                ->orderByRaw("CASE 
                                    WHEN status = 'ACTIVE' THEN 1
                                    WHEN status = 'INACTIVE' THEN 2  
                                    WHEN status = 'OUT_OF_STOCKS' THEN 3
                                    ELSE 4
                                END")
                ->orderBy('created_at', 'desc');

            if (isset($_GET['sku'])) {
                $sku = urldecode($_GET['sku']);
                $giftSet->where('sku', 'like', '%' . $sku . '%');
            }

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $giftSet->where('status', $status);
            }

            if (isset($_GET['name'])) {
                $name = urldecode($_GET['name']);
                $giftSet->where('name', '%' . $name . '%');
            }

            if (isset($_GET['description'])) {
                $description = urldecode($_GET['description']);
                $giftSet->where('description', '%' . $description . '%');
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $giftSet->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $giftSet->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $giftSet->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $giftSet->where('updated_at', '<=', $updatedTo);
            }

            $results = $this->paginateResults($giftSet, $perPage, $page);

            if ($results->isEmpty()) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy bộ quà tặng nào',
                ];
            }

            return $results->toArray();
        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGiftSetById($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID bộ quà tặng không được để trống',
                ];
            }

            $giftSet = (new GiftSet())
                ->where('deleted', false)
                ->with(['products', 'prices'])
                ->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy bộ quà tặng với ID: ' . $id,
                ];
            }

            return $giftSet->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createGiftSet(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu đầu vào không hợp lệ',
                ];
            }

            $giftSet = new GiftSet();
            $errors = $giftSet->validate($data);

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu không hợp lệ',
                    'details' => $errors
                ];
            }

            $giftSet->fill($data);
            $giftSet->save();

            return [
                'success' => true,
                'data' => $giftSet->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateGiftSetById($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID bộ quà tặng không được để trống',
                ];
            }

            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy bộ quà tặng với ID: ' . $id,
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu cập nhật không hợp lệ',
                ];
            }

            $errors = $giftSet->validate($data, true);

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu không hợp lệ',
                    'details' => $errors
                ];
            }

            $giftSet->fill($data);
            $giftSet->save();

            return [
                'success' => true,
                'data' => $giftSet->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteGiftSet($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID bộ quà tặng không được để trống',
                ];
            }

            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy bộ quà tặng với ID: ' . $id,
                ];
            }

            // Kiểm tra nếu có đơn hàng liên quan
            if ($giftSet->orders()->where('deleted', false)->exists()) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Không thể xóa bộ quà tặng đang có trong đơn hàng',
                ];
            }

            $giftSet->deleted = true;
            $giftSet->save();

            return [
                'success' => true,
                'message' => 'Xóa thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductsByGiftSet($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID bộ quà tặng không được để trống',
                ];
            }

            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $giftSet = GiftSet::query()->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy bộ quà tặng với ID: ' . $id,
                ];
            }

            $productsQuery = $giftSet->products()
                ->where('products.deleted', false)
                ->with(['categories', 'discounts', 'prices', 'storageHistories', 'orderDetails'])
                ->getQuery();

            $results = $this->paginateResults($productsQuery, $perPage, $page);

            if ($results->isEmpty()) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm nào trong bộ quà tặng này',
                ];
            }

            return $results->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addProductToGiftSet($id): array
    {
        try {
            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng',
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['product_id']) || empty($data['quantity'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin sản phẩm hoặc số lượng',
                ];
            }

            $product = (new Product())->find($data['product_id']);

            if (!$product) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm',
                ];
            }

            // Attach với pivot quantity
            $giftSet->products()->attach($product->id, ['quantity' => $data['quantity']]);

            return [
                'success' => true,
                'message' => 'Thêm sản phẩm vào quà tặng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeProductFromGiftSet($id): array
    {
        try {
            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng',
                ];
            }
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['product_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin sản phẩm',
                ];
            }

            $product = (new Product())->find($data['product_id']);

            if (!$product) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm',
                ];
            }

            // Detach product khỏi gift set
            $giftSet->products()->detach($product->id);

            return [
                'success' => true,
                'message' => 'Xóa sản phẩm khỏi quà tặng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProductQuantityInGiftSet($id): array
    {
        try {
            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng',
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['product_id']) || !isset($data['quantity'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin sản phẩm hoặc số lượng',
                ];
            }

            if ($data['quantity'] < 0) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Số lượng không được âm',
                ];
            }

            $product = (new Product())->find($data['product_id']);

            if (!$product) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy sản phẩm',
                ];
            }

            // Update quantity trong pivot table
            $giftSet->products()->updateExistingPivot($product->id, [
                'quantity' => $data['quantity']
            ]);

            return [
                'success' => true,
                'message' => 'Cập nhật số lượng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getOrdersByGiftSet($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID bộ quà tặng không được để trống',
                ];
            }

            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $giftSet = GiftSet::query()->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy bộ quà tặng',
                ];
            }

            $ordersQuery = $giftSet->orders()
                ->where('orders.deleted', false)
                ->with(['customer', 'creator', 'orderDetails'])
                ->getQuery();

            $results = $this->paginateResults($ordersQuery, $perPage, $page);

            if ($results->isEmpty()) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy đơn hàng nào',
                ];
            }

            return $results->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addOrderToGiftSet($id): array
    {
        try {
            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng',
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['order_id']) || empty($data['quantity']) || empty($data['price'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin đơn hàng, số lượng hoặc giá',
                ];
            }

            $order = (new Order())->find($data['order_id']);

            if (!$order) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy đơn hàng',
                ];
            }

            // Dùng attach thêm trực tiếp vào bảng pivot
            $giftSet->orders()->attach($data['order_id'], [
                'quantity' => $data['quantity'],
                'price' => $data['price']
            ]);

            return [
                'success' => true,
                'message' => 'Thêm quà tặng vào đơn hàng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeOrderFromGiftSet($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID bộ quà tặng không được để trống',
                ];
            }

            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng',
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['order_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin đơn hàng',
                ];
            }

            $order = (new Order())->find($data['order_id']);

            if (!$order) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy đơn hàng',
                ];
            }

            // Dùng detach xóa quan hệ trong bảng pivot
            $giftSet->orders()->detach($data['order_id']);

            return [
                'success' => true,
                'message' => 'Xóa quà tặng khỏi đơn hàng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateOrderByGiftSet($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID bộ quà tặng không được để trống',
                ];
            }

            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng',
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['order_id']) || !isset($data['quantity']) || !isset($data['price'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin cập nhật',
                ];
            }

            $order = (new Order())->find($data['order_id']);

            if (!$order) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy đơn hàng',
                ];
            }

            if (!$giftSet->orders()->where('order_id', $data['order_id'])->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Đơn hàng không thuộc về bộ quà tặng này',
                ];
            }

            // Dùng updateExistingPivot để cập nhật thông tin trong bảng pivot
            $giftSet->orders()->updateExistingPivot($data['order_id'], [
                'quantity' => $data['quantity'],
                'price' => $data['price']
            ]);

            return [
                'success' => true,
                'message' => 'Cập nhật thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in updateGiftSetPrice: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGiftSetPricesByGiftSet($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID bộ quà tặng không được để trống',
                ];
            }

            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $giftSet = (new GiftSet())->where('deleted', false)->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy bộ quà tặng',
                ];
            }

            $pricesQuery = $giftSet->prices()
                ->where('deleted', false)
                ->getQuery();

            $result = $this->paginateResults($pricesQuery, $perPage, $page);

            if ($result->isEmpty()) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy giá nào cho bộ quà tặng này',
                ];
            }

            return $result->toArray();

        } catch (\Exception $e) {
            error_log("Error in getPricesByGiftSet: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addGiftSetPricesToGiftSet($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID bộ quà tặng không được để trống',
                ];
            }

            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng',
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu giá không được để trống',
                ];
            }

            $data['gift_set_id'] = $id;

            // Tạo instance mới của GiftSetPrice
            $giftSetPrice = new GiftSetPrice();

            // Validate input
            $errors = $giftSetPrice->validate($data);

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu không hợp lệ',
                    'details' => $errors
                ];
            }

            $giftSetPrice->fill($data);
            $giftSetPrice->save();

            return [
                'success' => true,
                'message' => 'Thêm giá cho quà tặng thành công',
                'data' => $giftSetPrice->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in addPriceToGiftSet: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeGiftSetPricesFromGiftSet($id, $priceId): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID bộ quà tặng không được để trống',
                ];
            }

            if (empty($priceId)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID giá không được để trống',
                ];
            }


            $giftSet = (new GiftSet())
                ->where('deleted', false)
                ->find($id);

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng',
                ];
            }

            $giftSetPrice = (new GiftSetPrice())
                ->where('gift_set_id', $id)
                ->where('id', $priceId)
                ->where('deleted', false)
                ->first();

            if (!$giftSetPrice) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy giá của quà tặng',
                ];
            }

            if ($giftSetPrice->orders()->where('deleted', false)->exists()) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Không thể xóa giá đang được sử dụng trong đơn hàng',
                ];
            }

            // Soft delete
            $giftSetPrice->deleted = true;
            $giftSetPrice->save();

            return [
                'success' => true,
                'message' => 'Xóa giá của quà tặng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removePriceFromGiftSet: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateGiftSetPriceByGiftSet($id, $priceId): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID bộ quà tặng không được để trống',
                ];
            }

            if (empty($priceId)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID giá không được để trống',
                ];
            }

            // Kiểm tra gift set tồn tại và không bị xóa mềm
            $giftSet = (new GiftSet())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$giftSet) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng',
                ];
            }

            // Kiểm tra price tồn tại, thuộc về gift set và không bị xóa mềm
            $giftSetPrice = (new GiftSetPrice())
                ->where('id', $priceId)
                ->where('gift_set_id', $id)
                ->where('deleted', false)
                ->first();

            if (!$giftSetPrice) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy giá của quà tặng',
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(422);
                return [
                    'error' => 'Dữ liệu không hợp lệ'
                ];
            }

            // Không cho phép thay đổi gift_set_id
            unset($data['gift_set_id']);

            // Validate
            $errors = $giftSetPrice->validate($data, true);

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu không hợp lệ',
                    'details' => $errors
                ];
            }

            $giftSetPrice->fill($data);
            $giftSetPrice->save();

            return [
                'success' => true,
                'data' => $giftSetPrice->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateGiftSetPrice: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}