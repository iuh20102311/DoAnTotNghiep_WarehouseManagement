<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\GroupCustomer;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CustomerController
{
    use PaginationTrait;

    public function getCustomers(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $customer = Customer::with('groupCustomer')
            ->where('status', '!=', 'DELETED')
            ->where('deleted', false)->with(['groupCustomer', 'orders']);

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $customer->where('status', $status);
        }

        if (isset($_GET['name'])) {
            $name = urldecode($_GET['name']);
            $customer->where('name', 'like', $name . '%');
        }

        if (isset($_GET['gender'])) {
            $gender = urldecode($_GET['gender']);
            $customer->where('gender', $gender);
        }

        if (isset($_GET['email'])) {
            $email = urldecode($_GET['email']);
            $customer->where('email', 'like', $email . '%');
        }

        if (isset($_GET['phone'])) {
            $phone = urldecode($_GET['phone']);
            $length = strlen($phone);
            $customer->whereRaw('SUBSTRING(phone, 1, ?) = ?', [$length, $phone]);
        }

        if (isset($_GET['address'])) {
            $address = urldecode($_GET['address']);
            $customer->where('address', 'like', '%' . $address . '%');
        }

        if (isset($_GET['city'])) {
            $city = urldecode($_GET['city']);
            $customer->where('city', 'like', '%' . $city . '%');
        }

        if (isset($_GET['district'])) {
            $district = urldecode($_GET['district']);
            $customer->where('district', 'like', '%' . $district . '%');
        }

        if (isset($_GET['ward'])) {
            $ward = urldecode($_GET['ward']);
            $customer->where('ward', 'like', '%' . $ward . '%');
        }

        return $this->paginateResults($customer, $perPage, $page)->toArray();
    }

    public function getCustomerById($id): string
    {
        $customer = Customer::query()->where('id', $id)->with(['groupCustomer', 'orders'])
            ->first();

        if (!$customer) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($customer->toArray());
    }

    public function getOrderByCustomer($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $customer = Customer::query()->where('id', $id)->firstOrFail();
        $ordersQuery = $customer->orders()->with(['customer','creator'])->getQuery();

        return $this->paginateResults($ordersQuery, $perPage, $page)->toArray();
    }

    public function getGroupCustomerByCustomer($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $customer = Customer::query()->where('id', $id)->firstOrFail();
        $groupCustomersQuery = $customer->groupCustomer()->with(['customers'])->getQuery();

        return $this->paginateResults($groupCustomersQuery, $perPage, $page)->toArray();
    }

    public function createCustomer()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $customer = new Customer();
        $errors = $customer->validate($data);

        if ($errors) {
            http_response_code(422);
            return json_encode(["errors" => $errors]);
        }

        $customer->fill($data);
        $customer->save();

        http_response_code(201);
        return json_encode($customer);
    }

    public function updateCustomerById($id): bool|int|string
    {
        $customer = Customer::find($id);

        if (!$customer) {
            http_response_code(404);
            return json_encode(["error" => "Customer not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $errors = $customer->validate($data, true);

        if ($errors) {
            http_response_code(422);
            return json_encode(["errors" => $errors]);
        }

        $customer->fill($data);
        $customer->save();

        http_response_code(200);
        return json_encode($customer);
    }

    public function deleteCustomer($id)
    {
        $customer = Customer::find($id);

        if ($customer) {
            $customer->status = 'DELETED';
            $customer->save();
            return "Xóa thành công";
        } else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}