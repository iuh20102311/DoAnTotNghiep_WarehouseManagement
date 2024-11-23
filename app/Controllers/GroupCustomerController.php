<?php

namespace App\Controllers;

use App\Models\GroupCustomer;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Model;

class GroupCustomerController
{
    use PaginationTrait;

    public function getGroupCustomers(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $groupcustomer = GroupCustomer::query()
                ->where('deleted', false)
                ->with(['customers'])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
                ->orderBy('created_at', 'desc');

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $groupcustomer->where('status', $status);
            }

            if (isset($_GET['name'])) {
                $name = urldecode($_GET['name']);
                $groupcustomer->where('name', 'like', '%' . $name . '%');
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $groupcustomer->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $groupcustomer->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $groupcustomer->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $groupcustomer->where('updated_at', '<=', $updatedTo);
            }

            $results = $this->paginateResults($groupcustomer, $perPage, $page);

            if ($results->isEmpty()) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhóm khách hàng nào',
                    'error_code' => 'GROUP_CUSTOMERS_NOT_FOUND'
                ];
            }

            return $results->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGroupCustomerList(): array
    {
        try {
            $groupcustomer = GroupCustomer::query()
                ->where('deleted', false)
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")
                ->orderBy('created_at', 'desc');

            if (!$groupcustomer) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhóm khách hàng nào',
                ];
            }

            return $this->paginateResults($groupcustomer)->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGroupCustomerById($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID nhóm khách hàng không được để trống',
                ];
            }

            $groupcustomer = GroupCustomer::query()
                ->where('deleted', false)
                ->where('id', $id)
                ->with(['customers'])
                ->first();

            if (!$groupcustomer) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhóm khách hàng',
                ];
            }

            return $groupcustomer->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCustomerByGroupCustomer($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID nhóm khách hàng không được để trống',
                ];
            }

            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $groupcustomer = GroupCustomer::where('deleted', false)->find($id);

            if (!$groupcustomer) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhóm khách hàng',
                ];
            }

            $customersQuery = $groupcustomer->customers()
                ->where('deleted', false)
                ->with('groupCustomer')
                ->getQuery();

            $results = $this->paginateResults($customersQuery, $perPage, $page);

            if ($results->isEmpty()) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khách hàng nào trong nhóm này',
                ];
            }

            return $results->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createGroupCustomer(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu đầu vào không hợp lệ',
                ];
            }

            $groupcustomer = new GroupCustomer();
            $errors = $groupcustomer->validate($data);

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $groupcustomer->fill($data);
            $groupcustomer->save();

            return [
                'success' => true,
                'message' => 'Tạo nhóm khách hàng thành công',
                'data' => $groupcustomer->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateGroupCustomerById($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID nhóm khách hàng không được để trống',
                ];
            }

            $groupcustomer = GroupCustomer::where('deleted', false)->find($id);

            if (!$groupcustomer) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhóm khách hàng',
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu cập nhật không hợp lệ',
                ];
            }

            $errors = $groupcustomer->validate($data, true);

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $groupcustomer->fill($data);
            $groupcustomer->save();

            return [
                'success' => true,
                'message' => 'Cập nhật nhóm khách hàng thành công',
                'data' => $groupcustomer->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteGroupCustomer($id): array
    {
        try {
            if (empty($id)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'ID nhóm khách hàng không được để trống',
                    'error_code' => 'EMPTY_GROUP_CUSTOMER_ID'
                ];
            }

            $groupcustomer = GroupCustomer::where('deleted', false)->find($id);

            if (!$groupcustomer) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhóm khách hàng',
                    'error_code' => 'GROUP_CUSTOMER_NOT_FOUND'
                ];
            }

            // Check if group has active customers
            if ($groupcustomer->customers()->where('deleted', false)->exists()) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Không thể xóa nhóm đang có khách hàng',
                    'error_code' => 'GROUP_HAS_ACTIVE_CUSTOMERS'
                ];
            }

            $groupcustomer->status = 'DISABLE';
            $groupcustomer->deleted = true;
            $groupcustomer->save();

            http_response_code(200);
            return [
                'success' => true,
                'message' => 'Xóa nhóm khách hàng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'error_code' => 'DATABASE_ERROR',
                'details' => $e->getMessage()
            ];
        }
    }
}