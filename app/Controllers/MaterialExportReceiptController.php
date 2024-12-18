<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialExportReceipt;
use App\Models\MaterialImportReceipt;
use App\Models\MaterialImportReceiptDetail;
use App\Models\MaterialStorageHistoryDetail;
use App\Models\MaterialStorageHistory;
use App\Models\StorageArea;
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
                ->orderBy('created_at', 'desc');

            // Code filter
            if (isset($_GET['code'])) {
                $code = urldecode($_GET['code']);
                $materialER->where('code', 'LIKE', '%' . $code . '%');
            }

            // Creator filter
            if (isset($_GET['created_by'])) {
                $createdBy = urldecode($_GET['created_by']);
                $materialER->where('created_by', $createdBy);
            }

            // Receipt Date filters
            if (isset($_GET['receipt_date'])) {
                $receiptDate = urldecode($_GET['receipt_date']);
                $materialER->whereDate('receipt_date', $receiptDate);
            }
            if (isset($_GET['receipt_date_from'])) {
                $receiptDateFrom = urldecode($_GET['receipt_date_from']);
                $materialER->whereDate('receipt_date', '>=', $receiptDateFrom);
            }
            if (isset($_GET['receipt_date_to'])) {
                $receiptDateTo = urldecode($_GET['receipt_date_to']);
                $materialER->whereDate('receipt_date', '<=', $receiptDateTo);
            }

            // Type filter
            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                $materialER->where('type', $type);
            }

            // Status filter
            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $materialER->where('status', $status);
            }

            // Note filter
            if (isset($_GET['note'])) {
                $note = urldecode($_GET['note']);
                $materialER->where('note', 'LIKE', '%' . $note . '%');
            }

            // Created At filters
            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $materialER->where('created_at', '>=', $createdFrom);
            }
            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $materialER->where('created_at', '<=', $createdTo);
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
                    'details.storageArea',
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

