<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\Parser;

class OrderController
{
    public function getOrders(): Collection
    {
        $orders = Order::query()->where('status', '!=' , 'DELETED');

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

        $orders = $orders->get();
        foreach ($orders as $index => $order) {
            $customer = Customer::query()->where('id',$order->customer_id)->first();
            unset($order->customer_id);
            $order->customer = $customer;

            $profile = Profile::query()->where('id', $order->created_by)->first();
            unset($order->created_by);
            $order->profile = $profile;
        }
        return $orders;
    }

    public function getOrderDetails(): Collection
    {
        $order_details = OrderDetail::query()->where('status', '!=' , 'DISABLE');

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $order_details->where('status', $status);
        }

        if (isset($_GET['price'])) {
            $price = urldecode($_GET['price']);
            $order_details->where('price', $price);
        }

        if (isset($_GET['quantity'])) {
            $quantity = urldecode($_GET['quantity']);
            $order_details->where('quantity', $quantity);
        }

        return $order_details->get();
    }

    public function getOrderById($id) : ?Model
    {
        $order = Order::query()->where('id', $id)->first();

        if (!$order) {
            return null;
        }
        $customer = Customer::query()->where('id', $order->customer_id)->first();
        $profile = Profile::query()->where('id', $order->created_by)->first();
        unset($order->customer_id, $order->created_by);
        $order->customer = $customer;
        $order->profile = $profile;
        return $order;
    }

    public function getProductByOrder($id)
    {
        $order = Order::query()->where('id',$id)->first();
        return $order->products;
    }

    public function addProductToOrder($id)
    {
        $order = Order::query()->where('id',$id)->first();
        $data = json_decode(file_get_contents('php://input'),true);
        $product = Product::query()->where('id',$data['product_id'])->first();
        $order->products()->attach($product);
        return 'Thêm thành công';
    }

    public function getOrderDetailByOrder($id)
    {
        $orderDetails = OrderDetail::query()->where('order_id', $id)->get();
        return $orderDetails;
    }

//    public function createOrder(): Model | string
//    {
//        $data = json_decode(file_get_contents('php://input'), true);
//        $order = new Order();
//        $error = $order->validate($data);
//        if ($error != "") {
//            http_response_code(404);
//            error_log($error);
//            return json_encode(["error" => $error]);
//        }
//        $order->fill($data);
//        $order->save();
//        return $order;
//    }

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
        $data = json_decode(file_get_contents('php://input'), true);

        // Xử lý khách hàng (tạo mới hoặc cập nhật)
        $customer = $this->handleCustomer($data);

        // Kiểm tra khách hàng tồn tại
        if (!$customer) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Khách hàng không tồn tại']);
            return;
        }

        $headers = apache_request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

        if (!$token) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Token không tồn tại'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);

            $profileId = $parsedToken->claims()->get('profile_id');
            if (!$profileId) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Profile ID không tồn tại trong token'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $order = Order::create([
                'customer_id' => $customer->id, // Sử dụng id của khách hàng mới được tạo
                'created_by' => $profileId,
                'phone' => $data['phone'],
                'address' => $data['address'],
                'city' => $data['city'],
                'district' => $data['district'],
                'ward' => $data['ward'],
            ]);

            $orderDetails = $data['order_details'] ?? [];
            $totalPrice = 0;

            foreach ($orderDetails as $orderDetail) {
                $productExists = Product::where('id', $orderDetail['product_id'])->exists();
                if (!$productExists) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Sản phẩm không tồn tại']);
                    return;
                }

                $price = $orderDetail['price'];
                $quantity = $orderDetail['quantity'];
                $totalPrice += $price * $quantity;

                $order->orderDetails()->create([
                    'product_id' => $orderDetail['product_id'],
                    'quantity' => $quantity,
                    'price' => $price,
                ]);
            }

            $order->total_price = $totalPrice;
            $order->save();

            header('Content-Type: application/json');
            echo json_encode(['message' => 'Tạo đơn hàng thành công']);
        } catch (CannotDecodeContent|InvalidTokenStructure|UnsupportedHeaderFound $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Token không hợp lệ'], JSON_UNESCAPED_UNICODE);
            return;
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