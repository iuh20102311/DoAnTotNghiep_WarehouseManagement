<?php

namespace App\Controllers;

use App\Models\InventoryCheck;
use App\Models\InventoryCheckDetail;
use App\Models\MaterialInventoryHistory;
use App\Models\MaterialStorageHistory;
use App\Models\ProductInventoryHistory;
use App\Models\ProductStorageHistory;
use App\Models\Role;
use App\Models\StorageArea;
use App\Models\User;
use App\Utils\PaginationTrait;
use Exception;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class InventoryCheckController
{
    use PaginationTrait;

    public function getInventoryChecks(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

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
            $storageArea = (new StorageArea())->where('deleted', false)->find($data['storage_area_id']);
            if (!$storageArea) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy khu vực kho'
                ];
            }

            // Lấy thông tin người đang đăng nhập
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            if (!$token) {
                http_response_code(401);
                throw new \Exception('Token không tồn tại');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $currentUserId = $parsedToken->claims()->get('id');

            // Kiểm tra user và role
            $user = (new User())->where('deleted', false)->find($currentUserId);
            if (!$user) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy thông tin người dùng'
                ];
            }

            $role = (new Role())->find($user->role_id);
            if (!$role || $role->name !== 'Staff') {
                http_response_code(403);
                return [
                    'success' => false,
                    'error' => 'Chỉ nhân viên mới được tạo phiếu kiểm kê'
                ];
            }

            // Chuẩn bị dữ liệu để validate và tạo phiếu
            $checkData = [
                'storage_area_id' => $data['storage_area_id'],
                'check_date' => date('Y-m-d H:i:s'),
                'status' => 'PENDING',
                'note' => $data['note'] ?? null,
                'created_by' => $currentUserId
            ];

            $inventoryCheck = new InventoryCheck();
            $errors = $inventoryCheck->validate($checkData);

            if ($errors) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            // Tạo phiếu kiểm kê
            $inventoryCheck->fill($checkData);
            $inventoryCheck->save();

            // Lấy số lượng hiện tại từ kho
            if ($storageArea->type === 'PRODUCT') {
                $currentStock = (new ProductStorageHistory())
                    ->where('storage_area_id', $storageArea->id)
                    ->where('status', 'ACTIVE')
                    ->where('deleted', false)
                    ->get();
            } else {
                $currentStock = (new MaterialStorageHistory())
                    ->where('storage_area_id', $storageArea->id)
                    ->where('status', 'ACTIVE')
                    ->where('deleted', false)
                    ->get();
            }

            // Tạo chi tiết kiểm kê
            foreach ($currentStock as $stock) {
                $detail = new InventoryCheckDetail();
                $detail->fill([
                    'inventory_check_id' => $inventoryCheck->id,
                    'product_id' => $storageArea->type === 'PRODUCT' ? $stock->product_id : null,
                    'material_id' => $storageArea->type === 'MATERIAL' ? $stock->material_id : null,
                    'exact_quantity' => $stock->quantity_available,
                    'actual_quantity' => null,
                    'defective_quantity' => 0
                ]);
                $detail->save();
            }

            return [
                'success' => true,
                'data' => $inventoryCheck->load(['details', 'storageArea', 'creator'])->toArray()
            ];

        } catch (Exception $e) {
            error_log("Error in createInventoryCheck: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function approveInventoryCheck(int $id): array
    {
        try {
            // Unset tất cả dữ liệu được gửi từ người dùng
            $_POST = [];
            $_REQUEST = [];
            unset($_GET);
            $input = file_get_contents('php://input');
            if ($input) {
                fclose(fopen('php://input', 'w'));
            }

            // Kiểm tra phiếu kiểm kê tồn tại
            $inventoryCheck = (new InventoryCheck())
                ->where('deleted', false)
                ->find($id);

            if (!$inventoryCheck) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy phiếu kiểm kê'
                ];
            }

            if ($inventoryCheck->status !== 'PENDING') {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Phiếu kiểm kê không ở trạng thái chờ duyệt'
                ];
            }

            // Lấy thông tin người đang đăng nhập
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            if (!$token) {
                http_response_code(401);
                throw new \Exception('Token không tồn tại');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $currentUserId = $parsedToken->claims()->get('id');

            // Kiểm tra user và role
            $user = (new User())->where('deleted', false)->find($currentUserId);
            if (!$user) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy thông tin người dùng'
                ];
            }

            $role = (new Role())->find($user->role_id);
            if (!$role || $role->id !== 3) {
                http_response_code(403);
                return [
                    'success' => false,
                    'error' => 'Chỉ thủ kho mới được phê duyệt phiếu kiểm kê'
                ];
            }

            // Chỉ cập nhật trạng thái và thông tin phê duyệt
            $inventoryCheck->status = 'APPROVED';
            $inventoryCheck->approved_by = $currentUserId;
            $inventoryCheck->approved_at = date('Y-m-d H:i:s');
            $inventoryCheck->save();

            return [
                'success' => true,
                'data' => $inventoryCheck->load(['details', 'storageArea', 'creator', 'approver'])->toArray()
            ];

        } catch (Exception $e) {
            error_log("Error in approveInventoryCheck: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateInventoryCheckById(int $id): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // [BƯỚC 1] - Kiểm tra phiếu kiểm kê tồn tại
            $inventoryCheck = (new InventoryCheck())
                ->where('deleted', false)
                ->find($id);

            if (!$inventoryCheck) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy phiếu kiểm kê'
                ];
            }

            // [BƯỚC 2] - Kiểm tra trạng thái phiếu
            if ($inventoryCheck->status !== 'APPROVED') {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Phiếu kiểm kê chưa được duyệt hoặc đã hoàn thành'
                ];
            }

            // [BƯỚC 3] - Kiểm tra JWT Token và quyền hạn
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            if (!$token) {
                http_response_code(401);
                throw new \Exception('Token không tồn tại');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $currentUserId = $parsedToken->claims()->get('id');

            // Kiểm tra user và role
            $user = (new User())->where('deleted', false)->find($currentUserId);
            if (!$user) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy thông tin người dùng'
                ];
            }

            $role = (new Role())->find($user->role_id);
            if (!$role || $role->id !== 4) {
                http_response_code(403);
                return [
                    'success' => false,
                    'error' => 'Chỉ nhân viên mới được cập nhật kết quả kiểm kê'
                ];
            }

            // [BƯỚC 4] - Validate dữ liệu đầu vào
            if (!isset($data['details']) || !is_array($data['details']) || empty($data['details'])) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Dữ liệu kiểm kê không hợp lệ'
                ];
            }

            // [BƯỚC 5] - Lấy và kiểm tra chi tiết kiểm kê
            $currentDetails = (new InventoryCheckDetail())
                ->where('inventory_check_id', $id)
                ->where('deleted', false)
                ->get();

            $materialDetails = [];
            foreach ($currentDetails as $detail) {
                $materialId = $detail->material_id ?? $detail->product_id;
                if (!isset($materialDetails[$materialId])) {
                    $materialDetails[$materialId] = [];
                }
                $materialDetails[$materialId][] = $detail;
            }

            // [BƯỚC 6] - Kiểm tra kiểm kê đủ số lượng
            $submittedIds = array_map(function($detail) {
                return $detail['material_id'] ?? $detail['product_id'];
            }, $data['details']);

            $missingItems = array_diff(array_keys($materialDetails), $submittedIds);
            if (!empty($missingItems)) {
                $missingCount = count($missingItems);
                $type = $inventoryCheck->storageArea->type === 'PRODUCT' ? 'Sản phẩm' : 'Nguyên liệu';
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => "Còn {$missingCount} {$type} chưa được kiểm kê"
                ];
            }

            // [BƯỚC 7] - Xử lý cập nhật từng chi tiết
            foreach ($data['details'] as $updateDetail) {
                $materialId = $updateDetail['material_id'] ?? $updateDetail['product_id'];

                // Validate chi tiết
                if (!isset($materialDetails[$materialId])) {
                    http_response_code(422);
                    return [
                        'success' => false,
                        'error' => 'Không tìm thấy material/product trong phiếu kiểm kê'
                    ];
                }

                if (!isset($updateDetail['actual_quantity']) || $updateDetail['actual_quantity'] < 0) {
                    http_response_code(422);
                    return [
                        'success' => false,
                        'error' => 'Số lượng thực tế không hợp lệ'
                    ];
                }

                // Tính tổng số lượng từ kho
                $totalExact = 0;
                $firstDetail = $materialDetails[$materialId][0]; // Định nghĩa $firstDetail ở đây
                foreach ($materialDetails[$materialId] as $detail) {
                    $totalExact += $detail->exact_quantity;
                }

                // Lấy thông tin storage history hiện tại
                if ($firstDetail->product_id) {
                    $currentStorage = (new ProductStorageHistory())
                        ->where('product_id', $firstDetail->product_id)
                        ->where('storage_area_id', $inventoryCheck->storage_area_id)
                        ->where('status', 'ACTIVE')
                        ->where('deleted', false)
                        ->first();
                } else {
                    $currentStorage = (new MaterialStorageHistory())
                        ->where('material_id', $firstDetail->material_id)
                        ->where('storage_area_id', $inventoryCheck->storage_area_id)
                        ->where('status', 'ACTIVE')
                        ->where('deleted', false)
                        ->first();
                }

                // Kiểm tra tổng số lượng kiểm kê phải bằng số lượng trong kho
                $totalChecked = $updateDetail['actual_quantity'] + ($updateDetail['defective_quantity'] ?? 0);
                if ($totalChecked !== $currentStorage->quantity_available) {
                    $type = $firstDetail->product_id ? 'Sản phẩm' : 'Nguyên liệu';
                    $itemName = $firstDetail->product_id ?
                        $firstDetail->product->name :
                        $firstDetail->material->name;

                    http_response_code(422);
                    return [
                        'success' => false,
                        'error' => "Tổng số lượng kiểm kê của {$type} '{$itemName}' ({$totalChecked}) không khớp với số lượng trong kho ({$currentStorage->quantity_available})"
                    ];
                }

                // Gộp các chi tiết trùng
                $firstDetail->fill([
                    'exact_quantity' => $totalExact,
                    'actual_quantity' => $updateDetail['actual_quantity'],
                    'defective_quantity' => $updateDetail['defective_quantity'] ?? 0,
                    'error_description' => $updateDetail['error_description'] ?? null
                ]);
                $firstDetail->save();

                // Xóa các chi tiết trùng
                for ($i = 1; $i < count($materialDetails[$materialId]); $i++) {
                    $materialDetails[$materialId][$i]->deleted = true;
                    $materialDetails[$materialId][$i]->save();
                }

                // [BƯỚC 8] - Ghi nhận lịch sử kiểm kê
                if ($firstDetail->product_id) {
                    // Ghi nhận kiểm kê sản phẩm
                    ProductInventoryHistory::create([
                        'storage_area_id' => $inventoryCheck->storage_area_id,
                        'product_id' => $firstDetail->product_id,
                        'quantity_before' => $totalExact,
                        'quantity_change' => $updateDetail['actual_quantity'] - $totalExact,
                        'quantity_after' => $updateDetail['actual_quantity'] - ($updateDetail['defective_quantity'] ?? 0),
                        'remaining_quantity' => $updateDetail['actual_quantity'] - ($updateDetail['defective_quantity'] ?? 0),
                        'action_type' => 'CHECK',
                        'created_by' => $currentUserId
                    ]);
                } else {
                    // Ghi nhận kiểm kê nguyên liệu
                    MaterialInventoryHistory::create([
                        'storage_area_id' => $inventoryCheck->storage_area_id,
                        'material_id' => $firstDetail->material_id,
                        'quantity_before' => $totalExact,
                        'quantity_change' => $updateDetail['actual_quantity'] - $totalExact,
                        'quantity_after' => $updateDetail['actual_quantity'] - ($updateDetail['defective_quantity'] ?? 0),
                        'remaining_quantity' => $updateDetail['actual_quantity'] - ($updateDetail['defective_quantity'] ?? 0),
                        'action_type' => 'CHECK',
                        'created_by' => $currentUserId
                    ]);
                }

                // [BƯỚC 9] - Cập nhật storage history
                if ($firstDetail->product_id) {
                    // Xử lý product storage history
                    $currentStorage = (new ProductStorageHistory())
                        ->where('product_id', $firstDetail->product_id)
                        ->where('storage_area_id', $inventoryCheck->storage_area_id)
                        ->where('status', 'ACTIVE')
                        ->where('deleted', false)
                        ->first();

                    if ($currentStorage) {
                        // Set record hiện tại thành inactive
                        $currentStorage->status = 'INACTIVE';
                        $currentStorage->save();

                        // Tạo record mới cho số lượng sau kiểm kê
                        $newStorage = new ProductStorageHistory();
                        $newStorage->fill([
                            'product_id' => $firstDetail->product_id,
                            'storage_area_id' => $inventoryCheck->storage_area_id,
                            'expiry_date' => $currentStorage->expiry_date,
                            'quantity' => 0,
                            'quantity_available' => $updateDetail['actual_quantity'] - ($updateDetail['defective_quantity'] ?? 0),
                            'provider_id' => $currentStorage->provider_id,
                            'status' => 'ACTIVE',
                            'deleted' => false
                        ]);
                        $newStorage->save();
                    }
                } else {
                    // Xử lý material storage history - tương tự product
                    $currentStorage = (new MaterialStorageHistory())
                        ->where('material_id', $firstDetail->material_id)
                        ->where('storage_area_id', $inventoryCheck->storage_area_id)
                        ->where('status', 'ACTIVE')
                        ->where('deleted', false)
                        ->first();

                    if ($currentStorage) {
                        $currentStorage->status = 'INACTIVE';
                        $currentStorage->save();

                        $newStorage = new MaterialStorageHistory();
                        $newStorage->fill([
                            'material_id' => $firstDetail->material_id,
                            'storage_area_id' => $inventoryCheck->storage_area_id,
                            'expiry_date' => $currentStorage->expiry_date,
                            'quantity' => 0,
                            'quantity_available' => $updateDetail['actual_quantity'] - ($updateDetail['defective_quantity'] ?? 0),
                            'provider_id' => $currentStorage->provider_id,
                            'status' => 'ACTIVE',
                            'deleted' => false
                        ]);
                        $newStorage->save();
                    }
                }
            }

            // [BƯỚC 10] - Cập nhật trạng thái phiếu kiểm kê
            $inventoryCheck->status = 'COMPLETED';
            $inventoryCheck->completed_at = date('Y-m-d H:i:s');
            $inventoryCheck->save();

            // [BƯỚC 11] - Trả về kết quả
            return [
                'success' => true,
                'data' => $inventoryCheck->load([
                    'details' => function($query) {
                        $query->where('deleted', false);
                    },
                    'storageArea',
                    'creator',
                    'approver'
                ])->toArray()
            ];

        } catch (Exception $e) {
            error_log("Error in updateInventoryCheck: " . $e->getMessage());
            http_response_code(500);
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
            $inventoryCheck = (new InventoryCheck())->where('deleted', false)->find($id);

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
            $inventoryCheck = (new InventoryCheck())->where('deleted', false)->find($id);

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
            $inventoryCheck = (new InventoryCheck())->where('deleted', false)->find($id);

            if (!$inventoryCheck) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy phiếu kiểm kê'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Kiểm tra storage area mới tồn tại
            $storageArea = (new StorageArea())->where('deleted', false)->find($data['storage_area_id']);
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
            $inventoryCheck = (new InventoryCheck())->where('deleted', false)->find($id);

            if (!$inventoryCheck) {
                return [
                    'error' => 'Không tìm thấy phiếu kiểm kê'
                ];
            }

            $user = $inventoryCheck->creator()
                ->where('deleted', false)
                ->with(['role', 'profile', 'inventoryHistory'])
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
            $inventoryCheck = (new InventoryCheck())->where('deleted', false)->find($id);

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