<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialImportReceipt;
use App\Models\MaterialInventory;
use App\Models\Provider;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MaterialImportReceiptController
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

        $totalReceipts = MaterialImportReceipt::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();

        header('Content-Type: application/json');
        echo json_encode(['total_receipts' => $totalReceipts]);
    }

    public function getMaterialImportReceipts(): Collection
    {
        $materialIRs = MaterialImportReceipt::query()->where('status', '!=' , 'DELETED');

        if (isset($_GET['type'])) {
            $type = urldecode($_GET['type']);
            $materialIRs->where('type', $type);
        }

        if (isset($_GET['total_price'])) {
            $total_price = urldecode($_GET['total_price']);
            $materialIRs->where('total_price', $total_price);
        }

        if (isset($_GET['total_price_min'])) {
            $total_price_min = urldecode($_GET['total_price_min']);
            $materialIRs->where('total_price', '>=', $total_price_min);
        }

        if (isset($_GET['total_price_max'])) {
            $total_price_max = urldecode($_GET['total_price_max']);
            $materialIRs->where('total_price', '<=', $total_price_max);
        }

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $materialIRs->where('status', $status);
        }

        $materialIRs = $materialIRs->get();
        foreach ($materialIRs as $index => $materialIR) {
            $warehouse = Warehouse::query()->where('id',$materialIR->warehouse_id)->first();
            unset($materialIR->warehouse_id);
            $materialIR->warehouse = $warehouse;
        }

        return $materialIRs;
    }

    public function getMaterialImportReceiptById($id) : ?Model
    {
        $materialIR = MaterialImportReceipt::query()->where('id',$id)->first();
        $warehouse = Warehouse::query()->where('id',$materialIR->warehouse_id)->first();
        if ($materialIR) {
            unset($materialIR->warehouse_id);
            $materialIR->warehouse = $warehouse;
            return $materialIR;
        } else {
            return null;
        }
    }

    public function getImportReceiptDetailsByImportReceipt($id)
    {
        $materialIRs = MaterialImportReceipt::query()->where('id',$id)->first();
        $materialIRDList = $materialIRs->MaterialImportReceiptDetails;
        foreach ($materialIRDList as $key => $value) {
            $material = Material::query()->where('id', $value->material_id)->first();
            unset($value->material_id);
            $value->material = $material;
        }
        return $materialIRDList;
    }

    public function createMaterialImportReceipt(): Model | string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $materialIR = new MaterialImportReceipt();
        $error = $materialIR->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $materialIR->fill($data);
        $materialIR->save();
        return $materialIR;
    }

    public function updateMaterialImportReceiptById($id): bool | int | string
    {
        $materialIR = MaterialImportReceipt::find($id);

        if (!$materialIR) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $materialIR->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $materialIR->fill($data);
        $materialIR->save();

        return $materialIR;
    }

    public function deleteMaterialImportReceipt($id): string
    {
        $materialIR = MaterialImportReceipt::find($id);

        if ($materialIR) {
            $materialIR->status = 'DELETED';
            $materialIR->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }

    public function importMaterials()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $warehouseExists = Warehouse::where('id', $data['warehouse_id'])->exists();
        if (!$warehouseExists) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Kho nhập kho không tồn tại']);
            return;
        }

        $providerExists = Provider::where('id', $data['provider_id'])->exists();
        if (!$providerExists) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Nhà cung cấp không tồn tại']);
            return;
        }

        // Kiểm tra nếu người dùng gửi receipt_id hoặc nếu không gửi thì ta tự động tạo
        $receiptId = null;
        if (isset($data['receipt_id'])) {
            $receiptId = $data['receipt_id'];
            // Kiểm tra receipt_id đã tồn tại hay chưa
            $existingReceipt = MaterialImportReceipt::where('receipt_id', $receiptId)->first();
            if ($existingReceipt) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Receipt ID đã tồn tại']);
                return;
            }
        } else {
            // Tạo receipt_id ngẫu nhiên không trùng
            do {
                $receiptId = mt_rand(1, 10000); // Tạo giá trị ngẫu nhiên từ 1000000 đến 9999999
                $existingReceipt = MaterialImportReceipt::where('receipt_id', $receiptId)->first();
            } while ($existingReceipt);
        }

        $materialImportReceipt = MaterialImportReceipt::create([
            'warehouse_id' => $data['warehouse_id'],
            'provider_id' => $data['provider_id'],
            'receipt_id' => $receiptId,
        ]);

        $materials = $data['materials'] ?? [];
        $totalPrice = 0;

        foreach ($materials as $material) {
            $materialInventory = MaterialInventory::where('material_id', $material['material_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->first();

            $price = $material['price'];
            $quantity = $material['quantity'];
            $totalPrice += $price * $quantity;

            $materialImportReceiptDetail = $materialImportReceipt->details()->create([
                'material_id' => $material['material_id'],
                'quantity' => $quantity,
                'price' => $price,
            ]);

            if ($materialInventory) {
                $materialInventory->quantity_available += $quantity;
                $materialInventory->minimum_stock_level = 0;
                $materialInventory->save();
            } else {
                MaterialInventory::create([
                    'provider_id' => $data['provider_id'],
                    'material_id' => $material['material_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'quantity_available' => $quantity,
                ]);
            }

            // Cập nhật số lượng nguyên vật liệu trong bảng materials
            $materialModel = Material::find($material['material_id']);
            if ($materialModel) {
                $materialModel->quantity += $quantity;
                $materialModel->save();
            }
        }

        // Cập nhật tổng giá trị vào material_import_receipt
        $materialImportReceipt->total_price = $totalPrice;
        $materialImportReceipt->save();

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Nhập kho thành công']);
    }
}