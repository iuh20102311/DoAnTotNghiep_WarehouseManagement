<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialExportReceipt;
use App\Models\MaterialStorageLocation;
use App\Models\StorageArea;
use App\Models\User;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class MaterialExportReceiptController
{
    use PaginationTrait;

    public function countTotalReceipts()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['month']) || !isset($data['year'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Tháng và năm là bắt buộc.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $month = $data['month'];
        $year = $data['year'];

        $totalReceipts = MaterialExportReceipt::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();

        header('Content-Type: application/json');
        echo json_encode(['total_receipts' => $totalReceipts]);
    }


    public function getMaterialExportReceipts(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $materialERs = MaterialExportReceipt::query()->where('status', '!=', 'DELETED')->with(['creator', 'approver', 'details']);

        if (isset($_GET['type'])) {
            $type = urldecode($_GET['type']);
            $materialERs->where('type', $type);
        }

        return $this->paginateResults($materialERs, $perPage, $page)->toArray();
    }

    public function getMaterialExportReceiptById($id): string
    {
        $materialER = MaterialExportReceipt::query()
            ->where('id', $id)
            ->where('status', '!=', 'DELETED')
            ->with(['creator', 'approver', 'details'])
            ->first();

        if (!$materialER) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($materialER->toArray());
    }

    public function getExportReceiptDetailsByExportReceipt($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $materialER = MaterialExportReceipt::query()->where('id', $id)->firstOrFail();
        $materialExportReceiptDetailsQuery = $materialER->details()
            ->with(['material', 'storageArea', 'materialExportReceipt'])
            ->getQuery();

        return $this->paginateResults($materialExportReceiptDetailsQuery, $perPage, $page)->toArray();
    }

    public function createMaterialExportReceipt(): Model|string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $materialER = new MaterialExportReceipt();
        $error = $materialER->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $materialER->fill($data);
        $materialER->save();
        return $materialER;
    }

    public function updateMaterialExportReceiptById($id): bool|int|string
    {
        $materialER = MaterialExportReceipt::find($id);

        if (!$materialER) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $materialER->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $materialER->fill($data);
        $materialER->save();

        return $materialER;
    }

    public function deleteMaterialExportReceipt($id): string
    {
        $materialER = MaterialExportReceipt::find($id);

        if ($materialER) {
            $materialER->status = 'DELETED';
            $materialER->save();
            return "Xóa thành công";
        } else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }

    public function exportMaterials()
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

            // Kiểm tra người nhận có tồn tại và đang hoạt động không
            $receiver = User::where('id', $data['receiver_id'])
                ->where('status', 'ACTIVE')
                ->where('deleted', false)
                ->first();

            if (!$receiver) {
                throw new \Exception('Người nhận không tồn tại hoặc không hoạt động');
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
                'approved_by' => $createdById,
                'receiver_id' => $receiver->id,
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