<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Profile;
use App\Utils\PaginationTrait;
use Exception;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class OrderController
{
    use PaginationTrait;

    public function getOrders(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $orders = Order::query()
                ->where('status', '!=', 'DELETED')
                ->with(['customer', 'creator', 'orderDetails', 'giftSets'])
                ->orderByRaw("CASE 
            WHEN status = 'PROCESSED' THEN 1
            WHEN status = 'DELIVERED' THEN 2
            WHEN status = 'SHIPPING' THEN 3
            WHEN status = 'PENDING' THEN 4
            WHEN status = 'CANCELLED' THEN 5
            WHEN status = 'RETURNED' THEN 6
            WHEN status = 'DRAFT' THEN 7
            ELSE 8
            END")
                ->orderByRaw("CASE 
            WHEN payment_status = 'PAID' THEN 1
            WHEN payment_status = 'PENDING' THEN 2
            ELSE 3
            END")
                ->orderByRaw("CASE 
            WHEN payment_method = 'CASH' THEN 1
            WHEN payment_method = 'BANK_TRANSFER' THEN 2
            ELSE 3
            END")
                ->orderBy('created_at', 'desc');

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

    public function getOrderById($id): array
    {
        try {
            $order = Order::query()->where('id', $id)
                ->with(['customer', 'creator', 'orderDetails', 'giftSets'])
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

    public function updateOrderById($id): array
    {
        try {
            $order = Order::find($id);

            if (!$order) {
                http_response_code(404);
                return ["error" => "Order not found"];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $error = $order->validate($data, true);

            if ($error != "") {
                http_response_code(400);
                error_log($error);
                return ["error" => $error];
            }

            $order->fill($data);
            $order->save();

            return $order->toArray();
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
                $order->status = 'DELETED';
                $order->save();
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
            unset($data['code']); // Remove code if provided by user

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON data: ' . json_last_error_msg(), 400);
            }

            // Token validation and profile ID retrieval
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                throw new Exception('Token không tồn tại', 401);
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $profileId = $parsedToken->claims()->get('profile_id');

            if (!$profileId) {
                throw new Exception('Profile ID không tồn tại trong token', 401);
            }

            $profile = Profile::where('deleted', false)->find($profileId);

            if (!$profile) {
                throw new Exception('Profile không tồn tại trong hệ thống', 404);
            }

            // Customer validation
            $customer = Customer::where('deleted', false)->find($data['customer_id']);
            if (!$customer) {
                throw new Exception('Khách hàng không tồn tại', 404);
            }

            // Payment method validation
            $paymentMethod = $data['payment_method'] ?? null;
            if ($paymentMethod && !in_array($paymentMethod, ['CASH', 'BANK_TRANSFER'])) {
                throw new Exception('Phương thức thanh toán không hợp lệ', 400);
            }

            // Generate new code for order
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "DH" . $currentMonth . $currentYear;

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
            $orderDate = date('Y-m-d');

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
                'total_price' => 0
            ];

            // Validate order data
            $order = new Order();
            $order->fill($orderData);
            $errors = $order->validate($orderData);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $order->save();

            // Process order details
            $totalPrice = 0;

            foreach ($data['products'] as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
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
                    throw new Exception("Giá sản phẩm #{$product->id} chưa được cập nhật", 400);
                }

                $detailData = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $price->price,
                    'status' => 'ACTIVE',
                ];

                $order->orderDetails()->create($detailData);
                $totalPrice += $price->price * $item['quantity'];
            }

            // Update total price
            $order->total_price = $totalPrice;
            $order->save();

            return [
                'status' => 'success',
                'message' => 'Tạo đơn hàng thành công',
                'order_id' => $order->id
            ];
        } catch (\Exception $e) {
            error_log("Error in createOrder: " . $e->getMessage());
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
            unset($data['code']); // Remove code to prevent modification

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON data: ' . json_last_error_msg(), 400);
            }

            // Token validation and profile ID retrieval
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                throw new Exception('Token không tồn tại', 401);
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $profileId = $parsedToken->claims()->get('profile_id');

            if (!$profileId) {
                throw new Exception('Profile ID không tồn tại trong token', 401);
            }

            $profile = Profile::where('deleted', false)->find($profileId);

            if (!$profile) {
                throw new Exception('Profile không tồn tại trong hệ thống', 404);
            }

            // Find order to update
            $order = Order::where('deleted', false)->find($id);

            if (!$order) {
                throw new Exception('Đơn hàng không tồn tại', 404);
            }

            // Keep original order date and code
            $orderDate = $order->order_date;

            // Update order information
            $order->fill($data);
            $order->order_date = $orderDate; // Ensure order date remains unchanged

            $errors = $order->validate($order->toArray());

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }
            $order->save();

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
}