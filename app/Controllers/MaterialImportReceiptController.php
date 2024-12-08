<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialExportReceipt;
use App\Models\MaterialImportReceipt;
use App\Models\MaterialStorageHistoryDetail;
use App\Models\MaterialStorageHistory;
use App\Models\Provider;
use App\Models\StorageArea;
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
                ->orderBy('created_at', 'desc');

            // Code filter
            if (isset($_GET['code'])) {
                $code = urldecode($_GET['code']);
                $materialIR->where('code', 'LIKE', '%' . $code . '%');
            }

            // Provider filter
            if (isset($_GET['provider_id'])) {
                $providerId = urldecode($_GET['provider_id']);
                $materialIR->where('provider_id', $providerId);
            }

            // Creator filter
            if (isset($_GET['created_by'])) {
                $createdBy = urldecode($_GET['created_by']);
                $materialIR->where('created_by', $createdBy);
            }

            // Approver filter
            if (isset($_GET['approved_by'])) {
                $approvedBy = urldecode($_GET['approved_by']);
                $materialIR->where('approved_by', $approvedBy);
            }

            // Receiver filter
            if (isset($_GET['receiver_id'])) {
                $receiverId = urldecode($_GET['receiver_id']);
                $materialIR->where('receiver_id', $receiverId);
            }

            // Receipt Date filters
            if (isset($_GET['receipt_date'])) {
                $receiptDate = urldecode($_GET['receipt_date']);
                $materialIR->whereDate('receipt_date', $receiptDate);
            }
            if (isset($_GET['receipt_date_from'])) {
                $receiptDateFrom = urldecode($_GET['receipt_date_from']);
                $materialIR->whereDate('receipt_date', '>=', $receiptDateFrom);
            }
            if (isset($_GET['receipt_date_to'])) {
                $receiptDateTo = urldecode($_GET['receipt_date_to']);
                $materialIR->whereDate('receipt_date', '<=', $receiptDateTo);
            }

            // Type filter
            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                $materialIR->where('type', $type);
            }

            // Status filter
            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $materialIR->where('status', $status);
            }

            // Total Price filters
            if (isset($_GET['total_price'])) {
                $totalPrice = urldecode($_GET['total_price']);
                $materialIR->where('total_price', $totalPrice);
            }
            if (isset($_GET['total_price_min'])) {
                $totalPriceMin = urldecode($_GET['total_price_min']);
                $materialIR->where('total_price', '>=', $totalPriceMin);
            }
            if (isset($_GET['total_price_max'])) {
                $totalPriceMax = urldecode($_GET['total_price_max']);
                $materialIR->where('total_price', '<=', $totalPriceMax);
            }

            // Note filter
            if (isset($_GET['note'])) {
                $note = urldecode($_GET['note']);
                $materialIR->where('note', 'LIKE', '%' . $note . '%');
            }

            // Created At filters
            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $materialIR->whereDate('created_at', '>=', $createdFrom);
            }
            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $materialIR->whereDate('created_at', '<=', $createdTo);
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
                    'details.storageArea',
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

