<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialImportReceipt;
use App\Models\MaterialStorageLocation;
use App\Models\Provider;
use App\Models\User;
use App\Utils\PaginationTrait;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class MaterialImportReceiptController
{
    use PaginationTrait;

    public function countTotalReceipts(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['month']) || !isset($data['year'])) {
                return [
                    'error' => 'Tháng và năm là bắt buộc'
                ];
            }

            $month = $data['month'];
            $year = $data['year'];

            $totalReceipts = (new MaterialImportReceipt())
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->count();

            return [
                'data' => ['total_receipts' => $totalReceipts]
            ];

        } catch (\Exception $e) {
            error_log("Error in countTotalReceipts: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialImportReceipts(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialIR = (new MaterialImportReceipt())
                ->where('deleted', false)
                ->with([
                    'provider',
                    'creator' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'approver' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'receiver' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details'
                ])
                ->orderByRaw("CASE 
                WHEN status = 'COMPLETED' THEN 1 
                WHEN status = 'PENDING_APPROVED' THEN 2
                WHEN status = 'APPROVED' THEN 3 
                WHEN status = 'REJECTED' THEN 4 
                END")
                ->orderByRaw("CASE 
                WHEN status = 'RETURN' THEN 1 
                WHEN status = 'NORMAL' THEN 2
                WHEN status = 'OTHER' THEN 3 
                END")
                ->orderBy('created_at', 'desc');

            if (isset($_GET['provider_id'])) {
                $providerId = urldecode($_GET['provider_id']);
                $materialIR->where('provider_id', $providerId);
            }

            if (isset($_GET['code'])) {
                $code = urldecode($_GET['code']);
                $materialIR->where('code', 'like', '%' . $code . '%');
            }

            if (isset($_GET['type'])) {
                $materialIR->where('type', urldecode($_GET['type']));
            }

            if (isset($_GET['status'])) {
                $materialIR->where('status', urldecode($_GET['status']));
            }

            if (isset($_GET['total_price'])) {
                $materialIR->where('total_price', urldecode($_GET['total_price']));
            }

            if (isset($_GET['total_price_min'])) {
                $materialIR->where('total_price', '>=', urldecode($_GET['total_price_min']));
            }

            if (isset($_GET['total_price_max'])) {
                $materialIR->where('total_price', '<=', urldecode($_GET['total_price_max']));
            }

            if (isset($_GET['note'])) {
                $note = urldecode($_GET['note']);
                $materialIR->where('note', '%' . $note . '%');
            }

            $result = $this->paginateResults($materialIR, $perPage, $page)->toArray();

            if (isset($result['data']) && is_array($result['data'])) {
                foreach ($result['data'] as &$item) {
                    if (isset($item['creator']['profile'])) {
                        $item['creator']['full_name'] = trim($item['creator']['profile']['first_name'] . ' ' . $item['creator']['profile']['last_name']);
                    }
                    if (isset($item['approver']['profile'])) {
                        $item['approver']['full_name'] = trim($item['approver']['profile']['first_name'] . ' ' . $item['approver']['profile']['last_name']);
                    }
                    if (isset($item['receiver']['profile'])) {
                        $item['receiver']['full_name'] = trim($item['receiver']['profile']['first_name'] . ' ' . $item['receiver']['profile']['last_name']);
                    }
                }
            }

            return $result;

        } catch (\Exception $e) {
            error_log("Error in getMaterialImportReceipts: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialImportReceiptByCode($code): array
    {
        try {
            $materialIR = (new MaterialImportReceipt())
                ->where('code', $code)
                ->where('deleted', false)
                ->with([
                    'provider',
                    'creator' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'approver' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'receiver' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details.materialStorageLocation',
                    'details.material'
                ])->first();

            if (!$materialIR) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = $materialIR->toArray();

            if (isset($data['creator']['profile'])) {
                $data['creator']['full_name'] = trim($data['creator']['profile']['first_name'] . ' ' . $data['creator']['profile']['last_name']);
            }
            if (isset($data['approver']['profile'])) {
                $data['approver']['full_name'] = trim($data['approver']['profile']['first_name'] . ' ' . $data['approver']['profile']['last_name']);
            }
            if (isset($data['receiver']['profile'])) {
                $data['receiver']['full_name'] = trim($data['receiver']['profile']['first_name'] . ' ' . $data['receiver']['profile']['last_name']);
            }

            return $data;

        } catch (\Exception $e) {
            error_log("Error in getMaterialImportReceiptById: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getImportReceiptDetailsByImportReceipt($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialIR = (new MaterialImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$materialIR) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $detailsQuery = $materialIR->details()
                ->with(['material', 'storageArea', 'materialImportReceipt'])
                ->getQuery();

            return $this->paginateResults($detailsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getImportReceiptDetailsByImportReceipt: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProvidersByImportReceipt($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialIR = (new MaterialImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$materialIR) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $providersQuery = $materialIR->provider()
                ->with(['materials', 'materialImportReceipt'])
                ->getQuery();

            return $this->paginateResults($providersQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProvidersByImportReceipt: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateMaterialImportReceiptById($id): array
    {
        try {
            $materialIR = (new MaterialImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$materialIR) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $materialIR->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $materialIR->fill($data);
            $materialIR->save();

            return [
                'success' => true,
                'data' => $materialIR->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateMaterialImportReceiptById: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteMaterialImportReceipt($id): array
    {
        try {
            $materialIR = (new MaterialImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$materialIR) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $materialIR->deleted = true;
            $materialIR->save();

            return [
                'success' => true,
                'message' => 'Xóa thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteMaterialImportReceipt: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function approveImportReceipt($id): array
    {
        try {
            $materialIR = (new MaterialImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->where('status', 'PENDING')
                ->first();

            if (!$materialIR) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy phiếu nhập hoặc phiếu không ở trạng thái chờ duyệt'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['approved_by'])) {
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin người duyệt'
                ];
            }

            // Cập nhật trạng thái và người duyệt
            $materialIR->status = 'COMPLETED';
            $materialIR->approved_by = $data['approved_by'];
            $materialIR->save();

            return [
                'success' => true,
                'message' => 'Duyệt phiếu nhập thành công',
                'data' => $materialIR->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in approveImportReceipt: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function importMaterials()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            // Validate basic required fields
            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'RETURN'])) {
                throw new \Exception('Type phải là NORMAL hoặc RETURN');
            }

            if (!isset($data['receiver_id'])) {
                throw new \Exception('receiver_id là bắt buộc');
            }

            if (!isset($data['materials']) || !is_array($data['materials'])) {
                throw new \Exception('Danh sách materials là bắt buộc và phải là một mảng');
            }

            // Validate fields based on type
            $allowedFields = [
                'NORMAL' => ['type', 'provider_id', 'material_storage_location_id', 'receiver_id', 'note', 'materials'],
                'RETURN' => ['type', 'receiver_id', 'material_storage_location_id', 'note', 'materials']
            ];

            $allowedMaterialFields = [
                'NORMAL' => ['material_id', 'quantity', 'price'],
                'RETURN' => ['material_id', 'quantity']
            ];

            // Check for unexpected fields in main request
            foreach ($data as $field => $value) {
                if (!in_array($field, $allowedFields[$data['type']])) {
                    throw new \Exception("Trường '$field' không được phép với type " . $data['type']);
                }
            }

            // Check for required fields
            if ($data['type'] === 'NORMAL') {
                if (!isset($data['provider_id'])) {
                    throw new \Exception('provider_id là bắt buộc với type NORMAL');
                }
                if (!isset($data['material_storage_location_id'])) {
                    throw new \Exception('material_storage_location_id là bắt buộc với type NORMAL');
                }

                $providerExists = Provider::where('id', $data['provider_id'])->exists();
                if (!$providerExists) {
                    throw new \Exception('Nhà cung cấp không tồn tại');
                }
            }

            // Validate storage location
            if (!isset($data['material_storage_location_id'])) {
                throw new \Exception('material_storage_location_id là bắt buộc');
            }

            $storageLocationExists = MaterialStorageLocation::where('id', $data['material_storage_location_id'])->exists();
            if (!$storageLocationExists) {
                throw new \Exception('Vị trí lưu trữ không tồn tại');
            }

            // Validate materials array
            foreach ($data['materials'] as $material) {
                // Check for unexpected fields in materials
                foreach ($material as $field => $value) {
                    if (!in_array($field, $allowedMaterialFields[$data['type']])) {
                        throw new \Exception("Trường '$field' trong materials không được phép với type " . $data['type']);
                    }
                }

                // Check required fields for materials
                if (!isset($material['material_id']) || !isset($material['quantity'])) {
                    throw new \Exception('material_id và quantity là bắt buộc cho mỗi nguyên liệu');
                }

                if ($data['type'] === 'NORMAL' && !isset($material['price'])) {
                    throw new \Exception('price là bắt buộc cho mỗi nguyên liệu với type NORMAL');
                }

                // Validate material exists
                $materialExists = Material::where('id', $material['material_id'])->exists();
                if (!$materialExists) {
                    throw new \Exception('Nguyên liệu không tồn tại: ' . $material['material_id']);
                }
            }

            // Lấy thông tin người dùng từ token
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                throw new \Exception('Token không tồn tại');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $createdById = $parsedToken->claims()->get('id');

            // Kiểm tra người nhận
            $receiver = User::where('id', $data['receiver_id'])
                ->where('status', 'ACTIVE')
                ->where('deleted', false)
                ->first();

            if (!$receiver) {
                throw new \Exception('Người nhận không tồn tại hoặc không hoạt động');
            }

            // Tạo mã phiếu xuất tự động
            $currentDay = date('d');
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "PNNVL" . $currentDay . $currentMonth . $currentYear;

            // Lấy phiếu xuất mới nhất với prefix hiện tại
            $latestExportReceipt = MaterialImportReceipt::query()
                ->where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            if ($latestExportReceipt) {
                // Lấy số thứ tự và tăng lên 1
                $sequence = intval(substr($latestExportReceipt->code, -5)) + 1;
            } else {
                $sequence = 1;
            }

            // Định dạng số thứ tự thành 5 chữ số
            $code = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);

            // Tạo phiếu nhập
            $materialImportReceipt = MaterialImportReceipt::create([
                'provider_id' => $data['type'] === 'NORMAL' ? $data['provider_id'] : null,
                'code' => $code,
                'type' => $data['type'],
                'note' => $data['note'] ?? null,
                'created_by' => $createdById,
                'receiver_id' => $receiver->id,
                'status' => $data['type'] === 'NORMAL' ? 'PENDING_APPROVED' : 'COMPLETED'
            ]);

            $totalPrice = 0;

            foreach ($data['materials'] as $material) {
                $materialModel = Material::find($material['material_id']);
                $price = $data['type'] === 'NORMAL' ? $material['price'] : 0;
                $quantity = $material['quantity'];

                if ($data['type'] === 'NORMAL') {
                    $totalPrice += $price * $quantity;
                }

                // Tạo chi tiết phiếu nhập
                $materialImportReceiptDetail = $materialImportReceipt->details()->create([
                    'material_id' => $material['material_id'],
                    'material_storage_location_id' => $data['material_storage_location_id'],
                    'quantity' => $quantity,
                    'price' => $price
                ]);

                // Cập nhật số lượng trong material storage location
                $materialStorageLocation = MaterialStorageLocation::find($data['material_storage_location_id']);
                $materialStorageLocation->quantity += $quantity;
                $materialStorageLocation->save();

                // Cập nhật số lượng trong bảng Materials
                $materialModel->quantity_available += $quantity;
                $materialModel->save();
            }

            // Cập nhật tổng giá nếu là type NORMAL
            if ($data['type'] === 'NORMAL') {
                $materialImportReceipt->total_price = $totalPrice;
                $materialImportReceipt->save();
            }

            $response = [
                'message' => 'Nhập kho thành công',
                'material_import_receipt_id' => $materialImportReceipt->id,
                'code' => $materialImportReceipt->code
            ];

            header('Content-Type: application/json');
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}