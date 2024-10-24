<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
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
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $orders = Order::query()->where('status', '!=' , 'DELETED')
            ->with(['customer', 'creator','orderDetails','giftSets']);

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
    }

    public function getOrderById($id) : false|string
    {
        $order = Order::query()->where('id', $id)
            ->with(['customer', 'creator','orderDetails','giftSets'])
            ->first();

        if (!$order) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($order->toArray());
    }

    public function getOrderDetailByOrder($id)
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $order = Order::query()->where('id', $id)->firstOrFail();
        $orderDetailsQuery = $order->orderDetails()->with(['order','product'])->getQuery();

        return $this->paginateResults($orderDetailsQuery, $perPage, $page)->toArray();
    }

    public function getGiftSetsByOrder($id)
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $order = Order::query()->where('id', $id)->firstOrFail();
        $giftSetsQuery = $order->giftSets()
            ->with(['products','prices','orders','giftSetProducts','orderGiftSets'])
            ->getQuery();

        return $this->paginateResults($giftSetsQuery, $perPage, $page)->toArray();
    }

    public function getOrderGiftSetsByOrder($id)
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $order = Order::query()->where('id', $id)->firstOrFail();
        $orderGiftSetsQuery = $order->orderGiftSets()
            ->with(['order','giftSet'])
            ->getQuery();

        return $this->paginateResults($orderGiftSetsQuery, $perPage, $page)->toArray();
    }

    public function updateOrderById($id): bool | int | string
    {
        $order = Order::find($id);

        if (!$order) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $order->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $order->fill($data);
        $order->save();

        return $order;
    }

    public function deleteOrder($id)
    {
        $order = Order::find($id);

        if ($order) {
            $order->status = 'DELETED';
            $order->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }

    public function createOrder()
    {
        $rawInput = file_get_contents('php://input');

        // Decode JSON và kiểm tra lỗi
        $data = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid JSON data: ' . json_last_error_msg()]);
            return;
        }

        try {
            // Xử lý khách hàng
            $customer = $this->handleCustomer($data);

            if (!$customer) {
                throw new Exception('Không thể tạo hoặc cập nhật khách hàng');
            }

            // Xác thực token
            $headers = apache_request_headers();
            error_log("Headers: " . print_r($headers, true));

            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            if (!$token) {
                throw new Exception('Token không tồn tại');
            }

            // Parse token
            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $profileId = $parsedToken->claims()->get('profile_id');

            if (!$profileId) {
                throw new Exception('Profile ID không tồn tại trong token');
            }
            error_log("Profile ID from token: " . $profileId);

            // Tạo đơn hàng
            $orderData = [
                'customer_id' => $customer->id,
                'created_by' => $profileId,
                'phone' => $data['phone'],
                'address' => $data['address'],
                'city' => $data['city'],
                'district' => $data['district'],
                'ward' => $data['ward'],
                'delivery_date' => $data['delivery_date'] ?? date('Y-m-d', strtotime('+3 days')),
                'status' => 'PROCESSING',
                'total_price' => 0
            ];

            $order = Order::create($orderData);

            // Xử lý chi tiết đơn hàng
            $totalPrice = 0;

            foreach ($data['order_details'] as $detail) {

                $product = Product::find($detail['product_id']);
                if (!$product) {
                    throw new Exception("Sản phẩm #{$detail['product_id']} không tồn tại");
                }

                $detailData = [
                    'product_id' => $detail['product_id'],
                    'quantity' => $detail['quantity'],
                    'price' => $detail['price'],
                    'packaging_type' => $detail['packaging_type'] ?? 'PLASTIC_JAR',
                    'packaging_weight' => $detail['packaging_weight'] ?? 0,
                    'status' => 'ACTIVE',
                    'note' => $detail['note'] ?? null
                ];

                $order->orderDetails()->create($detailData);
                $totalPrice += $detail['price'] * $detail['quantity'];
            }

            // Cập nhật tổng tiền
            $order->total_price = $totalPrice;
            $order->save();

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Tạo đơn hàng thành công',
                'order_id' => $order->id
            ], JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            error_log("ERROR: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function handleCustomer($data)
    {
        $customerData = [
            'group_customer_id' => $data['group_customer_id'] ?? null,
            'name' => $data['name'],
            'gender' => $data['gender'] ?? null,
            'phone' => $data['phone'],
            'address' => $data['address'],
            'city' => $data['city'],
            'district' => $data['district'],
            'ward' => $data['ward'],
        ];

        // Kiểm tra khách hàng đã tồn tại dựa trên số điện thoại
        $customer = Customer::where('phone', $data['phone'])->first();

        if ($customer) {
            // Nếu khách hàng đã tồn tại, cập nhật thông tin
            $customer->update($customerData);
        } else {
            // Nếu khách hàng chưa tồn tại, tạo mới
            $customer = Customer::create($customerData);
        }

        return $customer;
    }
}