<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialExportReceipt;
use App\Models\MaterialImportReceipt;
use App\Models\MaterialImportReceiptDetail;
use App\Models\MaterialStorageLocation;
use App\Utils\PaginationTrait;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class MaterialExportReceiptController
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

            $totalReceipts = MaterialExportReceipt::whereMonth('created_at', $month)
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

    public function getMaterialExportReceipts(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialER = (new MaterialExportReceipt())
                ->where('deleted', false)
                ->with([
                    'creator' => function ($productER) {
                        $productER->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details'
                ])
                ->orderByRaw("CASE 
                    WHEN status = 'COMPLETED' THEN 1 
                    WHEN status = 'TEMPORARY' THEN 2
                END")
                ->orderByRaw("CASE 
                    WHEN type = 'RETURN' THEN 1
                    WHEN type = 'NORMAL' THEN 2
                    WHEN type = 'OTHER' THEN 3
                    WHEN type = 'CANCEL' THEN 4
                END")
                ->orderBy('created_at', 'desc');

            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                $materialER->where('type', $type);
            }

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $materialER->where('status', $status);
            }

            if (isset($_GET['note'])) {
                $note = urldecode($_GET['note']);
                $materialER->where('note', '%' . $note . '%');
            }

            $result = $this->paginateResults($materialER, $perPage, $page)->toArray();

            if (isset($result['data']) && is_array($result['data'])) {
                foreach ($result['data'] as &$item) {
                    if (isset($item['creator']['profile'])) {
                        $item['creator']['full_name'] = trim($item['creator']['profile']['first_name'] . ' ' . $item['creator']['profile']['last_name']);
                    }
                }
            }

            return $result;

        } catch (\Exception $e) {
            error_log("Error in getMaterialExportReceipts: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialExportReceiptByCode($code): array
    {
        try {
            $materialER = (new MaterialExportReceipt())
                ->where('code', $code)
                ->where('deleted', false)
                ->with([
                    'creator' => function ($productER) {
                        $productER->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details.materialStorageLocation',
                    'details.material'
                ])
                ->first();

            if (!$materialER) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = $materialER->toArray();

            if (isset($data['creator']['profile'])) {
                $data['creator']['full_name'] = trim($data['creator']['profile']['first_name'] . ' ' . $data['creator']['profile']['last_name']);
            }

            return $data;

        } catch (\Exception $e) {
            error_log("Error in getMaterialExportReceiptById: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getExportReceiptDetailsByExportReceipt($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialER = (new MaterialExportReceipt())->find($id);

            if (!$materialER) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $detailsQuery = $materialER->details()
                ->with(['material', 'storageArea', 'materialExportReceipt'])
                ->getQuery();

            return $this->paginateResults($detailsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getExportReceiptDetailsByExportReceipt: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateMaterialExportReceiptById($id): array
    {
        try {
            $materialER = (new MaterialExportReceipt())->find($id);

            if (!$materialER) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $materialER->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $materialER->fill($data);
            $materialER->save();

            return [
                'success' => true,
                'data' => $materialER->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateMaterialExportReceiptById: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteMaterialExportReceipt($id): array
    {
        try {
            $materialER = (new MaterialExportReceipt())->find($id);

            if (!$materialER) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $materialER->deleted = true;
            $materialER->save();

            return [
                'success' => true,
                'message' => 'Xóa thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteMaterialExportReceipt: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function exportMaterials(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            // Validate basic required fields
            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'CANCEL', 'RETURN'])) {
                throw new \Exception('Type phải là NORMAL, CANCEL hoặc RETURN');
            }

            // Validate fields based on type
            $allowedFields = [
                'NORMAL' => ['type', 'material_storage_location_id', 'note', 'materials'],
                'CANCEL' => ['type', 'material_storage_location_id', 'note', 'materials'],
                'RETURN' => ['type', 'material_storage_location_id', 'note', 'materials', 'material_import_receipt_id']
            ];

            // Check for unexpected fields in main request
            foreach ($data as $field => $value) {
                if (!in_array($field, $allowedFields[$data['type']])) {
                    throw new \Exception("Trường '$field' không được phép với type " . $data['type']);
                }
            }

            // Validate required fields based on type
            if ($data['type'] === 'RETURN') {
                if (!isset($data['material_import_receipt_id'])) {
                    throw new \Exception('material_import_receipt_id là bắt buộc với type RETURN');
                }

                // Kiểm tra phiếu nhập có tồn tại không
                $importReceipt = MaterialImportReceipt::find($data['material_import_receipt_id']);
                if (!$importReceipt) {
                    throw new \Exception('Phiếu nhập không tồn tại');
                }
            }

            // Lấy token từ header
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                throw new \Exception('Token không tồn tại');
            }

            // Giải mã token và lấy ID người dùng
            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $createdById = $parsedToken->claims()->get('id');

            // Validate storage location
            if (!isset($data['material_storage_location_id'])) {
                throw new \Exception('material_storage_location_id là bắt buộc');
            }

            $materialStorageLocation = MaterialStorageLocation::where('id', $data['material_storage_location_id'])
                ->where('deleted', false)
                ->first();

            if (!$materialStorageLocation) {
                throw new \Exception('Vị trí lưu trữ không tồn tại hoặc không hoạt động');
            }

            // Kiểm tra và validate materials
            $materials = $data['materials'] ?? [];
            if (empty($materials)) {
                throw new \Exception('Danh sách materials không được để trống');
            }

            // Kiểm tra tất cả nguyên vật liệu trước khi tạo hóa đơn
            $missingMaterials = [];
            foreach ($materials as $material) {
                if (!isset($material['material_id']) || !isset($material['quantity'])) {
                    throw new \Exception('material_id và quantity là bắt buộc cho mỗi nguyên liệu');
                }

                $materialModel = Material::find($material['material_id']);
                if (!$materialModel) {
                    $missingMaterials[] = "Nguyên vật liệu (ID: {$material['material_id']}) không tồn tại";
                    continue;
                }

                // Kiểm tra số lượng trong kho
                $currentLocation = MaterialStorageLocation::find($data['material_storage_location_id']);
                if ($currentLocation->quantity < $material['quantity']) {
                    $missingMaterials[] = "{$materialModel->name} không đủ số lượng trong kho. Có sẵn: {$currentLocation->quantity}, Yêu cầu: {$material['quantity']}";
                }

                // Kiểm tra thêm cho type RETURN
                if ($data['type'] === 'RETURN') {
                    $importDetail = MaterialImportReceiptDetail::where('material_import_receipt_id', $data['material_import_receipt_id'])
                        ->where('material_id', $material['material_id'])
                        ->first();

                    if (!$importDetail) {
                        $missingMaterials[] = "{$materialModel->name} không có trong phiếu nhập được chọn";
                    } elseif ($material['quantity'] > $importDetail->quantity) {
                        $missingMaterials[] = "Số lượng trả về của {$materialModel->name} không được lớn hơn số lượng đã nhập ({$importDetail->quantity})";
                    }
                }
            }

            if (!empty($missingMaterials)) {
                throw new \Exception("Tạo phiếu xuất kho thất bại. " . implode(". ", $missingMaterials));
            }

            // Tạo mã phiếu xuất tự động
            do {
                $code = 'EXP' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $existingReceipt = MaterialExportReceipt::where('code', $code)->exists();
            } while ($existingReceipt);

            // Tạo mới MaterialExportReceipt
            $materialExportReceipt = MaterialExportReceipt::create([
                'code' => $code,
                'note' => $data['note'] ?? null,
                'type' => $data['type'],
                'status' => $data['type'] === 'NORMAL' ? 'TEMPORARY' : 'COMPLETED',
                'created_by' => $createdById
            ]);

            // Tạo chi tiết xuất kho và cập nhật số lượng
            foreach ($materials as $material) {
                // Tạo chi tiết xuất kho
                $materialExportReceipt->details()->create([
                    'material_id' => $material['material_id'],
                    'material_storage_location_id' => $data['material_storage_location_id'],
                    'quantity' => $material['quantity']
                ]);

                // Cập nhật số lượng trong kho
                $materialStorageLocation = MaterialStorageLocation::find($data['material_storage_location_id']);
                $materialStorageLocation->quantity -= $material['quantity'];
                $materialStorageLocation->save();

                // Cập nhật số lượng trong bảng materials
                $materialModel = Material::find($material['material_id']);
                $materialModel->quantity_available -= $material['quantity'];
                $materialModel->save();
            }

            $response = [
                'message' => 'Xuất kho thành công',
                'material_export_receipt_id' => $materialExportReceipt->id,
                'code' => $materialExportReceipt->code
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