//    public function importMaterials(): void
//    {
//        $data = json_decode(file_get_contents('php://input'), true);
//        $exportReceipt = null;
//
//        try {
//            // [BƯỚC 1] - Validate basic required fields
//            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'RETURN'])) {
//                throw new \Exception('Type phải là NORMAL hoặc RETURN');
//            }
//
//            if (!isset($data['receiver_id'])) {
//                throw new \Exception('receiver_id là bắt buộc');
//            }
//
//            // [BƯỚC 2] - Validate allowed fields
//            $allowedFields = [
//                'NORMAL' => ['type', 'provider_id', 'receiver_id', 'note', 'materials'],
//                'RETURN' => ['type', 'material_export_receipt_id', 'receiver_id', 'note', 'materials']
//            ];
//
//            foreach ($data as $field => $value) {
//                if (!in_array($field, $allowedFields[$data['type']])) {
//                    throw new \Exception("Trường '$field' không được phép với type " . $data['type']);
//                }
//            }
//
//            // [BƯỚC 3] - Validate JWT token
//            $headers = apache_request_headers();
//            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
//
//            if (!$token) {
//                throw new \Exception('Token không tồn tại');
//            }
//
//            $parser = new Parser(new JoseEncoder());
//            $parsedToken = $parser->parse($token);
//            $createdById = $parsedToken->claims()->get('id');
//
//            // [BƯỚC 4] - Validate receiver
//            $receiver = User::where('id', $data['receiver_id'])
//                ->where('status', 'ACTIVE')
//                ->where('deleted', false)
//                ->first();
//
//            if (!$receiver) {
//                throw new \Exception('Người nhận không tồn tại hoặc không hoạt động');
//            }
//
//            // [BƯỚC 5] - Validate based on type
//            if ($data['type'] === 'NORMAL') {
//                if (!isset($data['provider_id'])) {
//                    throw new \Exception('provider_id là bắt buộc với type NORMAL');
//                }
//
//                $provider = Provider::where('id', $data['provider_id'])
//                    ->where('deleted', false)
//                    ->first();
//
//                if (!$provider) {
//                    throw new \Exception('Nhà cung cấp không tồn tại hoặc không hoạt động');
//                }
//            } else { // RETURN type
//                if (!isset($data['material_export_receipt_id'])) {
//                    throw new \Exception('material_export_receipt_id là bắt buộc với type RETURN');
//                }
//
//                $exportReceipt = MaterialExportReceipt::with(['details.material', 'details.storageArea'])
//                    ->where('id', $data['material_export_receipt_id'])
//                    ->where('type', 'NORMAL')
//                    ->where('status', 'COMPLETED')
//                    ->where('deleted', false)
//                    ->first();
//
//                if (!$exportReceipt) {
//                    throw new \Exception('Phiếu xuất không tồn tại hoặc không hợp lệ');
//                }
//            }
//
//            // [BƯỚC 6] - Validate materials array
//            if (!isset($data['materials']) || empty($data['materials'])) {
//                throw new \Exception('Danh sách materials không được để trống');
//            }
//
//            // [BƯỚC 7] - Validate materials và chuẩn bị dữ liệu
//            $validatedMaterials = [];
//            foreach ($data['materials'] as $material) {
//                $storageArea = StorageArea::where('id', $material['storage_area_id'])
//                    ->where('deleted', false)
//                    ->first();
//
//                if (!$storageArea) {
//                    throw new \Exception('Khu vực lưu trữ không tồn tại hoặc không hoạt động');
//                }
//
//                if ($storageArea->type !== 'MATERIAL') {
//                    throw new \Exception("Kho chứa {$storageArea->name} không phải là kho nguyên vật liệu");
//                }
//
//                if (!isset($material['material_id']) || !isset($material['quantity']) ||
//                    !isset($material['storage_area_id'])) {
//                    throw new \Exception('material_id, quantity và storage_area_id là bắt buộc cho mỗi nguyên liệu');
//                }
//
//                if ($material['quantity'] <= 0) {
//                    throw new \Exception('Số lượng phải lớn hơn 0');
//                }
//
//                $materialModel = Material::find($material['material_id']);
//                if (!$materialModel) {
//                    throw new \Exception("Nguyên liệu (ID: {$material['material_id']}) không tồn tại");
//                }
//
//                $storageArea = StorageArea::where('id', $material['storage_area_id'])
//                    ->where('deleted', false)
//                    ->first();
//
//                if (!$storageArea) {
//                    throw new \Exception('Khu vực lưu trữ không tồn tại hoặc không hoạt động');
//                }
//
//                if ($data['type'] === 'NORMAL') {
//                    if (!isset($material['price'])) {
//                        throw new \Exception('price là bắt buộc với type NORMAL');
//                    }
//
//                    if (!isset($material['expiry_date'])) {
//                        throw new \Exception('expiry_date là bắt buộc với type NORMAL');
//                    }
//
//                    if (!strtotime($material['expiry_date']) || strtotime($material['expiry_date']) <= time()) {
//                        throw new \Exception('expiry_date phải là ngày trong tương lai và đúng định dạng');
//                    }
//                } else { // RETURN type
//                    $exportDetail = $exportReceipt->details
//                        ->where('material_id', $material['material_id'])
//                        ->first();
//
//                    if (!$exportDetail) {
//                        throw new \Exception("Nguyên liệu {$materialModel->name} không có trong phiếu xuất");
//                    }
//
//                    if ($material['quantity'] > $exportDetail->quantity) {
//                        throw new \Exception(
//                            "Số lượng trả về của {$materialModel->name} ({$material['quantity']}) " .
//                            "không được lớn hơn số lượng đã xuất ({$exportDetail->quantity})"
//                        );
//                    }
//
//                    $material['expiry_date'] = $exportDetail->expiry_date;
//                    $material['price'] = 0; // Giá = 0 với type RETURN
//                }
//
//                $validatedMaterials[] = $material;
//            }
//
//            // [BƯỚC 8] - Generate receipt code
//            $currentDay = date('d');
//            $currentMonth = date('m');
//            $currentYear = date('y');
//            $prefix = "PNNVL" . $currentDay . $currentMonth . $currentYear;
//
//            $latestImportReceipt = MaterialImportReceipt::where('code', 'LIKE', $prefix . '%')
//                ->orderBy('code', 'desc')
//                ->first();
//
//            $sequence = $latestImportReceipt ? intval(substr($latestImportReceipt->code, -5)) + 1 : 1;
//            $code = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
//
//            // [BƯỚC 9] - Create import receipt
//            $importReceipt = MaterialImportReceipt::create([
//                'code' => $code,
//                'type' => $data['type'],
//                'provider_id' => $data['type'] === 'NORMAL' ? $data['provider_id'] : null,
//                'material_export_receipt_id' => $data['type'] === 'RETURN' ? $data['material_export_receipt_id'] : null,
//                'note' => $data['note'] ?? null,
//                'status' => $data['type'] === 'NORMAL' ? 'PENDING_APPROVED' : 'COMPLETED',
//                'created_by' => $createdById,
//                'receiver_id' => $data['receiver_id']
//            ]);
//
//            // [BƯỚC 10] - Create import details and update inventory
//            $totalPrice = 0;
//            $importDetails = []; // Mảng lưu chi tiết import và history tương ứng
//
//            foreach ($validatedMaterials as $material) {
//                $price = $material['price'] ?? 0;
//                $totalPrice += $price * $material['quantity'];
//
//                // Create import detail
//                $detail = $importReceipt->details()->create([
//                    'material_id' => $material['material_id'],
//                    'storage_area_id' => $material['storage_area_id'],
//                    'quantity' => $material['quantity'],
//                    'price' => $price,
//                    'expiry_date' => $material['expiry_date']
//                ]);
//
//                // Kiểm tra history cũ
//                $previousActiveRecord = MaterialStorageHistory::where([
//                    'material_id' => $material['material_id'],
//                    'storage_area_id' => $material['storage_area_id'],
//                    'expiry_date' => $material['expiry_date'],
//                    'status' => 'ACTIVE',
//                    'deleted' => false
//                ])->first();
//
//                // Tính toán quantity_available mới
//                $newQuantityAvailable = $material['quantity'];
//                if ($previousActiveRecord) {
//                    $newQuantityAvailable += $previousActiveRecord->quantity_available;
//                    // Set previous record to INACTIVE
//                    $previousActiveRecord->status = 'INACTIVE';
//                    $previousActiveRecord->save();
//                }
//
//                // Create new storage history record
//                $historyRecord = new MaterialStorageHistory();
//                $historyRecord->material_id = $material['material_id'];
//                $historyRecord->storage_area_id = $material['storage_area_id'];
//                $historyRecord->expiry_date = $material['expiry_date'];
//                $historyRecord->quantity = $material['quantity'];
//                $historyRecord->quantity_available = $newQuantityAvailable;
//                $historyRecord->provider_id = $data['type'] === 'NORMAL' ? $data['provider_id'] : 1;
//                $historyRecord->status = 'ACTIVE';
//                $historyRecord->deleted = false;
//                $historyRecord->save();
//
//                // Lưu detail và history vào mảng
//                $importDetails[] = [
//                    'detail' => $detail,
//                    'history' => $historyRecord
//                ];
//
//                // Update material quantity in materials
//                $materialModel = Material::find($material['material_id']);
//                $oldQuantity = $materialModel->quantity_available; // Lưu số lượng cũ
//                $materialModel->quantity_available += $material['quantity'];
//                $materialModel->save();
//
//                // Tạo material inventory history
//                $actionType = match($data['type']) {
//                    'NORMAL' => 'IMPORT_NORMAL',
//                    'RETURN' => 'IMPORT_RETURN'
//                };
//
//                MaterialStorageHistoryDetail::create([
//                    'storage_area_id' => $material['storage_area_id'],
//                    'material_id' => $material['material_id'],
//                    'quantity_before' => $oldQuantity,
//                    'quantity_change' => $material['quantity'], // Số dương vì là nhập kho
//                    'quantity_after' => $materialModel->quantity_available,
//                    'remaining_quantity' => $materialModel->quantity_available,
//                    'action_type' => $actionType,
//                    'created_by' => $createdById
//                ]);
//            }
//
//            // Update total price for NORMAL type
//            if ($data['type'] === 'NORMAL') {
//                $importReceipt->total_price = $totalPrice;
//                $importReceipt->save();
//            }
//
//            // [BƯỚC 11] - Load relationships for response
//            $importReceipt->load([
//                'details.material',
//                'details.storageArea',
//                'creator.profile',
//                'receiver.profile',
//                'provider'
//            ]);
//
//            // [BƯỚC 12] - Prepare and send response
//            $response = [
//                'message' => 'Nhập kho thành công',
//                'data' => [
//                    'id' => $importReceipt->id,
//                    'code' => $importReceipt->code,
//                    'type' => $importReceipt->type,
//                    'status' => $importReceipt->status,
//                    'note' => $importReceipt->note,
//                    'total_price' => $importReceipt->total_price,
//                    'created_at' => $importReceipt->created_at,
//                    'creator' => [
//                        'id' => $importReceipt->creator->id,
//                        'email' => $importReceipt->creator->email,
//                        'profile' => [
//                            'id' => $importReceipt->creator->profile->id,
//                            'first_name' => $importReceipt->creator->profile->first_name,
//                            'last_name' => $importReceipt->creator->profile->last_name,
//                        ]
//                    ],
//                    'receiver' => [
//                        'id' => $importReceipt->receiver->id,
//                        'email' => $importReceipt->receiver->email,
//                        'profile' => [
//                            'id' => $importReceipt->receiver->profile->id,
//                            'first_name' => $importReceipt->receiver->profile->first_name,
//                            'last_name' => $importReceipt->receiver->profile->last_name,
//                        ]
//                    ],
//                    'provider' => $importReceipt->provider ? [
//                        'id' => $importReceipt->provider->id,
//                        'name' => $importReceipt->provider->name,
//                        'code' => $importReceipt->provider->code,
//                    ] : null,
//                    'details' => array_map(function ($item) use ($data, $exportReceipt) {
//                        $detail = $item['detail'];
//                        $history = $item['history'];
//
//                        $result = [
//                            'id' => $detail->id,
//                            'material' => [
//                                'id' => $detail->material->id,
//                                'sku' => $detail->material->sku,
//                                'name' => $detail->material->name,
//                            ],
//                            'storage_area' => [
//                                'id' => $detail->storageArea->id,
//                                'name' => $detail->storageArea->name,
//                                'code' => $detail->storageArea->code,
//                            ],
//                            'quantity' => $detail->quantity,
//                            'price' => $detail->price,
//                            'expiry_date' => $detail->expiry_date,
//                            'created_at' => $detail->created_at,
//                            'history' => [
//                                'quantity_available' => $history->quantity_available,
//                                'status' => $history->status
//                            ]
//                        ];
//
//                        if ($data['type'] === 'RETURN' && isset($exportReceipt)) {
//                            $exportDetail = $exportReceipt->details
//                                ->where('material_id', $detail->material_id)
//                                ->first();
//
//                            if ($exportDetail) {
//                                $result['export_receipt'] = [
//                                    'id' => $exportReceipt->id,
//                                    'code' => $exportReceipt->code,
//                                    'type' => $exportReceipt->type,
//                                    'export_detail' => [
//                                        'id' => $exportDetail->id,
//                                        'quantity' => $exportDetail->quantity,
//                                        'expiry_date' => $exportDetail->expiry_date
//                                    ]
//                                ];
//                            }
//                        }
//
//                        return $result;
//                    }, $importDetails)
//                ]
//            ];
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

    public function importMaterials(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $exportReceipt = null;

        try {
            // [BƯỚC 1] - Validate basic required fields
            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'RETURN'])) {
                throw new \Exception('Type phải là NORMAL hoặc RETURN');
            }

            if (!isset($data['receiver_id'])) {
                throw new \Exception('receiver_id là bắt buộc');
            }

            // [BƯỚC 2] - Validate allowed fields
            $allowedFields = [
                'NORMAL' => ['type', 'provider_id', 'receiver_id', 'note', 'materials'],
                'RETURN' => ['type', 'material_export_receipt_id', 'receiver_id', 'note', 'materials']
            ];

            foreach ($data as $field => $value) {
                if (!in_array($field, $allowedFields[$data['type']])) {
                    throw new \Exception("Trường '$field' không được phép với type " . $data['type']);
                }
            }

            // [BƯỚC 3] - Validate JWT token
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                throw new \Exception('Token không tồn tại');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $createdById = $parsedToken->claims()->get('id');

            // [BƯỚC 4] - Validate receiver
            $receiver = User::where('id', $data['receiver_id'])
                ->where('status', 'ACTIVE')
                ->where('deleted', false)
                ->first();

            if (!$receiver) {
                throw new \Exception('Người nhận không tồn tại hoặc không hoạt động');
            }

            // [BƯỚC 5] - Validate based on type
            if ($data['type'] === 'NORMAL') {
                if (!isset($data['provider_id'])) {
                    throw new \Exception('provider_id là bắt buộc với type NORMAL');
                }

                $provider = Provider::where('id', $data['provider_id'])
                    ->where('deleted', false)
                    ->first();

                if (!$provider) {
                    throw new \Exception('Nhà cung cấp không tồn tại hoặc không hoạt động');
                }
            } else { // RETURN type
                if (!isset($data['material_export_receipt_id'])) {
                    throw new \Exception('material_export_receipt_id là bắt buộc với type RETURN');
                }

                $exportReceipt = MaterialExportReceipt::with(['details.material', 'details.storageArea'])
                    ->where('id', $data['material_export_receipt_id'])
                    ->where('type', 'NORMAL')
                    ->where('status', 'COMPLETED')
                    ->where('deleted', false)
                    ->first();

                if (!$exportReceipt) {
                    throw new \Exception('Phiếu xuất không tồn tại hoặc không hợp lệ');
                }
            }

            // [BƯỚC 6] - Validate materials array
            if (!isset($data['materials']) || empty($data['materials'])) {
                throw new \Exception('Danh sách materials không được để trống');
            }

            // [BƯỚC 7] - Validate materials
            foreach ($data['materials'] as $material) {
                $storageArea = StorageArea::where('id', $material['storage_area_id'])
                    ->where('deleted', false)
                    ->first();

                if (!$storageArea) {
                    throw new \Exception('Khu vực lưu trữ không tồn tại hoặc không hoạt động');
                }

                if ($storageArea->type !== 'MATERIAL') {
                    throw new \Exception("Kho chứa {$storageArea->name} không phải là kho nguyên vật liệu");
                }

                if (!isset($material['material_id']) || !isset($material['quantity']) ||
                    !isset($material['storage_area_id'])) {
                    throw new \Exception('material_id, quantity và storage_area_id là bắt buộc cho mỗi nguyên liệu');
                }

                if ($material['quantity'] <= 0) {
                    throw new \Exception('Số lượng phải lớn hơn 0');
                }

                $materialModel = Material::find($material['material_id']);
                if (!$materialModel) {
                    throw new \Exception("Nguyên liệu (ID: {$material['material_id']}) không tồn tại");
                }

                if ($data['type'] === 'NORMAL') {
                    if (!isset($material['price'])) {
                        throw new \Exception('price là bắt buộc với type NORMAL');
                    }

                    if (!isset($material['expiry_date'])) {
                        throw new \Exception('expiry_date là bắt buộc với type NORMAL');
                    }

                    if (!strtotime($material['expiry_date']) || strtotime($material['expiry_date']) <= time()) {
                        throw new \Exception('expiry_date phải là ngày trong tương lai và đúng định dạng');
                    }
                } else { // RETURN type
                    $exportDetail = $exportReceipt->details
                        ->where('material_id', $material['material_id'])
                        ->first();

                    if (!$exportDetail) {
                        throw new \Exception("Nguyên liệu {$materialModel->name} không có trong phiếu xuất");
                    }

                    if ($material['quantity'] > $exportDetail->quantity) {
                        throw new \Exception(
                            "Số lượng trả về của {$materialModel->name} ({$material['quantity']}) " .
                            "không được lớn hơn số lượng đã xuất ({$exportDetail->quantity})"
                        );
                    }
                }
            }

            // [BƯỚC 8] - Generate receipt code
            $currentDay = date('d');
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "PNNVL" . $currentDay . $currentMonth . $currentYear;

            $latestImportReceipt = MaterialImportReceipt::where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            $sequence = $latestImportReceipt ? intval(substr($latestImportReceipt->code, -5)) + 1 : 1;
            $code = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);

            // [BƯỚC 9] - Create import receipt with temporary status
            $importReceipt = MaterialImportReceipt::create([
                'code' => $code,
                'type' => $data['type'],
                'provider_id' => $data['type'] === 'NORMAL' ? $data['provider_id'] : null,
                'material_export_receipt_id' => $data['type'] === 'RETURN' ? $data['material_export_receipt_id'] : null,
                'note' => $data['note'] ?? null,
                'status' => 'TEMPORARY',
                'created_by' => $createdById,
                'receiver_id' => $data['receiver_id']
            ]);

            // [BƯỚC 10] - Create import details without updating inventory
            $totalPrice = 0;
            foreach ($data['materials'] as $material) {
                $price = $material['price'] ?? 0;
                $totalPrice += $price * $material['quantity'];

                // Set expiry_date for RETURN type
                if ($data['type'] === 'RETURN') {
                    $exportDetail = $exportReceipt->details
                        ->where('material_id', $material['material_id'])
                        ->first();
                    $material['expiry_date'] = $exportDetail->expiry_date;
                }

                // Create import detail
                $importReceipt->details()->create([
                    'material_id' => $material['material_id'],
                    'storage_area_id' => $material['storage_area_id'],
                    'quantity' => $material['quantity'],
                    'price' => $price,
                    'expiry_date' => $material['expiry_date']
                ]);
            }

            // Update total price for NORMAL type
            if ($data['type'] === 'NORMAL') {
                $importReceipt->total_price = $totalPrice;
                $importReceipt->save();
            }

            // [BƯỚC 11] - Prepare response
            $response = [
                'success' => true,
                'message' => 'Tạo phiếu nhập kho tạm thời thành công',
                'data' => [
                    'id' => $importReceipt->id,
                    'code' => $importReceipt->code,
                    'type' => $importReceipt->type,
                    'status' => $importReceipt->status,
                    'note' => $importReceipt->note,
                    'total_price' => $importReceipt->total_price,
                    'created_at' => $importReceipt->created_at
                ]
            ];

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

    public function approveImportReceipt(string $importReceiptId): void
    {
        try {
            // [BƯỚC 1] - Token validation
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            if (!$token) {
                throw new \Exception('Token không tồn tại');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $approvedById = $parsedToken->claims()->get('id');

            // [BƯỚC 2] - Get and validate receipt
            $importReceipt = MaterialImportReceipt::with(['details'])->where('id', $importReceiptId)
                ->where('status', 'TEMPORARY')
                ->where('deleted', false)
                ->first();

            if (!$importReceipt) {
                throw new \Exception('Phiếu nhập không tồn tại hoặc không ở trạng thái tạm thời');
            }

            // [BƯỚC 3] - Process each material
            foreach ($importReceipt->details as $detail) {
                // Kiểm tra history cũ
                $previousActiveRecord = MaterialStorageHistory::where([
                    'material_id' => $detail->material_id,
                    'storage_area_id' => $detail->storage_area_id,
                    'expiry_date' => $detail->expiry_date,
                    'status' => 'ACTIVE',
                    'deleted' => false
                ])->first();

                // Tính toán quantity_available mới
                $newQuantityAvailable = $detail->quantity;
                if ($previousActiveRecord) {
                    $newQuantityAvailable += $previousActiveRecord->quantity_available;
                    // Update quantity_available of the existing record
                    $previousActiveRecord->quantity_available = $newQuantityAvailable;
                    $previousActiveRecord->save();
                } else {
                    // Create new storage history record if no active record exists
                    $previousActiveRecord = MaterialStorageHistory::create([
                        'material_id' => $detail->material_id,
                        'storage_area_id' => $detail->storage_area_id,
                        'expiry_date' => $detail->expiry_date,
                        'quantity' => $detail->quantity,
                        'quantity_available' => $newQuantityAvailable,
                        'provider_id' => $importReceipt->type === 'NORMAL' ? $importReceipt->provider_id : 1,
                        'status' => 'ACTIVE'
                    ]);
                }

                // Create history detail
                $actionType = match($importReceipt->type) {
                    'NORMAL' => 'IMPORT_NORMAL',
                    'RETURN' => 'IMPORT_RETURN'
                };

                MaterialStorageHistoryDetail::create([
                    'material_storage_history_id' => $previousActiveRecord->id,
                    'quantity_before' => $previousActiveRecord->quantity_available - $detail->quantity,
                    'quantity_change' => $detail->quantity,
                    'quantity_after' => $newQuantityAvailable,
                    'action_type' => $actionType,
                    'created_by' => $approvedById
                ]);

                // Update material quantity
                $material = Material::find($detail->material_id);
                $material->quantity_available += $detail->quantity;
                $material->save();
            }

            // [BƯỚC 4] - Update receipt status
            $importReceipt->status = 'COMPLETED';
            $importReceipt->save();

            // [BƯỚC 5] - Prepare response
            $response = [
                'success' => true,
                'message' => 'Duyệt phiếu nhập kho thành công',
                'data' => [
                    'id' => $importReceipt->id,
                    'code' => $importReceipt->code,
                    'status' => $importReceipt->status,
                    'approved_at' => date('Y-m-d H:i:s')
                ]
            ];

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