<?php

namespace App\Controllers;

use App\Models\InventoryCheck;
use App\Models\InventoryCheckDetail;
use App\Models\Material;
use App\Models\MaterialStorageHistoryDetail;
use App\Models\MaterialStorageHistory;
use App\Models\Product;
use App\Models\ProductStorageHistoryDetail;
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

//    public function createInventoryCheck(): array
//    {
//        try {
//            $data = json_decode(file_get_contents('php://input'), true);
//
//            // Kiểm tra storage area tồn tại
//            $storageArea = (new StorageArea())->where('deleted', false)->find($data['storage_area_id']);
//            if (!$storageArea) {
//                http_response_code(422);
//                return [
//                    'success' => false,
//                    'error' => 'Không tìm thấy khu vực kho'
//                ];
//            }
//
//            // Lấy thông tin người đang đăng nhập
//            $headers = apache_request_headers();
//            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
//            if (!$token) {
//                http_response_code(401);
//                throw new \Exception('Token không tồn tại');
//            }
//
//            $parser = new Parser(new JoseEncoder());
//            $parsedToken = $parser->parse($token);
//            $currentUserId = $parsedToken->claims()->get('id');
//
//            // Kiểm tra user và role
//            $user = (new User())->where('deleted', false)->find($currentUserId);
//            if (!$user) {
//                http_response_code(422);
//                return [
//                    'success' => false,
//                    'error' => 'Không tìm thấy thông tin người dùng'
//                ];
//            }
//
////            $role = (new Role())->find($user->role_id);
////            if (!$role || $role->name !== 'Staff') {
////                http_response_code(403);
////                return [
////                    'success' => false,
////                    'error' => 'Chỉ nhân viên mới được tạo phiếu kiểm kê'
////                ];
////            }
//
//            // Chuẩn bị dữ liệu để validate và tạo phiếu
//            $checkData = [
//                'storage_area_id' => $data['storage_area_id'],
//                'check_date' => date('Y-m-d H:i:s'),
//                'status' => 'PENDING',
//                'note' => $data['note'] ?? null,
//                'created_by' => $currentUserId
//            ];
//
//            $inventoryCheck = new InventoryCheck();
//            $errors = $inventoryCheck->validate($checkData);
//
//            if ($errors) {
//                http_response_code(422);
//                return [
//                    'success' => false,
//                    'error' => 'Validation failed',
//                    'details' => $errors
//                ];
//            }
//
//            // Tạo phiếu kiểm kê
//            $inventoryCheck->fill($checkData);
//            $inventoryCheck->save();
//
//            // Lấy số lượng hiện tại từ kho
//            if ($storageArea->type === 'PRODUCT') {
//                $currentStock = (new ProductStorageHistory())
//                    ->where('storage_area_id', $storageArea->id)
//                    ->where('status', 'ACTIVE')
//                    ->where('deleted', false)
//                    ->get();
//
//                // Tạo chi tiết kiểm kê cho sản phẩm
//                foreach ($currentStock as $stock) {
//                    $detail = new InventoryCheckDetail();
//                    $detail->fill([
//                        'inventory_check_id' => $inventoryCheck->id,
//                        'product_history_id' => $stock->id,
//                        'system_quantity' => $stock->quantity_available,
//                        'actual_quantity' => null
//                    ]);
//                    $detail->save();
//                }
//            } else {
//                $currentStock = (new MaterialStorageHistory())
//                    ->where('storage_area_id', $storageArea->id)
//                    ->where('status', 'ACTIVE')
//                    ->where('deleted', false)
//                    ->get();
//
//                // Tạo chi tiết kiểm kê cho nguyên liệu
//                foreach ($currentStock as $stock) {
//                    $detail = new InventoryCheckDetail();
//                    $detail->fill([
//                        'inventory_check_id' => $inventoryCheck->id,
//                        'material_history_id' => $stock->id,
//                        'system_quantity' => $stock->quantity_available,
//                        'actual_quantity' => null
//                    ]);
//                    $detail->save();
//                }
//            }
//
//            return [
//                'success' => true,
//                'data' => $inventoryCheck->load(['details', 'storageArea', 'creator'])->toArray()
//            ];
//
//        } catch (Exception $e) {
//            error_log("Error in createInventoryCheck: " . $e->getMessage());
//            http_response_code(500);
//            return [
//                'success' => false,
//                'error' => 'Database error occurred',
//                'details' => $e->getMessage()
//            ];
//        }
//    }

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