//    public function exportMaterials(): void
//    {
//        $data = json_decode(file_get_contents('php://input'), true);
//
//        try {
//            // [BƯỚC 1] - Validate basic required fields
//            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'CANCEL', 'RETURN'])) {
//                throw new \Exception('Type phải là NORMAL, CANCEL hoặc RETURN');
//            }
//
//            // [BƯỚC 2] - Validate allowed fields
//            $allowedFields = [
//                'NORMAL' => ['type', 'note', 'materials'],
//                'CANCEL' => ['type', 'note', 'materials'],
//                'RETURN' => ['type', 'note', 'materials', 'material_import_receipt_id']
//            ];
//
//            foreach ($data as $field => $value) {
//                if (!in_array($field, $allowedFields[$data['type']])) {
//                    throw new \Exception("Trường '$field' không được phép với type " . $data['type']);
//                }
//            }
//
//            // [BƯỚC 3] - Token validation
//            $headers = apache_request_headers();
//            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
//            if (!$token) {
//                throw new \Exception('Token không tồn tại');
//            }
//
//            $parser = new Parser(new JoseEncoder());
//            $parsedToken = $parser->parse($token);
//            $createdById = $parsedToken->claims()->get('id');
//
//            // [BƯỚC 4] - Type specific validation
//            $importReceipt = null;
//            if ($data['type'] === 'RETURN') {
//                if (!isset($data['material_import_receipt_id'])) {
//                    throw new \Exception('material_import_receipt_id là bắt buộc với type RETURN');
//                }
//
//                $importReceipt = MaterialImportReceipt::where('id', $data['material_import_receipt_id'])
//                    ->where('deleted', false)
//                    ->first();
//
//                if (!$importReceipt) {
//                    throw new \Exception('Phiếu nhập không tồn tại hoặc đã bị xóa');
//                }
//            }
//
//            // [BƯỚC 5] - Validate materials
//            if (!isset($data['materials']) || empty($data['materials'])) {
//                throw new \Exception('Danh sách materials không được để trống');
//            }
//
//            $validatedMaterials = [];
//            foreach ($data['materials'] as $material) {
//                if (!isset($material['material_id']) || !isset($material['quantity']) ||
//                    !isset($material['storage_area_id'])) {
//                    throw new \Exception('material_id, quantity và storage_area_id là bắt buộc cho mỗi nguyên liệu');
//                }
//
//                // Kiểm tra số lượng xuất
//                if ($material['quantity'] <= 0) {
//                    throw new \Exception("Số lượng xuất phải lớn hơn 0");
//                }
//
//                // Kiểm tra kho tồn tại và loại kho
//                $storageArea = StorageArea::where('id', $material['storage_area_id'])
//                    ->where('deleted', false)
//                    ->first();
//
//                if (!$storageArea) {
//                    throw new \Exception("Kho chứa ID {$material['storage_area_id']} không tồn tại hoặc đã bị xóa");
//                }
//
//                if ($storageArea->type !== 'MATERIAL') {
//                    throw new \Exception("Kho chứa {$storageArea->name} không phải là kho nguyên vật liệu");
//                }
//
//                // Kiểm tra history có số lượng đủ không
//                $histories = MaterialStorageHistory::where('material_id', $material['material_id'])
//                    ->where('storage_area_id', $material['storage_area_id'])
//                    ->where('status', 'ACTIVE')
//                    ->where('deleted', false)
//                    ->where('quantity_available', '>', 0)
//                    ->orderBy('expiry_date', 'asc')
//                    ->get();
//
//                if ($histories->isEmpty()) {
//                    throw new \Exception("Không tìm thấy nguyên liệu ID {$material['material_id']} trong kho {$material['storage_area_id']}");
//                }
//
//                $totalAvailable = $histories->sum('quantity_available');
//                if ($totalAvailable < $material['quantity']) {
//                    throw new \Exception(
//                        "Không đủ số lượng trong kho cho nguyên liệu ID {$material['material_id']}. " .
//                        "Cần: {$material['quantity']}, Có sẵn: {$totalAvailable}"
//                    );
//                }
//
//                // Kiểm tra thêm cho type RETURN
//                if ($data['type'] === 'RETURN') {
//                    $importDetail = MaterialImportReceiptDetail::where('material_import_receipt_id', $data['material_import_receipt_id'])
//                        ->where('material_id', $material['material_id'])
//                        ->where('deleted', false)
//                        ->first();
//
//                    if (!$importDetail) {
//                        throw new \Exception("Nguyên liệu ID {$material['material_id']} không có trong phiếu nhập");
//                    }
//
//                    if ($material['quantity'] > $importDetail->quantity) {
//                        throw new \Exception(
//                            "Số lượng trả về ({$material['quantity']}) vượt quá số lượng trong phiếu nhập ({$importDetail->quantity})"
//                        );
//                    }
//                }
//
//                $material['histories'] = $histories;
//                $validatedMaterials[] = $material;
//            }
//
//            // [BƯỚC 6] - Generate receipt code
//            $currentDay = date('d');
//            $currentMonth = date('m');
//            $currentYear = date('y');
//            $prefix = "PXNVL" . $currentDay . $currentMonth . $currentYear;
//
//            $latestExportReceipt = MaterialExportReceipt::where('code', 'LIKE', $prefix . '%')
//                ->orderBy('code', 'desc')
//                ->first();
//
//            $sequence = $latestExportReceipt ? intval(substr($latestExportReceipt->code, -5)) + 1 : 1;
//            $code = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
//
//            // [BƯỚC 7] - Create export receipt
//            $materialExportReceipt = MaterialExportReceipt::create([
//                'code' => $code,
//                'note' => $data['note'] ?? '',
//                'type' => $data['type'],
//                'status' => 'COMPLETED',
//                'created_by' => $createdById,
//                'material_import_receipt_id' => $data['type'] === 'RETURN' ? $data['material_import_receipt_id'] : null,
//            ]);
//
//            // [BƯỚC 8] - Create details and update quantities
//            foreach ($validatedMaterials as $material) {
//                $remainingQuantity = $material['quantity'];
//                $histories = $material['histories'];
//
//                foreach ($histories as $history) {
//                    if ($remainingQuantity <= 0) break;
//
//                    $quantityToTake = min($remainingQuantity, $history->quantity_available);
//
//                    // Set INACTIVE cho history cũ
//                    $history->status = 'INACTIVE';
//                    $history->save();
//
//                    // Tạo history mới
//                    $newHistory = new MaterialStorageHistory();
//                    $newHistory->material_id = $material['material_id'];
//                    $newHistory->storage_area_id = $material['storage_area_id'];
//                    $newHistory->provider_id = $history->provider_id;
//                    $newHistory->expiry_date = $history->expiry_date;
//                    $newHistory->quantity = $history->quantity;
//                    $newHistory->quantity_available = $history->quantity_available - $quantityToTake;
//                    $newHistory->status = 'ACTIVE';
//                    $newHistory->deleted = false;
//                    $newHistory->save();
//
//                    // Tạo chi tiết xuất kho
//                    $materialExportReceipt->details()->create([
//                        'material_id' => $material['material_id'],
//                        'storage_area_id' => $material['storage_area_id'],
//                        'quantity' => $quantityToTake,
//                        'expiry_date' => $history->expiry_date
//                    ]);
//
//                    $remainingQuantity -= $quantityToTake;
//                }
//
//                // Cập nhật số lượng trong bảng materials
//                $materialModel = Material::find($material['material_id']);
//                $oldQuantity = $materialModel->quantity_available;
//                $materialModel->quantity_available -= $material['quantity'];
//                $materialModel->save();
//
//                // Tạo material inventory history
//                $actionType = match($data['type']) {
//                    'NORMAL' => 'EXPORT_NORMAL',
//                    'CANCEL' => 'EXPORT_CANCEL',
//                    'RETURN' => 'EXPORT_RETURN'
//                };
//
//                MaterialStorageHistoryDetail::create([
//                    'storage_area_id' => $material['storage_area_id'],
//                    'material_id' => $material['material_id'],
//                    'quantity_before' => $oldQuantity,
//                    'quantity_change' => -$material['quantity'], // Dấu trừ vì là xuất kho
//                    'quantity_after' => $materialModel->quantity_available,
//                    'remaining_quantity' => $materialModel->quantity_available,
//                    'action_type' => $actionType,
//                    'created_by' => $createdById
//                ]);
//            }
//
//            // [BƯỚC 9] - Load relationships for response
//            $exportReceipt = MaterialExportReceipt::with([
//                'details.material',
//                'details.storageArea',
//                'creator.profile',
//            ])->find($materialExportReceipt->id);
//
//            // [BƯỚC 10] - Prepare response
//            $response = [
//                'success' => true,
//                'message' => 'Xuất kho thành công',
//                'data' => [
//                    'id' => $exportReceipt->id,
//                    'code' => $exportReceipt->code,
//                    'type' => $exportReceipt->type,
//                    'status' => $exportReceipt->status,
//                    'note' => $exportReceipt->note,
//                    'created_at' => $exportReceipt->created_at,
//                    'creator' => [
//                        'id' => $exportReceipt->creator->id,
//                        'email' => $exportReceipt->creator->email,
//                        'profile' => [
//                            'id' => $exportReceipt->creator->profile->id,
//                            'first_name' => $exportReceipt->creator->profile->first_name,
//                            'last_name' => $exportReceipt->creator->profile->last_name,
//                        ]
//                    ],
//                ]
//            ];
//
//            // Thêm thông tin phiếu nhập nếu là RETURN
//            if ($data['type'] === 'RETURN' && $importReceipt) {
//                $response['data']['import_receipt'] = [
//                    'id' => $importReceipt->id,
//                    'code' => $importReceipt->code,
//                    'created_at' => $importReceipt->created_at,
//                    'provider' => [
//                        'id' => $importReceipt->provider_id,
//                        'name' => $importReceipt->provider->name,
//                        'code' => $importReceipt->provider->code
//                    ]
//                ];
//            }
//
//            // Thêm chi tiết xuất kho và thông tin history
//            $response['data']['details'] = $exportReceipt->details->map(function ($detail) {
//                // Lấy history mới nhất
//                $latestHistory = MaterialStorageHistory::where([
//                    'material_id' => $detail->material_id,
//                    'storage_area_id' => $detail->storage_area_id,
//                    'status' => 'ACTIVE',
//                    'deleted' => false
//                ])->first();
//
//                return [
//                    'id' => $detail->id,
//                    'material' => [
//                        'id' => $detail->material->id,
//                        'sku' => $detail->material->sku,
//                        'name' => $detail->material->name,
//                    ],
//                    'storage_area' => [
//                        'id' => $detail->storageArea->id,
//                        'name' => $detail->storageArea->name,
//                        'code' => $detail->storageArea->code,
//                    ],
//                    'quantity' => $detail->quantity,
//                    'expiry_date' => $detail->expiry_date,
//                    'created_at' => $detail->created_at,
//                    'history' => [
//                        'quantity_available' => $latestHistory ? $latestHistory->quantity_available : 0,
//                        'status' => $latestHistory ? $latestHistory->status : null
//                    ]
//                ];
//            });
//
//            header('Content-Type: application/json');
//            echo json_encode($response, JSON_UNESCAPED_UNICODE);
//
//        } catch (\Exception $e) {
//            header('Content-Type: application/json');
//            http_response_code(400);
//            echo json_encode([
//                'success' => false,
//                'message' => $e->getMessage()
//            ], JSON_UNESCAPED_UNICODE);
//        }
//    }

