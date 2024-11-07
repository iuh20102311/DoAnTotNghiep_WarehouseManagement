<?php

namespace App\Controllers;

use App\Models\InventoryCheck;
use App\Models\StorageArea;
use App\Models\User;
use App\Utils\PaginationTrait;

class InventoryCheckController
{
    use PaginationTrait;

    public function getInventoryChecks(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $inventoryCheck = (new InventoryCheck())->query()
                ->where('deleted', false)
                ->with([
                    'storageArea',
                    'creator' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details'])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
                ->orderBy('created_at', 'desc');

            if (isset($_GET['storage_area_id'])) {
                $storageAreaId = urldecode($_GET['storage_area_id']);
                $inventoryCheck->where('storage_area_id', $storageAreaId);
            }

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $inventoryCheck->where('status', $status);
            }

            if (isset($_GET['created_by'])) {
                $createdBy = urldecode($_GET['created_by']);
                $inventoryCheck->where('created_by', $createdBy);
            }

            if (isset($_GET['check_date_from'])) {
                $checkDateFrom = urldecode($_GET['check_date_from']);
                $inventoryCheck->where('check_date', '>=', $checkDateFrom);
            }

            if (isset($_GET['check_date_to'])) {
                $checkDateTo = urldecode($_GET['check_date_to']);
                $inventoryCheck->where('check_date', '<=', $checkDateTo);
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $inventoryCheck->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $inventoryCheck->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $inventoryCheck->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $inventoryCheck->where('updated_at', '<=', $updatedTo);
            }

            $result = $this->paginateResults($inventoryCheck, $perPage, $page)->toArray();

            if (isset($result['data']) && is_array($result['data'])) {
                foreach ($result['data'] as &$item) {
                    if (isset($item['creator']['profile'])) {
                        $item['creator']['full_name'] = trim($item['creator']['profile']['first_name'] . ' ' . $item['creator']['profile']['last_name']);
                    }
                }
            }

            return $result;

        } catch (\Exception $e) {
            error_log("Error in getInventoryChecks: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryCheckById($id): array
    {
        try {
            $inventoryCheck = (new InventoryCheck())
                ->where('deleted', false)
                ->with([
                    'storageArea',
                    'creator' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details'])
                ->find($id);

            if (!$inventoryCheck) {
                return [
                    'error' => 'Không tìm thấy phiếu kiểm kê'
                ];
            }

            $data = $inventoryCheck->toArray();

            if (isset($data['creator']['profile'])) {
                $data['creator']['full_name'] = trim($data['creator']['profile']['first_name'] . ' ' . $data['creator']['profile']['last_name']);
            }

            return $data;

        } catch (\Exception $e) {
            error_log("Error in getInventoryCheckById: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createInventoryCheck(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Kiểm tra storage area tồn tại
            $storageArea = (new StorageArea())->where('deleted',false)->find($data['storage_area_id']);
            if (!$storageArea) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            // Kiểm tra user tồn tại
            $user = (new User())->where('deleted',false)->find($data['created_by']);
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy người tạo'
                ];
            }

            $inventoryCheck = new InventoryCheck();
            $errors = $inventoryCheck->validate($data);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $inventoryCheck->fill($data);
            $inventoryCheck->save();

            return [
                'success' => true,
                'data' => $inventoryCheck->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createInventoryCheck: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateInventoryCheckById($id): array
    {
        try {
            $inventoryCheck = (new InventoryCheck())->where('deleted',false)->find($id);

            if (!$inventoryCheck) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy phiếu kiểm kê'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Nếu có cập nhật storage_area_id thì kiểm tra storage area tồn tại
            if (!empty($data['storage_area_id'])) {
                $storageArea = (new StorageArea())->where('deleted',false)->find($data['storage_area_id']);
                if (!$storageArea) {
                    return [
                        'success' => false,
                        'error' => 'Không tìm thấy khu vực kho'
                    ];
                }
            }

            // Nếu có cập nhật created_by thì kiểm tra user tồn tại
            if (!empty($data['created_by'])) {
                $user = (new User())->where('deleted',false)->find($data['created_by']);
                if (!$user) {
                    return [
                        'success' => false,
                        'error' => 'Không tìm thấy người tạo'
                    ];
                }
            }

            $errors = $inventoryCheck->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $inventoryCheck->fill($data);
            $inventoryCheck->save();

            return [
                'success' => true,
                'data' => $inventoryCheck->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateInventoryCheck: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteInventoryCheck($id): array
    {
        try {
            $inventoryCheck = (new InventoryCheck())->where('deleted',false)->find($id);

            if (!$inventoryCheck) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy phiếu kiểm kê'
                ];
            }

            $inventoryCheck->deleted = true;
            $inventoryCheck->save();

            return [
                'success' => true,
                'message' => 'Xóa phiếu kiểm kê thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteInventoryCheck: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getStorageAreaByInventoryCheck($id): array
    {
        try {
            $inventoryCheck = (new InventoryCheck())->where('deleted',false)->find($id);

            if (!$inventoryCheck) {
                return [
                    'error' => 'Không tìm thấy phiếu kiểm kê'
                ];
            }

            $storageArea = $inventoryCheck->storageArea()
                ->where('deleted', false)
                ->with(['inventoryHistory'])
                ->first();

            if (!$storageArea) {
                return [
                    'error' => 'Không tìm thấy khu vực kho của phiếu kiểm kê này'
                ];
            }

            return [
                'data' => $storageArea->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getStorageAreaByInventoryCheck: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateStorageAreaByInventoryCheck($id): array
    {
        try {
            $inventoryCheck = (new InventoryCheck())->where('deleted',false)->find($id);

            if (!$inventoryCheck) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy phiếu kiểm kê'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Kiểm tra storage area mới tồn tại
            $storageArea = (new StorageArea())->where('deleted',false)->find($data['storage_area_id']);
            if (!$storageArea) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            $inventoryCheck->storage_area_id = $data['storage_area_id'];
            $inventoryCheck->save();

            return [
                'success' => true,
                'message' => 'Cập nhật khu vực kho thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in updateStorageAreaByInventoryCheck: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getUserByInventoryCheck($id): array
    {
        try {
            $inventoryCheck = (new InventoryCheck())->where('deleted',false)->find($id);

            if (!$inventoryCheck) {
                return [
                    'error' => 'Không tìm thấy phiếu kiểm kê'
                ];
            }

            $user = $inventoryCheck->creator()
                ->where('deleted', false)
                ->with(['role','profile','inventoryHistory'])
                ->first();

            if (!$user) {
                return [
                    'error' => 'Không tìm thấy người tạo của phiếu kiểm kê này'
                ];
            }

            return [
                'data' => $user->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getUserByInventoryCheck: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateUserByInventoryCheck($id): array
    {
        try {
            $inventoryCheck = (new InventoryCheck())->where('deleted',false)->find($id);

            if (!$inventoryCheck) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy phiếu kiểm kê'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Kiểm tra user mới tồn tại
            $user = (new User())->find($data['created_by']);
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy người dùng'
                ];
            }

            $inventoryCheck->created_by = $data['created_by'];
            $inventoryCheck->save();

            return [
                'success' => true,
                'message' => 'Cập nhật người tạo thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in updateUserByInventoryCheck: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}