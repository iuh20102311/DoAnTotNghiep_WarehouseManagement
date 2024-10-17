<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialExportReceipt;
use App\Models\MaterialStorageLocation;
use App\Models\StorageArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Utils\TokenGenerator;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

class MaterialExportReceiptController
{
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


    public function getMaterialExportReceipts(): Collection
    {
        $materialERs = MaterialExportReceipt::query()->where('status', '!=', 'DELETED')->get();

        if (isset($_GET['type'])) {
            $type = urldecode($_GET['type']);
            $materialERs->where('type', $type);
        }

        foreach ($materialERs as $index => $materialER) {
            $storage = StorageArea::query()->where('id', $materialER->storage_area_id)->first();
            unset($materialER->storage_area_id);
            $materialER->storageArea = $storage;
        }

        return $materialERs;
    }

    public function getMaterialExportReceiptById($id): ?Model
    {
        $materialER = MaterialExportReceipt::query()->where('id', $id)->first();
        $created_by = User::query()->where('id', $materialER->created_by)->first();
        $approved_by = User::query()->where('id', $materialER->approved_by)->first();
        $receiver_id = User::query()->where('id', $materialER->receiver_id)->first();

        if ($materialER) {
            unset($materialER->created_by);
            unset($materialER->approved_by);
            unset($materialER->receiver_id);
            $materialER->creator = $created_by;
            $materialER->approver = $approved_by;
            $materialER->receiver = $receiver_id;
            return $materialER;
        } else {
            return null;
        }
    }

    public function getExportReceiptDetailsByExportReceipt($id)
    {
        $materialERs = MaterialExportReceipt::query()->where('id', $id)->first();
        $materialERDList = $materialERs->details;

        foreach ($materialERDList as $key => $value) {
            $material = Material::query()->where('id', $value->material_id)->first();
            unset($value->material_id);
            $value->material = $material;
        }
        return $materialERDList;
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
                'created_by' => 1,
                'approved_by' => 1,
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