//    public function exportMaterials(): void
//    {
//        $data = json_decode(file_get_contents('php://input'), true);
//
//        try {
//            // [BƯỚC 1] - Validate basic required fields
//            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'CANCEL', 'RETURN'])) {
//                throw new \Exception('Type phải là NORMAL, CANCEL hoặc RETURN');
//            }
//
//            // [BƯỚC 2] - Validate allowed fields
//            $allowedFields = [
//                'NORMAL' => ['type', 'note', 'materials'],
//                'CANCEL' => ['type', 'note', 'materials'],
//                'RETURN' => ['type', 'note', 'materials', 'material_import_receipt_id']
//            ];
//
//            foreach ($data as $field => $value) {
//                if (!in_array($field, $allowedFields[$data['type']])) {
//                    throw new \Exception("Trường '$field' không được phép với type " . $data['type']);
//                }
//            }
//
//            // [BƯỚC 3] - Token validation
//            $headers = apache_request_headers();
//            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
//            if (!$token) {
//                throw new \Exception('Token không tồn tại');
//            }
//
//            $parser = new Parser(new JoseEncoder());
//            $parsedToken = $parser->parse($token);
//            $createdById = $parsedToken->claims()->get('id');
//
//            // [BƯỚC 4] - Type specific validation
//            $importReceipt = null;
//            if ($data['type'] === 'RETURN') {
//                if (!isset($data['material_import_receipt_id'])) {
//                    throw new \Exception('material_import_receipt_id là bắt buộc với type RETURN');
//                }
//
//                $importReceipt = MaterialImportReceipt::where('id', $data['material_import_receipt_id'])
//                    ->where('deleted', false)
//                    ->first();
//
//                if (!$importReceipt) {
//                    throw new \Exception('Phiếu nhập không tồn tại hoặc đã bị xóa');
//                }
//            }
//
//            // [BƯỚC 5] - Validate materials
//            if (!isset($data['materials']) || empty($data['materials'])) {
//                throw new \Exception('Danh sách materials không được để trống');
//            }
//
//            $validatedMaterials = [];
//            foreach ($data['materials'] as $material) {
//                if (!isset($material['material_id']) || !isset($material['quantity']) ||
//                    !isset($material['storage_area_id'])) {
//                    throw new \Exception('material_id, quantity và storage_area_id là bắt buộc cho mỗi nguyên liệu');
//                }
//
//                // Kiểm tra số lượng xuất
//                if ($material['quantity'] <= 0) {
//                    throw new \Exception("Số lượng xuất phải lớn hơn 0");
//                }
//
//                // Kiểm tra kho tồn tại và loại kho
//                $storageArea = StorageArea::where('id', $material['storage_area_id'])
//                    ->where('deleted', false)
//                    ->first();
//
//                if (!$storageArea) {
//                    throw new \Exception("Kho chứa ID {$material['storage_area_id']} không tồn tại hoặc đã bị xóa");
//                }
//
//                if ($storageArea->type !== 'MATERIAL') {
//                    throw new \Exception("Kho chứa {$storageArea->name} không phải là kho nguyên vật liệu");
//                }
//
//                // Kiểm tra history có số lượng đủ không
//                $histories = MaterialStorageHistory::where('material_id', $material['material_id'])
//                    ->where('storage_area_id', $material['storage_area_id'])
//                    ->where('status', 'ACTIVE')
//                    ->where('deleted', false)
//                    ->where('quantity_available', '>', 0)
//                    ->orderBy('expiry_date', 'asc')
//                    ->get();
//
//                if ($histories->isEmpty()) {
//                    throw new \Exception("Không tìm thấy nguyên liệu ID {$material['material_id']} trong kho {$material['storage_area_id']}");
//                }
//
//                $totalAvailable = $histories->sum('quantity_available');
//                if ($totalAvailable < $material['quantity']) {
//                    throw new \Exception(
//                        "Không đủ số lượng trong kho cho nguyên liệu ID {$material['material_id']}. " .
//                        "Cần: {$material['quantity']}, Có sẵn: {$totalAvailable}"
//                    );
//                }
//
//                // Kiểm tra thêm cho type RETURN
//                if ($data['type'] === 'RETURN') {
//                    $importDetail = MaterialImportReceiptDetail::where('material_import_receipt_id', $data['material_import_receipt_id'])
//                        ->where('material_id', $material['material_id'])
//                        ->where('deleted', false)
//                        ->first();
//
//                    if (!$importDetail) {
//                        throw new \Exception("Nguyên liệu ID {$material['material_id']} không có trong phiếu nhập");
//                    }
//
//                    if ($material['quantity'] > $importDetail->quantity) {
//                        throw new \Exception(
//                            "Số lượng trả về ({$material['quantity']}) vượt quá số lượng trong phiếu nhập ({$importDetail->quantity})"
//                        );
//                    }
//                }
//
//                $material['histories'] = $histories;
//                $validatedMaterials[] = $material;
//            }
//
//            // [BƯỚC 6] - Generate receipt code
//            $currentDay = date('d');
//            $currentMonth = date('m');
//            $currentYear = date('y');
//            $prefix = "PXNVL" . $currentDay . $currentMonth . $currentYear;
//
//            $latestExportReceipt = MaterialExportReceipt::where('code', 'LIKE', $prefix . '%')
//                ->orderBy('code', 'desc')
//                ->first();
//
//            $sequence = $latestExportReceipt ? intval(substr($latestExportReceipt->code, -5)) + 1 : 1;
//            $code = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
//
//            // [BƯỚC 7] - Create export receipt
//            $materialExportReceipt = MaterialExportReceipt::create([
//                'code' => $code,
//                'note' => $data['note'] ?? '',
//                'type' => $data['type'],
//                'status' => 'COMPLETED',
//                'created_by' => $createdById,
//                'material_import_receipt_id' => $data['type'] === 'RETURN' ? $data['material_import_receipt_id'] : null,
//            ]);
//
//            // [BƯỚC 8] - Create details and update quantities
//            foreach ($validatedMaterials as $material) {
//                $remainingQuantity = $material['quantity'];
//                $histories = $material['histories'];
//
//                foreach ($histories as $history) {
//                    if ($remainingQuantity <= 0) break;
//
//                    $quantityToTake = min($remainingQuantity, $history->quantity_available);
//
//                    // Cập nhật số lượng available trong history hiện tại
//                    $history->quantity_available -= $quantityToTake;
//                    $history->save();
//
//                    // Tạo chi tiết xuất kho
//                    $materialExportReceipt->details()->create([
//                        'material_id' => $material['material_id'],
//                        'storage_area_id' => $material['storage_area_id'],
//                        'quantity' => $quantityToTake,
//                        'expiry_date' => $history->expiry_date
//                    ]);
//
//                    // Create material inventory history for EACH history record
//                    $actionType = match($data['type']) {
//                        'NORMAL' => 'EXPORT_NORMAL',
//                        'CANCEL' => 'EXPORT_CANCEL',
//                        'RETURN' => 'EXPORT_RETURN'
//                    };
//
//                    MaterialStorageHistoryDetail::create([
//                        'material_storage_history_id' => $history->id,
//                        'quantity_before' => $history->quantity_available + $quantityToTake,
//                        'quantity_change' => -$quantityToTake,
//                        'quantity_after' => $history->quantity_available,
//                        'action_type' => $actionType,
//                        'created_by' => $createdById
//                    ]);
//
//                    $remainingQuantity -= $quantityToTake;
//                }
//
//                // Cập nhật số lượng trong bảng materials
//                $materialModel = Material::find($material['material_id']);
//                $materialModel->quantity_available -= $material['quantity'];
//                $materialModel->save();
//            }
//
//            // [BƯỚC 9] - Load relationships for response
//            $exportReceipt = MaterialExportReceipt::with([
//                'details.material',
//                'details.storageArea',
//                'creator.profile',
//            ])->find($materialExportReceipt->id);
//
//            // [BƯỚC 10] - Prepare response
//            $response = [
//                'success' => true,
//                'message' => 'Xuất kho thành công',
//                'data' => [
//                    'id' => $exportReceipt->id,
//                    'code' => $exportReceipt->code,
//                    'type' => $exportReceipt->type,
//                    'status' => $exportReceipt->status,
//                    'note' => $exportReceipt->note,
//                    'created_at' => $exportReceipt->created_at,
//                    'creator' => [
//                        'id' => $exportReceipt->creator->id,
//                        'email' => $exportReceipt->creator->email,
//                        'profile' => [
//                            'id' => $exportReceipt->creator->profile->id,
//                            'first_name' => $exportReceipt->creator->profile->first_name,
//                            'last_name' => $exportReceipt->creator->profile->last_name,
//                        ]
//                    ],
//                ]
//            ];
//
//            // Thêm thông tin phiếu nhập nếu là RETURN
//            if ($data['type'] === 'RETURN' && $importReceipt) {
//                $response['data']['import_receipt'] = [
//                    'id' => $importReceipt->id,
//                    'code' => $importReceipt->code,
//                    'created_at' => $importReceipt->created_at,
//                    'provider' => [
//                        'id' => $importReceipt->provider_id,
//                        'name' => $importReceipt->provider->name,
//                        'code' => $importReceipt->provider->code
//                    ]
//                ];
//            }
//
//            // Thêm chi tiết xuất kho và thông tin history
//            $response['data']['details'] = $exportReceipt->details->map(function ($detail) {
//                // Lấy history mới nhất
//                $latestHistory = MaterialStorageHistory::where([
//                    'material_id' => $detail->material_id,
//                    'storage_area_id' => $detail->storage_area_id,
//                    'status' => 'ACTIVE',
//                    'deleted' => false
//                ])->first();
//
//                return [
//                    'id' => $detail->id,
//                    'material' => [
//                        'id' => $detail->material->id,
//                        'sku' => $detail->material->sku,
//                        'name' => $detail->material->name,
//                    ],
//                    'storage_area' => [
//                        'id' => $detail->storageArea->id,
//                        'name' => $detail->storageArea->name,
//                        'code' => $detail->storageArea->code,
//                    ],
//                    'quantity' => $detail->quantity,
//                    'expiry_date' => $detail->expiry_date,
//                    'created_at' => $detail->created_at,
//                    'history' => [
//                        'quantity_available' => $latestHistory ? $latestHistory->quantity_available : 0,
//                        'status' => $latestHistory ? $latestHistory->status : null
//                    ]
//                ];
//            });
//
//            header('Content-Type: application/json');
//            echo json_encode($response, JSON_UNESCAPED_UNICODE);
//
//        } catch (\Exception $e) {
//            header('Content-Type: application/json');
//            http_response_code(400);
//            echo json_encode([
//                'success' => false,
//                'message' => $e->getMessage()
//            ], JSON_UNESCAPED_UNICODE);
//        }
//    }

    public function exportMaterials(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            // [BƯỚC 1] - Validate basic required fields
            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'CANCEL', 'RETURN'])) {
                throw new \Exception('Type phải là NORMAL, CANCEL hoặc RETURN');
            }

            // [BƯỚC 2] - Validate allowed fields
            $allowedFields = [
                'NORMAL' => ['type', 'note', 'materials'],
                'CANCEL' => ['type', 'note', 'materials'],
                'RETURN' => ['type', 'note', 'materials', 'material_import_receipt_id']
            ];

            foreach ($data as $field => $value) {
                if (!in_array($field, $allowedFields[$data['type']])) {
                    throw new \Exception("Trường '$field' không được phép với type " . $data['type']);
                }
            }

            // [BƯỚC 3] - Token validation
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            if (!$token) {
                throw new \Exception('Token không tồn tại');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $createdById = $parsedToken->claims()->get('id');

            // [BƯỚC 4] - Type specific validation
            $importReceipt = null;
            if ($data['type'] === 'RETURN') {
                if (!isset($data['material_import_receipt_id'])) {
                    throw new \Exception('material_import_receipt_id là bắt buộc với type RETURN');
                }

                $importReceipt = MaterialImportReceipt::where('id', $data['material_import_receipt_id'])
                    ->where('deleted', false)
                    ->first();

                if (!$importReceipt) {
                    throw new \Exception('Phiếu nhập không tồn tại hoặc đã bị xóa');
                }
            }

            // [BƯỚC 5] - Validate materials
            if (!isset($data['materials']) || empty($data['materials'])) {
                throw new \Exception('Danh sách materials không được để trống');
            }

            $validatedMaterials = [];
            foreach ($data['materials'] as $material) {
                if (!isset($material['material_history_id']) || !isset($material['quantity'])) {
                    throw new \Exception('material_history_id, quantity là bắt buộc cho mỗi nguyên liệu');
                }

                // Kiểm tra số lượng xuất
                if ($material['quantity'] <= 0) {
                    throw new \Exception("Số lượng xuất phải lớn hơn 0");
                }

                // Lấy history record và material liên quan
                $history = MaterialStorageHistory::with('material')
                    ->where('id', $material['material_history_id'])
                    ->where('status', 'ACTIVE')
                    ->where('deleted', false)
                    ->where('quantity_available', '>', 0)
                    ->first();

                if (!$history) {
                    throw new \Exception("Không tìm thấy history ID {$material['material_history_id']} hoặc đã hết hàng");
                }

                if ($history->quantity_available < $material['quantity']) {
                    throw new \Exception(
                        "Không đủ số lượng trong kho cho history ID {$material['material_history_id']}. " .
                        "Cần: {$material['quantity']}, Có sẵn: {$history->quantity_available}"
                    );
                }

                // Kiểm tra thêm cho type RETURN
                if ($data['type'] === 'RETURN') {
                    $importDetail = MaterialImportReceiptDetail::where('material_import_receipt_id', $data['material_import_receipt_id'])
                        ->where('material_id', $history->material_id)
                        ->where('deleted', false)
                        ->first();

                    if (!$importDetail) {
                        throw new \Exception("Nguyên liệu không có trong phiếu nhập");
                    }

                    if ($material['quantity'] > $importDetail->quantity) {
                        throw new \Exception(
                            "Số lượng trả về ({$material['quantity']}) vượt quá số lượng trong phiếu nhập ({$importDetail->quantity})"
                        );
                    }
                }

                $validatedMaterials[] = [
                    'history' => $history,
                    'quantity' => $material['quantity']
                ];
            }

            // [BƯỚC 6] - Generate receipt code
            $currentDay = date('d');
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "PXNVL" . $currentDay . $currentMonth . $currentYear;

            $latestExportReceipt = MaterialExportReceipt::where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            $sequence = $latestExportReceipt ? intval(substr($latestExportReceipt->code, -5)) + 1 : 1;
            $code = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);

            // [BƯỚC 7] - Create export receipt
            $materialExportReceipt = MaterialExportReceipt::create([
                'code' => $code,
                'note' => $data['note'] ?? '',
                'type' => $data['type'],
                'status' => 'COMPLETED',
                'created_by' => $createdById,
                'material_import_receipt_id' => $data['type'] === 'RETURN' ? $data['material_import_receipt_id'] : null,
            ]);

            // [BƯỚC 8] - Create details and update quantities
            foreach ($validatedMaterials as $material) {
                $history = $material['history'];
                $quantity = $material['quantity'];

                // Cập nhật số lượng available trong history
                $history->quantity_available -= $quantity;
                $history->save();

                // Tạo chi tiết xuất kho
                $materialExportReceipt->details()->create([
                    'material_id' => $history->material_id,
                    'storage_area_id' => $history->storage_area_id,
                    'quantity' => $quantity,
                    'expiry_date' => $history->expiry_date
                ]);

                // Tạo material inventory history
                $actionType = match($data['type']) {
                    'NORMAL' => 'EXPORT_NORMAL',
                    'CANCEL' => 'EXPORT_CANCEL',
                    'RETURN' => 'EXPORT_RETURN'
                };

                MaterialStorageHistoryDetail::create([
                    'material_storage_history_id' => $history->id,
                    'quantity_before' => $history->quantity_available + $quantity,
                    'quantity_change' => -$quantity,
                    'quantity_after' => $history->quantity_available,
                    'action_type' => $actionType,
                    'created_by' => $createdById
                ]);

                // Cập nhật số lượng trong bảng materials
                $history->material->quantity_available -= $quantity;
                $history->material->save();
            }

            // [BƯỚC 9] - Load relationships for response
            $exportReceipt = MaterialExportReceipt::with([
                'details.material',
                'details.storageArea',
                'creator.profile',
            ])->find($materialExportReceipt->id);

            // [BƯỚC 10] - Prepare response
            $response = [
                'success' => true,
                'message' => 'Xuất kho thành công',
                'data' => [
                    'id' => $exportReceipt->id,
                    'code' => $exportReceipt->code,
                    'type' => $exportReceipt->type,
                    'status' => $exportReceipt->status,
                    'note' => $exportReceipt->note,
                    'created_at' => $exportReceipt->created_at,
                    'creator' => [
                        'id' => $exportReceipt->creator->id,
                        'email' => $exportReceipt->creator->email,
                        'profile' => [
                            'id' => $exportReceipt->creator->profile->id,
                            'first_name' => $exportReceipt->creator->profile->first_name,
                            'last_name' => $exportReceipt->creator->profile->last_name,
                        ]
                    ],
                ]
            ];

            // Thêm thông tin phiếu nhập nếu là RETURN
            if ($data['type'] === 'RETURN' && $importReceipt) {
                $response['data']['import_receipt'] = [
                    'id' => $importReceipt->id,
                    'code' => $importReceipt->code,
                    'created_at' => $importReceipt->created_at,
                    'provider' => [
                        'id' => $importReceipt->provider_id,
                        'name' => $importReceipt->provider->name,
                        'code' => $importReceipt->provider->code
                    ]
                ];
            }

            // Thêm chi tiết xuất kho và thông tin history
            $response['data']['details'] = $exportReceipt->details->map(function ($detail) {
                // Lấy history mới nhất
                $latestHistory = MaterialStorageHistory::where([
                    'material_id' => $detail->material_id,
                    'storage_area_id' => $detail->storage_area_id,
                    'status' => 'ACTIVE',
                    'deleted' => false
                ])->first();

                return [
                    'id' => $detail->id,
                    'material' => [
                        'id' => $detail->material->id,
                        'sku' => $detail->material->sku,
                        'name' => $detail->material->name,
                    ],
                    'storage_area' => [
                        'id' => $detail->storageArea->id,
                        'name' => $detail->storageArea->name,
                        'code' => $detail->storageArea->code,
                    ],
                    'quantity' => $detail->quantity,
                    'expiry_date' => $detail->expiry_date,
                    'created_at' => $detail->created_at,
                    'history' => [
                        'quantity_available' => $latestHistory ? $latestHistory->quantity_available : 0,
                        'status' => $latestHistory ? $latestHistory->status : null
                    ]
                ];
            });

            header('Content-Type: application/json');
            echo json_encode($response, JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}