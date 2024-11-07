<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialExportReceipt;
use App\Models\MaterialStorageLocation;
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
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $materialER = (new MaterialExportReceipt())
                ->where('deleted', false)
                ->with([
                    'creator' => function($productER) {
                        $productER->select('id', 'email', 'role_id')
                            ->with(['profile' => function($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details'
                ])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
                ->orderBy('created_at', 'desc');

            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                $materialER->where('type', $type);
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

    public function getMaterialExportReceiptById($id): array
    {
        try {
            $materialER = (new MaterialExportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->with([
                    'creator' => function($productER) {
                        $productER->select('id', 'email', 'role_id')
                            ->with(['profile' => function($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details'
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
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

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

    public function createMaterialExportReceipt(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $materialER = new MaterialExportReceipt();

            $errors = $materialER->validate($data);
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
            error_log("Error in createMaterialExportReceipt: " . $e->getMessage());
            return [
                'success' => false,
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

            // Kiểm tra xem kho cần xuất kho có tồn tại và đang hoạt động không
            $storageArea = StorageArea::where('id', $data['storage_area_id'])
                ->where('status', 'ACTIVE')
                ->where('deleted', false)
                ->first();

            if (!$storageArea) {
                throw new \Exception('Kho xuất kho không tồn tại hoặc không hoạt động');
            }

            // Kiểm tra tất cả nguyên vật liệu trước khi tạo hóa đơn
            $materials = $data['materials'] ?? [];
            $missingMaterials = [];

            foreach ($materials as $material) {
                $materialLocation = MaterialStorageLocation::where('material_id', $material['material_id'])
                    ->where('storage_area_id', $storageArea->id)
                    ->where('deleted', false)
                    ->first();

                if (!$materialLocation) {
                    $materialInfo = Material::find($material['material_id']);
                    $materialName = $materialInfo ? $materialInfo->name : "Nguyên vật liệu không xác định";
                    $missingMaterials[] = "{$materialName} (ID: {$material['material_id']}) không tồn tại trong kho";
                } elseif ($materialLocation->quantity < $material['quantity']) {
                    $materialInfo = Material::find($material['material_id']);
                    $materialName = $materialInfo ? $materialInfo->name : "Nguyên vật liệu không xác định";
                    $missingMaterials[] = "{$materialName} (ID: {$material['material_id']}) không đủ số lượng trong kho. Có sẵn: {$materialLocation->quantity}, Yêu cầu: {$material['quantity']}";
                }
            }

            if (!empty($missingMaterials)) {
                throw new \Exception("Tạo hóa đơn xuất kho thất bại. " . implode(". ", $missingMaterials));
            }

            // Tạo mới MaterialExportReceipt
            $materialExportReceipt = MaterialExportReceipt::create([
                'note' => $data['note'] ?? '',
                'type' => 'NORMAL',
                'status' => 'PENDING',
                'created_by' => $createdById,
            ]);

            // Tạo chi tiết xuất kho và cập nhật số lượng
            foreach ($materials as $material) {
                $materialLocation = MaterialStorageLocation::where('material_id', $material['material_id'])
                    ->where('storage_area_id', $storageArea->id)
                    ->where('deleted', false)
                    ->first();

                // Tạo chi tiết xuất kho
                $materialExportReceipt->details()->create([
                    'material_id' => $material['material_id'],
                    'storage_area_id' => $storageArea->id,
                    'quantity' => $material['quantity'],
                ]);

                // Cập nhật số lượng trong kho
                $materialLocation->quantity -= $material['quantity'];
                $materialLocation->save();

                // Cập nhật số lượng trong bảng materials
                $materialModel = Material::find($material['material_id']);
                if ($materialModel) {
                    $materialModel->quantity_available -= $material['quantity'];
                    $materialModel->save();
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'receipt_id' => $materialExportReceipt->id], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}