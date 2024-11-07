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
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $customer = Customer::with('groupCustomer')
                ->where('deleted', false)
                ->with(['groupCustomer', 'orders'])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
                ->orderBy('created_at', 'desc');

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

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $customer->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $customer->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $customer->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $customer->where('updated_at', '<=', $updatedTo);
            }

            return $this->paginateResults($customer, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            error_log("Error in getCustomers: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCustomerById($id): array
    {
        try {
            $customer = Customer::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->with(['groupCustomer', 'orders'])
                ->first();

            if (!$customer) {
                return [
                    'error' => 'Không tìm thấy khách hàng'
                ];
            }

            return $customer->toArray();
        } catch (\Exception $e) {
            error_log("Error in getCustomerById: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getOrderByCustomer($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $customer = Customer::query()->where('id', $id)->firstOrFail();
            $ordersQuery = $customer->orders()
                ->where('orders.deleted', false)
                ->with(['customer','creator'])
                ->getQuery();

            return $this->paginateResults($ordersQuery, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            error_log("Error in getOrderByCustomer: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGroupCustomerByCustomer($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $customer = Customer::query()->where('id', $id)->firstOrFail();
            $groupCustomersQuery = $customer->groupCustomer()
                ->where('group_customers.deleted', false)
                ->with(['customers'])
                ->getQuery();

            return $this->paginateResults($groupCustomersQuery, $perPage, $page)->toArray();
        } catch (\Exception $e) {
            error_log("Error in getGroupCustomerByCustomer: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addGroupCustomerForCustomer($id): array
    {
        try {
            $customer = Customer::where('deleted', false)->find($id);

            if (!$customer) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khách hàng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $groupCustomerId = $data['group_customer_id'];
            $groupCustomer = GroupCustomer::find($groupCustomerId);

            if (!$groupCustomer) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhóm khách hàng'
                ];
            }

            $customer->group_customer_id = $groupCustomerId;
            $customer->save();

            return [
                'success' => true,
                'message' => 'Thêm nhóm khách hàng cho khách hàng thành công'
            ];
        } catch (\Exception $e) {
            error_log("Error in addGroupCustomerForCustomer: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createCustomer(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $groupCustomer = (new GroupCustomer())->find($data['group_customer_id']);
            if (!$groupCustomer) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhóm khách hàng'
                ];
            }

            $customer = new Customer();
            $errors = $customer->validate($data);

            if ($errors) {
                return [
                    'success' => false,
                    'errors' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $customer->fill($data);
            $customer->save();

            return [
                'success' => true,
                'data' => $customer->toArray()
            ];
        } catch (\Exception $e) {
            error_log("Error in createCustomer: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateCustomerById($id): array
    {
        try {
            $customer = (new Customer())->find($id);

            if (!$customer) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khách hàng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Nếu có cập nhật group_customer_id thì kiểm tra storage area tồn tại
            if (!empty($data['group_customer_id'])) {
                $groupCustomer = (new GroupCustomer())->find($data['group_customer_id']);
                if (!$groupCustomer) {
                    return [
                        'success' => false,
                        'error' => 'Không tìm thấy khu vực kho'
                    ];
                }
            }

            $errors = $customer->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $customer->fill($data);
            $customer->save();

            return [
                'success' => true,
                'data' => $customer->toArray()
            ];
        } catch (\Exception $e) {
            error_log("Error in updateCustomerById: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteCustomer($id): array
    {
        try {
            $customer = Customer::find($id);

            if (!$customer) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khách hàng'
                ];
            }

            if ($customer->status == 'ACTIVE') {
                return [
                    'success' => false,
                    'error' => 'Không thể xóa khách hàng đang ở trạng thái Active'
                ];
            }

            $customer->deleted = true;
            $customer->save();

            return [
                'success' => true,
                'message' => 'Xóa thành công'
            ];
        } catch (\Exception $e) {
            error_log("Error in deleteCustomer: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}