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
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $orders = Order::query()->where('status', '!=', 'DELETED')
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

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $orders->where('status', $status);
            }

            if (isset($_GET['total_price'])) {
                $total_price = urldecode($_GET['total_price']);
                $orders->where('total_price', $total_price);
            }

            if (isset($_GET['phone'])) {
                $phone = urldecode($_GET['phone']);
                $length = strlen($phone);
                $orders->whereRaw('SUBSTRING(phone, 1, ?) = ?', [$length, $phone]);
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
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

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
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

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
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

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

//    public function createOrder()
//    {
//        $rawInput = file_get_contents('php://input');
//
//        // Decode JSON và kiểm tra lỗi
//        $data = json_decode($rawInput, true);
//        if (json_last_error() !== JSON_ERROR_NONE) {
//            error_log("JSON decode error: " . json_last_error_msg());
//            header('Content-Type: application/json');
//            echo json_encode(['error' => 'Invalid JSON data: ' . json_last_error_msg()]);
//            return;
//        }
//
//        try {
//            // Xử lý khách hàng
//            $customer = $this->handleCustomer($data);
//
//            if (!$customer) {
//                throw new Exception('Không thể tạo hoặc cập nhật khách hàng');
//            }
//
//            // Xác thực token
//            $headers = apache_request_headers();
//            error_log("Headers: " . print_r($headers, true));
//
//            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
//            if (!$token) {
//                throw new Exception('Token không tồn tại');
//            }
//
//            // Parse token
//            $parser = new Parser(new JoseEncoder());
//            $parsedToken = $parser->parse($token);
//            $profileId = $parsedToken->claims()->get('profile_id');
//
//            if (!$profileId) {
//                throw new Exception('Profile ID không tồn tại trong token');
//            }
//
//            $profile = Profile::find($profileId);
//            if (!$profile) {
//                throw new Exception('Profile không tồn tại trong hệ thống');
//            }
//
//            // Tạo đơn hàng
//            $orderData = [
//                'customer_id' => $customer->id,
//                'created_by' => $profileId,
//                'phone' => $data['phone'],
//                'address' => $data['address'],
//                'city' => $data['city'],
//                'district' => $data['district'],
//                'ward' => $data['ward'],
//                'delivery_date' => $data['delivery_date'] ?? date('Y-m-d', strtotime('+3 days')),
//                'status' => 'PENDING',
//                'total_price' => 0
//            ];
//
//            $order = Order::create($orderData);
//
//            // Xử lý chi tiết đơn hàng
//            $totalPrice = 0;
//
//            foreach ($data['order_details'] as $detail) {
//
//                $product = Product::find($detail['product_id']);
//                if (!$product) {
//                    throw new Exception("Sản phẩm #{$detail['product_id']} không tồn tại");
//                }
//
//                $detailData = [
//                    'product_id' => $detail['product_id'],
//                    'quantity' => $detail['quantity'],
//                    'price' => $detail['price'],
//                    'packaging_type' => $detail['packaging_type'] ?? 'PLASTIC_JAR',
//                    'packaging_weight' => $detail['packaging_weight'] ?? 0,
//                    'status' => 'ACTIVE',
//                    'note' => $detail['note'] ?? null
//                ];
//
//                $order->orderDetails()->create($detailData);
//                $totalPrice += $detail['price'] * $detail['quantity'];
//            }
//
//            // Cập nhật tổng tiền
//            $order->total_price = $totalPrice;
//            $order->save();
//
//            header('Content-Type: application/json');
//            echo json_encode([
//                'status' => 'success',
//                'message' => 'Tạo đơn hàng thành công',
//                'order_id' => $order->id
//            ], JSON_UNESCAPED_UNICODE);
//
//        } catch (Exception $e) {
//            error_log("ERROR: " . $e->getMessage());
//            error_log("Stack trace: " . $e->getTraceAsString());
//
//            header('Content-Type: application/json');
//            echo json_encode([
//                'status' => 'error',
//                'message' => $e->getMessage()
//            ], JSON_UNESCAPED_UNICODE);
//        }
//    }

    public function createOrder()
    {
        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON data: ' . json_last_error_msg());
            }

            // Xác thực token và lấy profile ID
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                throw new Exception('Token không tồn tại');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $profileId = $parsedToken->claims()->get('profile_id');

            if (!$profileId) {
                throw new Exception('Profile ID không tồn tại trong token');
            }

            $profile = Profile::find($profileId);

            if (!$profile) {
                throw new Exception('Profile không tồn tại trong hệ thống');
            }

            // Lấy thông tin khách hàng
            $customer = Customer::find($data['customer_id']);

            if (!$customer) {
                throw new Exception('Khách hàng không tồn tại');
            }

            // Kiểm tra phương thức thanh toán
            $paymentMethod = $data['payment_method'] ?? null;
            if ($paymentMethod && !in_array($paymentMethod, ['CASH', 'BANK_TRANSFER'])) {
                throw new Exception('Phương thức thanh toán không hợp lệ');
            }

            // Tạo đơn hàng
            $orderData = [
                'customer_id' => $customer->id,
                'created_by' => $profileId,
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

            $order = Order::create($orderData);

            // Xử lý chi tiết đơn hàng
            $totalPrice = 0;

            foreach ($data['products'] as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    throw new Exception("Sản phẩm #{$item['product_id']} không tồn tại");
                }

                $currentDate = date('Y-m-d');
                $price = ProductPrice::where('product_id', $product->id)
                    ->where('date_start', '<=', $currentDate)
                    ->where('date_end', '>=', $currentDate)
                    ->where('status', 'ACTIVE')
                    ->orderBy('date_start', 'desc')
                    ->first();

                if (!$price) {
                    throw new Exception("Giá sản phẩm #{$product->id} chưa được cập nhật");
                }

                $detailData = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $price->price,
                    'packaging_type' => $product->packing,
                    'packaging_weight' => $product->weight,
                    'status' => 'ACTIVE',
                ];

                $order->orderDetails()->create($detailData);
                $totalPrice += $price->price * $item['quantity'];
            }

            // Cập nhật tổng tiền
            $order->total_price = $totalPrice;
            $order->save();

            return [
                'status' => 'success',
                'message' => 'Tạo đơn hàng thành công',
                'order_id' => $order->id
            ];
        } catch (Exception $e) {
            error_log("ERROR: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

//    private function handleCustomer($data)
//    {
//        $customerData = [
//            'group_customer_id' => $data['group_customer_id'] ?? null,
//            'name' => $data['name'],
//            'gender' => $data['gender'] ?? null,
//            'phone' => $data['phone'],
//            'address' => $data['address'],
//            'city' => $data['city'],
//            'district' => $data['district'],
//            'ward' => $data['ward'],
//        ];
//
//        // Kiểm tra khách hàng đã tồn tại dựa trên số điện thoại
//        $customer = Customer::where('phone', $data['phone'])->first();
//
//        if ($customer) {
//            // Nếu khách hàng đã tồn tại, cập nhật thông tin
//            $customer->update($customerData);
//        } else {
//            // Nếu khách hàng chưa tồn tại, tạo mới
//            $customer = Customer::create($customerData);
//        }
//
//        return $customer;
//    }
}