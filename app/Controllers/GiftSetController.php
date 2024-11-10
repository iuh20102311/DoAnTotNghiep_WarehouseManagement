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

            return $this->paginateResults($giftSet, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGiftSetById($id): array
    {
        try {
            $giftSet = (new GiftSet())
                ->where('deleted', false)
                ->with(['products', 'prices'])
                ->find($id);

            if (!$giftSet) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            return $giftSet->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
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
            $giftSet = new GiftSet();
            $errors = $giftSet->validate($data);

            if ($errors) {
                return [
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $giftSet->fill($data);
            $giftSet->save();

            return [
                'data' => $giftSet->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateGiftSetById($id): array
    {
        try {
            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $giftSet->validate($data, true);

            if ($errors) {
                return [
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $giftSet->fill($data);
            $giftSet->save();

            return [
                'data' => $giftSet->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteGiftSet($id): array
    {
        try {
            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $giftSet->deleted = true;
            $giftSet->save();

            return [
                'message' => 'Xóa thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductsByGiftSet($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $giftSet = GiftSet::query()->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$giftSet) {
                return [

                    'error' => 'Không tìm thấy'
                ];
            }

            $productsQuery = $giftSet->products()
                ->where('products.deleted', false)
                ->with(['categories', 'discounts', 'prices', 'storageLocations', 'orderDetails'])
                ->getQuery();

            return $this->paginateResults($productsQuery, $perPage, $page)->toArray();


        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
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
                return [
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Validate input
            if (empty($data['product_id']) || empty($data['quantity'])) {
                return [
                    'error' => 'Thiếu thông tin sản phẩm hoặc số lượng'
                ];
            }

            $product = (new Product())->find($data['product_id']);

            if (!$product) {
                return [
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            // Attach với pivot quantity
            $giftSet->products()->attach($product->id, ['quantity' => $data['quantity']]);

            return [
                'message' => 'Thêm sản phẩm vào quà tặng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
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
                return [
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['product_id'])) {
                return [
                    'error' => 'Thiếu thông tin sản phẩm'
                ];
            }

            $product = (new Product())->find($data['product_id']);

            if (!$product) {
                return [
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            // Detach product khỏi gift set
            $giftSet->products()->detach($product->id);

            return [
                'message' => 'Xóa sản phẩm khỏi quà tặng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
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
                return [
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['product_id']) || !isset($data['quantity'])) {
                return [
                    'error' => 'Thiếu thông tin sản phẩm hoặc số lượng'
                ];
            }

            $product = (new Product())->find($data['product_id']);

            if (!$product) {
                return [
                    'error' => 'Không tìm thấy sản phẩm'
                ];
            }

            // Update quantity trong pivot table
            $giftSet->products()->updateExistingPivot($product->id, [
                'quantity' => $data['quantity']
            ]);

            return [
                'message' => 'Cập nhật số lượng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getOrdersByGiftSet($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $giftSet = GiftSet::query()->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$giftSet) {
                return [

                    'error' => 'Không tìm thấy'
                ];
            }

            $ordersQuery = $giftSet->orders()
                ->where('orders.deleted', false)
                ->with(['customer', 'creator', 'orderDetails'])
                ->getQuery();

            return $this->paginateResults($ordersQuery, $perPage, $page)->toArray();


        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
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
                return [
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['order_id']) || empty($data['quantity']) || empty($data['price'])) {
                return [
                    'error' => 'Thiếu thông tin đơn hàng, số lượng hoặc giá'
                ];
            }

            $order = (new Order())->find($data['order_id']);

            if (!$order) {
                return [
                    'error' => 'Không tìm thấy đơn hàng'
                ];
            }

            // Dùng attach thêm trực tiếp vào bảng pivot
            $giftSet->orders()->attach($data['order_id'], [
                'quantity' => $data['quantity'],
                'price' => $data['price']
            ]);

            return [
                'message' => 'Thêm quà tặng vào đơn hàng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeOrderFromGiftSet($id): array
    {
        try {
            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                return [
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['order_id'])) {
                return [
                    'error' => 'Thiếu thông tin đơn hàng'
                ];
            }

            $order = (new Order())->find($data['order_id']);

            if (!$order) {
                return [
                    'error' => 'Không tìm thấy đơn hàng'
                ];
            }

            // Dùng detach xóa quan hệ trong bảng pivot
            $giftSet->orders()->detach($data['order_id']);

            return [
                'message' => 'Xóa quà tặng khỏi đơn hàng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateOrderByGiftSet($id): array
    {
        try {
            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                return [
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['order_id']) || !isset($data['quantity']) || !isset($data['price'])) {
                return [
                    'error' => 'Thiếu thông tin cập nhật'
                ];
            }

            $order = (new Order())->find($data['order_id']);

            if (!$order) {
                return [
                    'error' => 'Không tìm thấy đơn hàng'
                ];
            }

            // Dùng updateExistingPivot để cập nhật thông tin trong bảng pivot
            $giftSet->orders()->updateExistingPivot($data['order_id'], [
                'quantity' => $data['quantity'],
                'price' => $data['price']
            ]);

            return [
                'message' => 'Cập nhật thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGiftSetPricesByGiftSet($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $giftSet = (new GiftSet())->where('deleted', false)->find($id);

            if (!$giftSet) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $pricesQuery = $giftSet->prices()
                ->where('deleted', false)
                ->getQuery();

            $result = $this->paginateResults($pricesQuery, $perPage, $page);

            return [
                'data' => $result->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getPricesByGiftSet: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addGiftSetPricesToGiftSet($id): array
    {
        try {
            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                return [
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $data['gift_set_id'] = $id;

            // Tạo instance mới của GiftSetPrice
            $giftSetPrice = new GiftSetPrice();

            // Validate input
            $errors = $giftSetPrice->validate($data);

            if ($errors) {
                return [
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $giftSetPrice->fill($data);
            $giftSetPrice->save();

            return [
                'message' => 'Thêm giá cho quà tặng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in addPriceToGiftSet: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeGiftSetPricesFromGiftSet($id, $priceId): array
    {
        try {
            $giftSet = (new GiftSet())->find($id);

            if (!$giftSet) {
                return [
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $giftSetPrice = (new GiftSetPrice())
                ->where('gift_set_id', $id)
                ->where('id', $priceId)
                ->first();

            if (!$giftSetPrice) {
                return [
                    'error' => 'Không tìm thấy giá của quà tặng'
                ];
            }

            // Soft delete
            $giftSetPrice->deleted = true;
            $giftSetPrice->save();

            return [
                'message' => 'Xóa giá của quà tặng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removePriceFromGiftSet: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateGiftSetPriceByGiftSet($id, $priceId): array
    {
        try {
            // Kiểm tra gift set tồn tại và không bị xóa mềm
            $giftSet = (new GiftSet())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$giftSet) {
                return [
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            // Kiểm tra price tồn tại, thuộc về gift set và không bị xóa mềm
            $giftSetPrice = (new GiftSetPrice())
                ->where('id', $priceId)
                ->where('gift_set_id', $id)
                ->where('deleted', false)
                ->first();

            if (!$giftSetPrice) {
                return [
                    'error' => 'Không tìm thấy giá của quà tặng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                return [
                    'error' => 'Dữ liệu không hợp lệ'
                ];
            }

            // Không cho phép thay đổi gift_set_id
            unset($data['gift_set_id']);

            // Validate
            $errors = $giftSetPrice->validate($data, true);

            if ($errors) {
                return [
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $giftSetPrice->fill($data);
            $giftSetPrice->save();

            return [
                'data' => $giftSetPrice->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateGiftSetPrice: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}