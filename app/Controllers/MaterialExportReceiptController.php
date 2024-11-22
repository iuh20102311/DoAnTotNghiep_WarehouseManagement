<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialExportReceipt;
use App\Models\MaterialImportReceipt;
use App\Models\MaterialImportReceiptDetail;
use App\Models\MaterialStorageHistory;
use App\Models\Provider;
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
                'NORMAL' => ['type', 'note', 'materials', 'receiver_id'],
                'CANCEL' => ['type', 'note', 'materials', 'receiver_id'],
                'RETURN' => ['type', 'note', 'materials', 'receiver_id', 'material_import_receipt_id']
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

            // Kiểm tra và validate materials
            $materials = $data['materials'] ?? [];
            if (empty($materials)) {
                throw new \Exception('Danh sách materials không được để trống');
            }

            // Kiểm tra tất cả nguyên vật liệu trước khi tạo hóa đơn
            $missingMaterials = [];
            foreach ($materials as $material) {
                if (!isset($material['material_id']) || !isset($material['quantity']) || !isset($material['storage_area_id'])) {
                    throw new \Exception('material_id, quantity và storage_area_id là bắt buộc cho mỗi nguyên liệu');
                }

                $materialModel = Material::find($material['material_id']);
                if (!$materialModel) {
                    $missingMaterials[] = "Nguyên vật liệu (ID: {$material['material_id']}) không tồn tại";
                    continue;
                }

                // Kiểm tra vị trí lưu trữ và số lượng
                $storageArea = StorageArea::where('id', $material['storage_area_id'])
                    ->where('deleted', false)
                    ->first();

                if (!$storageArea) {
                    $missingMaterials[] = "Vị trí lưu trữ (ID: {$material['storage_area_id']}) không tồn tại hoặc không hoạt động";
                    continue;
                }

                // Lấy tổng số lượng có sẵn trong kho
                $totalAvailable = MaterialStorageHistory::where('material_id', $material['material_id'])
                    ->where('storage_area_id', $material['storage_area_id'])
                    ->where('deleted', false)
                    ->sum('quantity');

                if ($totalAvailable < $material['quantity']) {
                    $missingMaterials[] = "{$materialModel->name} không đủ số lượng trong kho. Có sẵn: {$totalAvailable}, Yêu cầu: {$material['quantity']}";
                    continue;
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
            $currentDay = date('d');
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "PXNVL" . $currentDay . $currentMonth . $currentYear;

            // Lấy phiếu xuất mới nhất với prefix hiện tại
            $latestExportReceipt = MaterialExportReceipt::query()
                ->where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            if ($latestExportReceipt) {
                $sequence = intval(substr($latestExportReceipt->code, -5)) + 1;
            } else {
                $sequence = 1;
            }

            // Định dạng số thứ tự thành 5 chữ số
            $code = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);

            // Tạo mới MaterialExportReceipt
            $materialExportReceipt = MaterialExportReceipt::create([
                'code' => $code,
                'note' => $data['note'] ?? null,
                'type' => $data['type'],
                'material_import_receipt_id' => $data['material_import_receipt_id'] ?? null,
                'status' => $data['type'] === 'NORMAL' ? 'TEMPORARY' : 'COMPLETED',
                'created_by' => $createdById
            ]);

            // Tạo chi tiết xuất kho và cập nhật số lượng
            foreach ($materials as $material) {
                $remainingQuantity = $material['quantity'];

                // Lấy các lô trong kho, sắp xếp theo hạn sử dụng gần nhất
                $storageBatches = MaterialStorageHistory::where('material_id', $material['material_id'])
                    ->where('storage_area_id', $material['storage_area_id'])
                    ->where('deleted', false)
                    ->where('quantity', '>', 0)
                    ->orderBy('expiry_date', 'asc')
                    ->get();

                foreach ($storageBatches as $batch) {
                    if ($remainingQuantity <= 0) break;

                    $quantityFromBatch = min($remainingQuantity, $batch->quantity);

                    // Tạo chi tiết xuất kho cho từng lô
                    $materialExportReceipt->details()->create([
                        'material_id' => $material['material_id'],
                        'storage_area_id' => $material['storage_area_id'],
                        'quantity' => $quantityFromBatch,
                        'expiry_date' => $batch->expiry_date
                    ]);

                    // Cập nhật số lượng trong batch
                    $batch->quantity -= $quantityFromBatch;
                    $batch->save();

                    $remainingQuantity -= $quantityFromBatch;
                }

                // Cập nhật tổng số lượng trong bảng materials
                $materialModel = Material::find($material['material_id']);
                $materialModel->quantity_available -= $material['quantity'];
                $materialModel->save();
            }

            // Tạo mảng relationships cần load
            $relationships = [
                'details.material',
                'details.storageArea',
                'creator.profile'
            ];

            // Load phiếu xuất với các relationships phù hợp
            $exportReceipt = MaterialExportReceipt::with($relationships)->find($materialExportReceipt->id);

            // Nếu là RETURN thì load thông tin phiếu nhập
            $importReceipt = null;
            if ($data['type'] === 'RETURN' && isset($data['material_import_receipt_id'])) {
                $importReceipt = MaterialImportReceipt::find($data['material_import_receipt_id']);
            }

            $response = [
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
                    'import_receipt' => $data['type'] === 'RETURN' && $importReceipt ? [
                        'id' => $importReceipt->id,
                        'code' => $importReceipt->code,
                        'note' => $importReceipt->note,
                        'created_at' => $importReceipt->created_at,
                        'provider' => [
                            'id' => $importReceipt->provider_id,
                            'name' => Provider::find($importReceipt->provider_id)->name,
                            'code' => Provider::find($importReceipt->provider_id)->code,
                        ]
                    ] : null,
                    'details' => $exportReceipt->details->map(function ($detail) {
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
                            'created_at' => $detail->created_at
                        ];
                    })
                ]
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