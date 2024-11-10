<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\GroupCustomer;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
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

            return $this->paginateResults($groupcustomer, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGroupCustomerById($id): string
    {
        $groupcustomer = GroupCustomer::query()
            ->where('deleted', false)
            ->where('id', $id)
            ->with(['customers'])
            ->first();

        if (!$groupcustomer) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($groupcustomer->toArray());
    }

    public function getCustomerByGroupCustomer($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $groupcustomer = GroupCustomer::where('deleted', false)->findOrFail($id);

        $customersQuery = $groupcustomer->customers()
            ->where('deleted', false)
            ->with('groupCustomer')
            ->getQuery();

        return $this->paginateResults($customersQuery, $perPage, $page)->toArray();
    }

    public function createGroupCustomer(): Model|string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $groupcustomer = new GroupCustomer();
        $error = $groupcustomer->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $groupcustomer->fill($data);
        $groupcustomer->save();
        return $groupcustomer;
    }

    public function updateGroupCustomerById($id): bool|int|string
    {
        $groupcustomer = GroupCustomer::find($id);

        if (!$groupcustomer) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $groupcustomer->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $groupcustomer->fill($data);
        $groupcustomer->save();

        return $groupcustomer;
    }

    public function deleteGroupCustomer($id): string
    {
        $groupcustomer = GroupCustomer::find($id);

        if ($groupcustomer) {
            $groupcustomer->status = 'DISABLE';
            $groupcustomer->save();
            return "Xóa thành công";
        } else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}