//            $role = (new Role())->find($user->role_id);
//            if (!$role || $role->id !== 3) {
//                http_response_code(403);
//                return [
//                    'success' => false,
//                    'error' => 'Chỉ thủ kho mới được phê duyệt phiếu kiểm kê'
//                ];
//            }

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

//    public function updateInventoryCheckById(int $id): array
//    {
//        try {
//            $data = json_decode(file_get_contents('php://input'), true);
//
//            // [BƯỚC 1] - Validate payload cơ bản
//            if (!isset($data['created_by']) || !isset($data['details']) || !is_array($data['details'])) {
//                http_response_code(422);
//                return [
//                    'success' => false,
//                    'error' => 'Dữ liệu không hợp lệ'
//                ];
//            }
//
//            // [BƯỚC 2] - Kiểm tra phiếu kiểm kê tồn tại
//            $inventoryCheck = (new InventoryCheck())
//                ->where('deleted', false)
//                ->find($id);
//
//            if (!$inventoryCheck) {
//                http_response_code(422);
//                return [
//                    'success' => false,
//                    'error' => 'Không tìm thấy phiếu kiểm kê'
//                ];
//            }
//
//            // [BƯỚC 3] - Kiểm tra trạng thái phiếu
//            if ($inventoryCheck->status !== 'APPROVED') {
//                http_response_code(422);
//                return [
//                    'success' => false,
//                    'error' => 'Phiếu kiểm kê chưa được duyệt hoặc đã hoàn thành'
//                ];
//            }
//
//            // [BƯỚC 4] - Token validation
//            $headers = apache_request_headers();
//            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
//            if (!$token) {
//                http_response_code(401);
//                throw new \Exception('Token không tồn tại');
//            }
//
//            $parser = new Parser(new JoseEncoder());
//            $parsedToken = $parser->parse($token);
//            $currentUserId = $parsedToken->claims()->get('id');
//
//            // [BƯỚC 5] - Kiểm tra user thực hiện và role
//            $user = (new User())->where('deleted', false)->find($data['created_by']);
//            if (!$user) {
//                http_response_code(422);
//                return [
//                    'success' => false,
//                    'error' => 'Không tìm thấy thông tin người thực hiện'
//                ];
//            }
//
////            $role = (new Role())->find($user->role_id);
////            if (!$role || $role->id !== 4) {
////                http_response_code(403);
////                return [
////                    'success' => false,
////                    'error' => 'Người thực hiện phải là nhân viên'
////                ];
////            }
//
//            // [BƯỚC 6] - Lấy và kiểm tra chi tiết kiểm kê
//            $currentDetails = (new InventoryCheckDetail())
//                ->where('inventory_check_id', $id)
//                ->where('deleted', false)
//                ->get();
//
//            $historyMap = [];
//            foreach ($currentDetails as $detail) {
//                $historyId = $detail->material_history_id ?? $detail->product_history_id;
//                if (!isset($historyMap[$historyId])) {
//                    $historyMap[$historyId] = $detail;
//                }
//            }
//
//            // [BƯỚC 7] - Kiểm tra kiểm kê đủ số lượng
//            $submittedIds = array_map(function($detail) {
//                return $detail['history_id'];
//            }, $data['details']);
//
//            $missingItems = array_diff(array_keys($historyMap), $submittedIds);
//            if (!empty($missingItems)) {
//                $missingCount = count($missingItems);
//                $type = $inventoryCheck->storageArea->type === 'PRODUCT' ? 'Sản phẩm' : 'Nguyên liệu';
//                http_response_code(422);
//                return [
//                    'success' => false,
//                    'error' => "Còn {$missingCount} {$type} chưa được kiểm kê"
//                ];
//            }
//
//            // [BƯỚC 8] - Xử lý cập nhật từng chi tiết
//            foreach ($data['details'] as $updateDetail) {
//                $historyId = $updateDetail['history_id'];
//
//                // Validate chi tiết
//                if (!isset($historyMap[$historyId])) {
//                    http_response_code(422);
//                    return [
//                        'success' => false,
//                        'error' => 'Không tìm thấy history trong phiếu kiểm kê'
//                    ];
//                }
//
//                if (!isset($updateDetail['actual_quantity']) || $updateDetail['actual_quantity'] < 0) {
//                    http_response_code(422);
//                    return [
//                        'success' => false,
//                        'error' => 'Số lượng thực tế không hợp lệ'
//                    ];
//                }
//
//                $detail = $historyMap[$historyId];
//                $isProduct = $detail->product_history_id !== null;
//
//                // Lấy history record hiện tại
//                if ($isProduct) {
//                    $currentHistory = ProductStorageHistory::find($detail->product_history_id);
//                } else {
//                    $currentHistory = MaterialStorageHistory::find($detail->material_history_id);
//                }
//
//                if (!$currentHistory) {
//                    http_response_code(422);
//                    return [
//                        'success' => false,
//                        'error' => 'Không tìm thấy history record'
//                    ];
//                }
//
//                // Cập nhật chi tiết kiểm kê
//                $detail->fill([
//                    'system_quantity' => $currentHistory->quantity_available,
//                    'actual_quantity' => $updateDetail['actual_quantity'],
//                    'reason' => $updateDetail['reason'] ?? null
//                ]);
//                $detail->save();
//
//                // Tạo history detail record
//                if ($isProduct) {
//                    ProductStorageHistoryDetail::create([
//                        'product_storage_history_id' => $currentHistory->id,
//                        'quantity_before' => $currentHistory->quantity_available,
//                        'quantity_change' => $updateDetail['actual_quantity'] - $currentHistory->quantity_available,
//                        'quantity_after' => $updateDetail['actual_quantity'],
//                        'action_type' => 'CHECK',
//                        'created_by' => $data['created_by']
//                    ]);
//                } else {
//                    MaterialStorageHistoryDetail::create([
//                        'material_storage_history_id' => $currentHistory->id,
//                        'quantity_before' => $currentHistory->quantity_available,
//                        'quantity_change' => $updateDetail['actual_quantity'] - $currentHistory->quantity_available,
//                        'quantity_after' => $updateDetail['actual_quantity'],
//                        'action_type' => 'CHECK',
//                        'created_by' => $data['created_by']
//                    ]);
//                }
//
//                // Cập nhật số lượng trong history
//                $currentHistory->quantity_available = $updateDetail['actual_quantity'];
//                $currentHistory->save();
//            }
//
//            // [BƯỚC 9] - Cập nhật trạng thái phiếu kiểm kê
//            $inventoryCheck->status = 'COMPLETED';
//            $inventoryCheck->completed_at = date('Y-m-d H:i:s');
//            $inventoryCheck->save();
//
//            // [BƯỚC 10] - Trả về kết quả
//            return [
//                'success' => true,
//                'data' => $inventoryCheck->load([
//                    'details' => function($query) {
//                        $query->where('deleted', false);
//                    },
//                    'storageArea',
//                    'creator',
//                    'approver'
//                ])->toArray()
//            ];
//
//        } catch (Exception $e) {
//            error_log("Error in updateInventoryCheck: " . $e->getMessage());
//            http_response_code(500);
//            return [
//                'success' => false,
//                'error' => 'Database error occurred',
//                'details' => $e->getMessage()
//            ];
//        }
//    }

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

            return [
                'success' => true,
                'data' => $inventoryCheck->load(['storageArea', 'creator'])->toArray()
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

    public function createInventoryCheckDetails(int $id): array
    {
        try {
            // [STEP 1] - Check if the inventory check exists
            $inventoryCheck = (new InventoryCheck())
                ->where('deleted', false)
                ->find($id);

            if (!$inventoryCheck) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Inventory check not found'
                ];
            }

            // [STEP 2] - Check the status of the inventory check
            if ($inventoryCheck->status !== 'APPROVED') {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Inventory check is not approved or already completed'
                ];
            }

            // [STEP 3] - Determine the storage area type
            $storageArea = $inventoryCheck->storageArea;
            if (!$storageArea) {
                http_response_code(422);
                return [
                    'success' => false,
                    'error' => 'Storage area not found'
                ];
            }

            // [STEP 4] - Get the current stock list
            $currentStock = $storageArea->type === 'PRODUCT'
                ? (new ProductStorageHistory())
                    ->where('storage_area_id', $storageArea->id)
                    ->where('status', 'ACTIVE')
                    ->where('deleted', false)
                    ->get()
                : (new MaterialStorageHistory())
                    ->where('storage_area_id', $storageArea->id)
                    ->where('status', 'ACTIVE')
                    ->where('deleted', false)
                    ->get();

            // [STEP 5] - Get the current user information from the token
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            if (!$token) {
                http_response_code(401);
                throw new \Exception('Token not found');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $currentUserId = $parsedToken->claims()->get('id');

            // [STEP 6] - Get data from the request
            $data = json_decode(file_get_contents('php://input'), true);

            // [STEP 7] - Create inventory check details
            $inventoryCheckDetails = [];
            $requestedHistoryIds = array_column($data['details'], 'history_id');

            foreach ($currentStock as $stock) {
                $historyId = $storageArea->type === 'PRODUCT' ? $stock->id : $stock->id;

                // Check if the current inventory item has a corresponding actual_quantity in the request
                if (in_array($historyId, $requestedHistoryIds)) {
                    $actualQuantity = null;
                    foreach ($data['details'] as $detail) {
                        if ($detail['history_id'] == $historyId) {
                            $actualQuantity = $detail['actual_quantity'];
                            break;
                        }
                    }

                    $detail = new InventoryCheckDetail();
                    $detail->fill([
                        'inventory_check_id' => $inventoryCheck->id,
                        // Select the appropriate field based on the storage area type
                        $storageArea->type === 'PRODUCT'
                            ? 'product_history_id'
                            : 'material_history_id' => $stock->id,
                        'system_quantity' => $stock->quantity_available,
                        'actual_quantity' => $actualQuantity,
                        'created_by' => $currentUserId
                    ]);
                    $detail->save();
                    $inventoryCheckDetails[] = $detail;

                    // Update storage history details
                    if ($storageArea->type === 'PRODUCT') {
                        ProductStorageHistoryDetail::create([
                            'product_storage_history_id' => $stock->id,
                            'quantity_before' => $stock->quantity_available,
                            'quantity_change' => $actualQuantity - $stock->quantity_available,
                            'quantity_after' => $actualQuantity,
                            'action_type' => 'CHECK',
                            'created_by' => $currentUserId
                        ]);

                        // Update quantity in the Product table
                        $product = Product::find($stock->product_id);
                        if ($product) {
                            $product->quantity_available -= ($stock->quantity_available - $actualQuantity);
                            $product->save();
                        } else {
                            error_log("Product not found for product_id: " . $stock->product_id);
                        }
                    } else {
                        MaterialStorageHistoryDetail::create([
                            'material_storage_history_id' => $stock->id,
                            'quantity_before' => $stock->quantity_available,
                            'quantity_change' => $actualQuantity - $stock->quantity_available,
                            'quantity_after' => $actualQuantity,
                            'action_type' => 'CHECK',
                            'created_by' => $currentUserId
                        ]);

                        // Update quantity in the Material table
                        $material = Material::find($stock->material_id);
                        if ($material) {
                            $material->quantity_available -= ($stock->quantity_available - $actualQuantity);
                            $material->save();
                        } else {
                            error_log("Material not found for material_id: " . $stock->material_id);
                        }
                    }

                    // Update the quantity in the storage history
                    $stock->quantity_available = $actualQuantity;
                    $stock->save();
                }
            }

            // [STEP 8] - Update the status of the inventory check
            $inventoryCheck->status = 'COMPLETED';
            $inventoryCheck->save();

            // [STEP 9] - Return the result
            return [
                'success' => true,
                'data' => [
                    'inventory_check' => $inventoryCheck->load([
                        'storageArea',
                        'creator'
                    ])->toArray(),
                    'total_items' => count($inventoryCheckDetails)
                ]
            ];
        } catch (Exception $e) {
            error_log("Error in createInventoryCheckDetails: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'An error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}