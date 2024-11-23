<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Profile;
use App\Utils\PaginationTrait;
use App\Utils\ShippingCalculator;
use Exception;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class OrderController
{
    use PaginationTrait;

    private $shippingCalculator;

    public function __construct(ShippingCalculator $shippingCalculator)
    {
        $this->shippingCalculator = $shippingCalculator;
    }

    public function getOrders(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $orders = Order::query()
                ->where('status', '!=', 'DELETED')
                ->with(['customer', 'creator', 'orderDetails', 'giftSets'])
                ->orderBy('created_at', 'desc')
                ->orderByRaw("CASE 
                    WHEN status = 'PENDING' THEN 1
                    WHEN status = 'PROCESSED' THEN 2
                    WHEN status = 'SHIPPING' THEN 3
                    WHEN status = 'DELIVERED' THEN 4
                    WHEN status = 'CANCELLED' THEN 5
                    WHEN status = 'RETURNED' THEN 6
                    WHEN status = 'DRAFT' THEN 7
                    ELSE 8
                    END")
                ->orderByRaw("CASE 
                    WHEN payment_status = 'PENDING' THEN 1
                    WHEN payment_status = 'PAID' THEN 2
                    ELSE 3
                    END")
                ->orderByRaw("CASE 
                    WHEN payment_method = 'CASH' THEN 1
                    WHEN payment_method = 'BANK_TRANSFER' THEN 2
                    ELSE 3
                    END");

            // Filter by customer name
            if (isset($_GET['customer_name'])) {
                $customerName = urldecode($_GET['customer_name']);
                $orders->whereHas('customer', function ($query) use ($customerName) {
                    $query->where('name', 'LIKE', '%' . $customerName . '%');
                });
            }

            // Filter by order code
            if (isset($_GET['code'])) {
                $code = urldecode($_GET['code']);
                $orders->where('code', 'LIKE', '%' . $code . '%');
            }

            // Filter by phone
            if (isset($_GET['phone'])) {
                $phone = urldecode($_GET['phone']);
                $orders->where(function ($query) use ($phone) {
                    $query->where('phone', 'LIKE', '%' . $phone . '%')
                        ->orWhere('phone', 'LIKE', $phone . '%')
                        ->orWhere('phone', 'LIKE', '%' . $phone);
                });
            }

            // Filter by order date
            if (isset($_GET['order_date_from']) && isset($_GET['order_date_to'])) {
                $fromDate = date('Y-m-d', strtotime(urldecode($_GET['order_date_from'])));
                $toDate = date('Y-m-d', strtotime(urldecode($_GET['order_date_to'])));
                $orders->whereBetween('order_date', [$fromDate, $toDate]);
            } elseif (isset($_GET['order_date_from'])) {
                $fromDate = date('Y-m-d', strtotime(urldecode($_GET['order_date_from'])));
                $orders->where('order_date', '>=', $fromDate);
            } elseif (isset($_GET['order_date_to'])) {
                $toDate = date('Y-m-d', strtotime(urldecode($_GET['order_date_to'])));
                $orders->where('order_date', '<=', $toDate);
            }

            // Filter by delivery date
            if (isset($_GET['delivery_date_from']) && isset($_GET['delivery_date_to'])) {
                $fromDate = date('Y-m-d', strtotime(urldecode($_GET['delivery_date_from'])));
                $toDate = date('Y-m-d', strtotime(urldecode($_GET['delivery_date_to'])));
                $orders->whereBetween('delivery_date', [$fromDate, $toDate]);
            } elseif (isset($_GET['delivery_date_from'])) {
                $fromDate = date('Y-m-d', strtotime(urldecode($_GET['delivery_date_from'])));
                $orders->where('delivery_date', '>=', $fromDate);
            } elseif (isset($_GET['delivery_date_to'])) {
                $toDate = date('Y-m-d', strtotime(urldecode($_GET['delivery_date_to'])));
                $orders->where('delivery_date', '<=', $toDate);
            }

            // Filter by status
            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $orders->where('status', $status);
            }

            // Keep existing filters
            if (isset($_GET['total_price'])) {
                $total_price = urldecode($_GET['total_price']);
                $orders->where('total_price', $total_price);
            }

            if (isset($_GET['address'])) {
                $address = urldecode($_GET['address']);
                $orders->where('address', 'like', '%' . $address . '%');
            }

            if (isset($_GET['city'])) {
                $city = urldecode($_GET['city']);
                $orders->where('city', 'like', '%' . $city . '%');
            }

            if (isset($_GET['district'])) {
                $district = urldecode($_GET['district']);
                $orders->where('district', 'like', '%' . $district . '%');
            }

            if (isset($_GET['ward'])) {
                $ward = urldecode($_GET['ward']);
                $orders->where('ward', 'like', '%' . $ward . '%');
            }

            return $this->paginateResults($orders, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getOrderByCode($code): array
    {
        try {
            $order = Order::query()->where('code', $code)
                ->with(['customer', 'creator', 'orderDetails.product', 'giftSets'])
                ->first();

            if (!$order) {
                http_response_code(404);
                return ['error' => 'Không tìm thấy'];
            }

            return $order->toArray();
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getOrderDetailByOrder($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $order = Order::query()->where('id', $id)->firstOrFail();
            $orderDetailsQuery = $order->orderDetails()->with(['order', 'product'])->getQuery();

            return $this->paginateResults($orderDetailsQuery, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGiftSetsByOrder($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $order = Order::query()->where('id', $id)->firstOrFail();
            $giftSetsQuery = $order->giftSets()
                ->with(['products', 'prices', 'orders', 'giftSetProducts', 'orderGiftSets'])
                ->getQuery();

            return $this->paginateResults($giftSetsQuery, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getOrderGiftSetsByOrder($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $order = Order::query()->where('id', $id)->firstOrFail();
            $orderGiftSetsQuery = $order->orderGiftSets()
                ->with(['order', 'giftSet'])
                ->getQuery();

            return $this->paginateResults($orderGiftSetsQuery, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteOrder($id): array
    {
        try {
            $order = Order::find($id);

            if ($order) {
                // Cập nhật trạng thái của order
                $order->status = 'CANCELLED';
                $order->deleted = true;
                $order->save();

                // Lấy và cập nhật tất cả order details liên quan
                $orderDetails = OrderDetail::where('order_id', $id)->get();
                foreach ($orderDetails as $detail) {
                    $detail->status = 'INACTIVE';
                    $detail->deleted = true;
                    $detail->save();
                }

                return ["message" => "Xóa thành công"];
            } else {
                http_response_code(404);
                return ["error" => "Không tìm thấy"];
            }
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createOrder()
    {
        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            // Remove fields that should not be passed directly
            unset($data['total_price']);
            unset($data['order_date']);
            unset($data['code']);

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                throw new Exception('Invalid JSON data: ' . json_last_error_msg(), 400);
            }

            // Token validation and profile ID retrieval
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                http_response_code(401);
                throw new Exception('Token không tồn tại', 401);
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $profileId = $parsedToken->claims()->get('profile_id');

            if (!$profileId) {
                http_response_code(401);
                throw new Exception('Profile ID không tồn tại trong token', 401);
            }

            $profile = Profile::where('deleted', false)->find($profileId);

            if (!$profile) {
                http_response_code(404);
                throw new Exception('Profile không tồn tại trong hệ thống', 404);
            }

            // Customer validation
            $customer = Customer::where('deleted', false)->find($data['customer_id']);
            if (!$customer) {
                http_response_code(404);
                throw new Exception('Khách hàng không tồn tại', 404);
            }

            // Payment method validation
            $paymentMethod = $data['payment_method'] ?? null;
            if ($paymentMethod && !in_array($paymentMethod, ['CASH', 'BANK_TRANSFER'])) {
                http_response_code(400);
                throw new Exception('Phương thức thanh toán không hợp lệ', 400);
            }

            // Generate new code for order
            $currentDay = date('d');
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "DH" . $currentDay . $currentMonth . $currentYear;

            // Get latest order code with current prefix
            $latestOrder = Order::query()
                ->where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            if ($latestOrder) {
                $sequence = intval(substr($latestOrder->code, -5)) + 1;
            } else {
                $sequence = 1;
            }

            // Format sequence to 5 digits
            $orderCode = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
            $orderDate = date('Y-m-d H:i:s');

            // Set default values for new fields
            $discountPercent = isset($data['discount_percent']) ? intval($data['discount_percent']) : 0;
            $shippingFee = isset($data['shipping_fee']) ? intval($data['shipping_fee']) : 0;
            $deliveryType = $data['delivery_type'] ?? 'SHIPPING';

            // If store pickup, shipping fee should be 0
            if ($deliveryType === 'STORE_PICKUP') {
                $shippingFee = 0;
            }

            // Prepare order data
            $orderData = [
                'code' => $orderCode,
                'customer_id' => $customer->id,
                'created_by' => $profileId,
                'order_date' => $orderDate,
                'phone' => $data['phone'] ?? $customer->phone,
                'address' => $data['address'] ?? $customer->address,
                'city' => $data['city'] ?? $customer->city,
                'district' => $data['district'] ?? $customer->district,
                'ward' => $data['ward'] ?? $customer->ward,
                'delivery_date' => $data['delivery_date'] ?? date('Y-m-d', strtotime('+3 days')),
                'status' => 'PENDING',
                'payment_status' => 'PENDING',
                'payment_method' => $paymentMethod,
                'discount_percent' => $discountPercent,
                'shipping_fee' => $shippingFee,
                'delivery_type' => $deliveryType,
                'total_price' => 0
            ];

            // Validate order data
            $order = new Order();
            $order->fill($orderData);
            $errors = $order->validate($orderData);

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $order->save();

            // Process order details and calculate total
            $subtotal = 0;

            foreach ($data['products'] as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    http_response_code(404);
                    throw new Exception("Sản phẩm #{$item['product_id']} không tồn tại", 404);
                }

                $currentDate = date('Y-m-d');
                $price = ProductPrice::where('product_id', $product->id)
                    ->where('date_start', '<=', $currentDate)
                    ->where('date_end', '>=', $currentDate)
                    ->where('status', 'ACTIVE')
                    ->orderBy('date_start', 'desc')
                    ->first();

                if (!$price) {
                    http_response_code(400);
                    throw new Exception("Giá sản phẩm #{$product->id} chưa được cập nhật", 400);
                }

                $detailData = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $price->price,
                    'status' => 'ACTIVE',
                ];

                $order->orderDetails()->create($detailData);
                $subtotal += $price->price * $item['quantity'];
            }

            // Calculate total price with discount and shipping fee
            $discountAmount = $subtotal * ($discountPercent / 100);
            $totalPrice = $subtotal - $discountAmount + $shippingFee;

            // Update total price
            $order->total_price = $totalPrice;
            $order->save();

            http_response_code(201);
            return [
                'status' => 'success',
                'message' => 'Tạo đơn hàng thành công',
                'order_id' => $order->id
            ];
        } catch (\Exception $e) {
            error_log("Error in createOrder: " . $e->getMessage());
            $errorCode = $e->getCode() ?: 500;
            http_response_code($errorCode);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateOrder($id)
    {
        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            // Remove fields that should not be updated
            unset($data['total_price']);
            unset($data['order_date']);
            unset($data['code']);

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                throw new Exception('Invalid JSON data: ' . json_last_error_msg(), 400);
            }

            // Token validation and profile ID retrieval
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                http_response_code(401);
                throw new Exception('Token không tồn tại', 401);
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $profileId = $parsedToken->claims()->get('profile_id');

            if (!$profileId) {
                http_response_code(401);
                throw new Exception('Profile ID không tồn tại trong token', 401);
            }

            $profile = Profile::where('deleted', false)->find($profileId);

            if (!$profile) {
                http_response_code(404);
                throw new Exception('Profile không tồn tại trong hệ thống', 404);
            }

            // Find order to update
            $order = Order::where('deleted', false)->find($id);

            if (!$order) {
                http_response_code(404);
                throw new Exception('Đơn hàng không tồn tại', 404);
            }

            // Keep original order date
            $orderDate = $order->order_date;

            // Get updated values
            $discountPercent = isset($data['discount_percent']) ? intval($data['discount_percent']) : $order->discount_percent;
            $shippingFee = isset($data['shipping_fee']) ? intval($data['shipping_fee']) : $order->shipping_fee;
            $deliveryType = $data['delivery_type'] ?? $order->delivery_type;

            // If store pickup, shipping fee should be 0
            if ($deliveryType === 'STORE_PICKUP') {
                $shippingFee = 0;
                $data['shipping_fee'] = 0;
            }

            // Calculate new total price
            $subtotal = 0;
            foreach ($order->orderDetails as $detail) {
                $subtotal += $detail->price * $detail->quantity;
            }

            $discountAmount = $subtotal * ($discountPercent / 100);
            $totalPrice = $subtotal - $discountAmount + $shippingFee;

            // Update order data
            $data['total_price'] = $totalPrice;
            $data['order_date'] = $orderDate;

            // Update order information
            $order->fill($data);

            $errors = $order->validate($order->toArray());

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $order->save();

            http_response_code(200);
            return [
                'status' => 'success',
                'message' => 'Cập nhật đơn hàng thành công',
                'order_id' => $order->id
            ];
        } catch (Exception $e) {
            error_log("ERROR: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            $errorCode = $e->getCode() ?: 500;
            http_response_code($errorCode);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

//    public function createOrder()
//    {
//        try {
//            // 1. Đọc và validate input
//            $rawInput = file_get_contents('php://input');
//            $data = json_decode($rawInput, true);
//
//            if (json_last_error() !== JSON_ERROR_NONE) {
//                http_response_code(400);
//                throw new \Exception('Invalid JSON data: ' . json_last_error_msg(), 400);
//            }
//
//            // Loại bỏ các trường không cho phép gửi trực tiếp
//            unset($data['total_price']);
//            unset($data['order_date']);
//            unset($data['code']);
//
//            // 2. Validate token và lấy profile_id
//            $headers = apache_request_headers();
//            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
//
//            if (!$token) {
//                http_response_code(401);
//                throw new \Exception('Token không tồn tại', 401);
//            }
//
//            $parser = new Parser(new JoseEncoder());
//            $parsedToken = $parser->parse($token);
//            $profileId = $parsedToken->claims()->get('profile_id');
//
//            if (!$profileId) {
//                http_response_code(401);
//                throw new \Exception('Profile ID không tồn tại trong token', 401);
//            }
//
//            // 3. Kiểm tra profile
//            $profile = Profile::where('deleted', false)->find($profileId);
//            if (!$profile) {
//                http_response_code(404);
//                throw new \Exception('Profile không tồn tại trong hệ thống', 404);
//            }
//
//            // 4. Validate customer
//            $customer = Customer::where('deleted', false)->find($data['customer_id']);
//            if (!$customer) {
//                http_response_code(404);
//                throw new \Exception('Khách hàng không tồn tại', 404);
//            }
//
//            // 5. Validate payment method
//            $paymentMethod = $data['payment_method'] ?? null;
//            if ($paymentMethod && !in_array($paymentMethod, ['CASH', 'BANK_TRANSFER'])) {
//                http_response_code(400);
//                throw new \Exception('Phương thức thanh toán không hợp lệ', 400);
//            }
//
//            // 6. Tạo mã đơn hàng
//            $currentDay = date('d');
//            $currentMonth = date('m');
//            $currentYear = date('y');
//            $prefix = "DH" . $currentDay . $currentMonth . $currentYear;
//
//            $latestOrder = Order::query()
//                ->where('code', 'LIKE', $prefix . '%')
//                ->orderBy('code', 'desc')
//                ->first();
//
//            $sequence = $latestOrder ? intval(substr($latestOrder->code, -5)) + 1 : 1;
//            $orderCode = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
//
//            // 7. Set các giá trị mặc định
//            $orderDate = date('Y-m-d H:i:s');
//            $discountPercent = isset($data['discount_percent']) ? intval($data['discount_percent']) : 0;
//            $deliveryType = $data['delivery_type'] ?? 'SHIPPING';
//
//            // 8. Tính phí ship
//            $shippingFee = 0;
//            if ($deliveryType === 'SHIPPING') {
//                try {
//                    $shippingInfo = $this->shippingCalculator->calculateShippingFee(
//                        $data['address'] ?? $customer->address,
//                        $data['ward'] ?? $customer->ward,
//                        $data['district'] ?? $customer->district,
//                        $data['city'] ?? $customer->city
//                    );
//                    $shippingFee = $shippingInfo['shippingFee'];
//                } catch (\Exception $e) {
//                    http_response_code(400);
//                    throw new \Exception('Không thể tính phí ship: ' . $e->getMessage());
//                }
//            }
//
//            // 9. Chuẩn bị dữ liệu đơn hàng
//            $orderData = [
//                'code' => $orderCode,
//                'customer_id' => $customer->id,
//                'created_by' => $profileId,
//                'order_date' => $orderDate,
//                'phone' => $data['phone'] ?? $customer->phone,
//                'address' => $data['address'] ?? $customer->address,
//                'city' => $data['city'] ?? $customer->city,
//                'district' => $data['district'] ?? $customer->district,
//                'ward' => $data['ward'] ?? $customer->ward,
//                'delivery_date' => $data['delivery_date'] ?? date('Y-m-d', strtotime('+3 days')),
//                'status' => 'PENDING',
//                'payment_status' => 'PENDING',
//                'payment_method' => $paymentMethod,
//                'discount_percent' => $discountPercent,
//                'shipping_fee' => $shippingFee,
//                'delivery_type' => $deliveryType,
//                'total_price' => 0
//            ];
//
//            // 10. Validate đơn hàng
//            $order = new Order();
//            $order->fill($orderData);
//            $errors = $order->validate($orderData);
//
//            if ($errors) {
//                http_response_code(422);
//                return [
//                    'success' => false,
//                    'error' => 'Validation failed',
//                    'details' => $errors
//                ];
//            }
//
//            // 11. Lưu đơn hàng
//            $order->save();
//
//            // 12. Xử lý chi tiết đơn hàng và tính tổng tiền
//            $subtotal = 0;
//            foreach ($data['products'] as $item) {
//                $product = Product::find($item['product_id']);
//                if (!$product) {
//                    http_response_code(404);
//                    throw new \Exception("Sản phẩm #{$item['product_id']} không tồn tại", 404);
//                }
//
//                // Lấy giá hiện tại của sản phẩm
//                $currentDate = date('Y-m-d');
//                $price = ProductPrice::where('product_id', $product->id)
//                    ->where('date_start', '<=', $currentDate)
//                    ->where('date_end', '>=', $currentDate)
//                    ->where('status', 'ACTIVE')
//                    ->orderBy('date_start', 'desc')
//                    ->first();
//
//                if (!$price) {
//                    http_response_code(400);
//                    throw new \Exception("Giá sản phẩm #{$product->id} chưa được cập nhật", 400);
//                }
//
//                // Tạo chi tiết đơn hàng
//                $detailData = [
//                    'product_id' => $item['product_id'],
//                    'quantity' => $item['quantity'],
//                    'price' => $price->price,
//                    'status' => 'ACTIVE',
//                ];
//
//                $order->orderDetails()->create($detailData);
//                $subtotal += $price->price * $item['quantity'];
//            }
//
//            // 13. Tính tổng tiền cuối cùng
//            $discountAmount = $subtotal * ($discountPercent / 100);
//            $totalPrice = $subtotal - $discountAmount + $shippingFee;
//
//            // 14. Cập nhật tổng tiền
//            $order->total_price = $totalPrice;
//            $order->save();
//
//            // 15. Trả về kết quả
//            http_response_code(201);
//            return [
//                'status' => 'success',
//                'message' => 'Tạo đơn hàng thành công',
//                'data' => [
//                    'order_id' => $order->id,
//                    'code' => $order->code,
//                    'subtotal' => $subtotal,
//                    'discount_amount' => $discountAmount,
//                    'shipping_fee' => $shippingFee,
//                    'total_price' => $totalPrice,
//                    'shipping_info' => $deliveryType === 'SHIPPING' ? [
//                        'distance' => $shippingInfo['distance'] ?? null,
//                        'is_inner_city' => $shippingInfo['isInnerCity'] ?? null,
//                    ] : null
//                ]
//            ];
//
//        } catch (\Exception $e) {
//            error_log("Error in createOrder: " . $e->getMessage());
//            $errorCode = $e->getCode() ?: 500;
//            http_response_code($errorCode);
//            return [
//                'success' => false,
//                'error' => 'Error occurred',
//                'message' => $e->getMessage()
//            ];
//        }
//    }
}