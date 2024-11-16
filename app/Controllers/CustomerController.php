<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\GroupCustomer;
use App\Utils\PaginationTrait;

class CustomerController
{
    use PaginationTrait;

    public function getCustomers(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

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
                $customer->where('name', 'like', '%' . $name . '%');
            }

            if (isset($_GET['code'])) {
                $code = urldecode($_GET['code']);
                $customer->where('code', 'like', '%' . $code . '%');
            }

            if (isset($_GET['gender'])) {
                $gender = urldecode($_GET['gender']);
                $customer->where('gender', $gender);
            }

            if (isset($_GET['email'])) {
                $email = urldecode($_GET['email']);
                $customer->where('email', 'like', '%' . $email . '%');
            }

            if (isset($_GET['phone'])) {
                $phone = urldecode($_GET['phone']);
                $customer->where(function ($query) use ($phone) {
                    $query->where('phone', 'LIKE', '%' . $phone . '%')
                        ->orWhere('phone', 'LIKE', $phone . '%')
                        ->orWhere('phone', 'LIKE', '%' . $phone);
                });
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

            $results = $this->paginateResults($customer, $perPage, $page);

            if ($results->isEmpty()) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy khách hàng nào'
                ];
            }

            return $results->toArray();
        } catch (\Exception $e) {
            error_log("Error in getCustomers: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCustomerByCode($code): array
    {
        try {
            $customer = Customer::query()
                ->where('code', $code)
                ->where('deleted', false)
                ->with(['groupCustomer', 'orders'])
                ->first();

            if (!$customer) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy khách hàng'
                ];
            }

            return $customer->toArray();
        } catch (\Exception $e) {
            error_log("Error in getCustomerById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getOrderByCustomer($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            if (empty($id)) {
                http_response_code(404);
                return [
                    'error' => 'ID khách hàng không được để trống'
                ];
            }

            $customer = Customer::query()->where('deleted', false)->where('id', $id)->firstOrFail();

            if (!$customer) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy khách hàng với ID: ' . $id
                ];
            }

            $ordersQuery = $customer->orders()
                ->where('orders.deleted', false)
                ->with(['customer', 'creator'])
                ->getQuery();

            $results = $this->paginateResults($ordersQuery, $perPage, $page);

            if ($results->isEmpty()) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy đơn hàng nào của khách hàng này'
                ];
            }

            return $results->toArray();
        } catch (\Exception $e) {
            error_log("Error in getOrderByCustomer: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGroupCustomerByCustomer($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            if (empty($id)) {
                http_response_code(404);
                return [
                    'error' => 'ID khách hàng không được để trống'
                ];
            }

            $customer = Customer::query()->where('deleted', false)->where('id', $id)->firstOrFail();

            if (!$customer) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy khách hàng với ID: ' . $id
                ];
            }

            $groupCustomersQuery = $customer->groupCustomer()
                ->where('group_customers.deleted', false)
                ->with(['customers'])
                ->getQuery();

            $results = $this->paginateResults($groupCustomersQuery, $perPage, $page);

            if ($results->isEmpty()) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy nhóm khách hàng nào'
                ];
            }

            return $results->toArray();
        } catch (\Exception $e) {
            error_log("Error in getGroupCustomerByCustomer: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addGroupCustomerForCustomer($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'ID khách hàng không được để trống'
                ];
            }

            $customer = Customer::where('deleted', false)->find($id);

            if (!$customer) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khách hàng với ID: ' . $id
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['group_customer_id'])) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'ID nhóm khách hàng không được để trống'
                ];
            }

            $groupCustomerId = $data['group_customer_id'];
            $groupCustomer = GroupCustomer::find($groupCustomerId);

            if (!$groupCustomer) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhóm khách hàng với ID: ' . $groupCustomerId
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
            http_response_code(500);
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

            if (empty($data['group_customer_id'])) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'ID nhóm khách hàng không được để trống'
                ];
            }

            $groupCustomer = (new GroupCustomer())->find($data['group_customer_id']);

            if (!$groupCustomer) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhóm khách hàng với ID: ' . $data['group_customer_id']
                ];
            }

            // Unset code if provided by user
            if (isset($data['code'])) {
                unset($data['code']);
            }

            $customer = new Customer();
            $errors = $customer->validate($data);

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'errors' => 'Validation failed',
                    'details' => $errors
                ];
            }

            // Generate new code for customer
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "KH" . $currentMonth . $currentYear;

            // Get latest customer code with current prefix
            $latestCustomer = Customer::query()
                ->where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            if ($latestCustomer) {
                // Extract sequence number and increment
                $sequence = intval(substr($latestCustomer->code, -5)) + 1;
            } else {
                $sequence = 1;
            }

            // Format sequence to 5 digits
            $data['code'] = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);

            $customer->fill($data);
            $customer->save();

            return [
                'success' => true,
                'data' => $customer->toArray()
            ];
        } catch (\Exception $e) {
            error_log("Error in createCustomer: " . $e->getMessage());
            http_response_code(500);
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
            if (empty($id)) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'ID khách hàng không được để trống'
                ];
            }

            $customer = (new Customer())->find($id);

            if (!$customer) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khách hàng với ID: ' . $id
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Remove code from update data to prevent modification
            if (isset($data['code'])) {
                unset($data['code']);
            }

            // Nếu có cập nhật group_customer_id thì kiểm tra storage area tồn tại
            if (!empty($data['group_customer_id'])) {
                $groupCustomer = (new GroupCustomer())->find($data['group_customer_id']);
                if (!$groupCustomer) {
                    http_response_code(404);
                    return [
                        'success' => false,
                        'error' => 'Không tìm thấy nhóm khách hàng với ID: ' . $data['group_customer_id']
                    ];
                }
            }

            $errors = $customer->validate($data, true);

            if ($errors) {
                http_response_code(422);
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
            http_response_code(500);
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
            if (empty($id)) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'ID khách hàng không được để trống'
                ];
            }

            $customer = Customer::find($id);

            if (!$customer) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khách hàng với ID: ' . $id
                ];
            }

            if ($customer->status == 'ACTIVE') {
                http_response_code(422);
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
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}