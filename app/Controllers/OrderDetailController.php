<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Profile;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\Parser;

class OrderDetailController
{
    use PaginationTrait;

    public function getOrderDetails(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $orders = Order::query()->where('status', '!=' , 'DELETED')->with(['customer', 'creator','orderDetails','giftSets']);

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

    public function getOrderDetailById($id) : false|string
    {
        $order = Order::query()->where('id', $id)
            ->with(['customer', 'creator','orderDetails','giftSets'])
            ->first();

        if (!$order) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($order->toArray());
    }
}