<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialImportReceipt;
use App\Models\MaterialStorageLocation;
use App\Models\Provider;
use App\Models\StorageArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

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
        $materialIRs = MaterialImportReceipt::query()->where('status', '!=', 'DELETED');

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
            $provider = Provider::query()->where('id', $materialIR->provider_id)->first();
            $creator = User::query()->where('id', $materialIR->created_by)->first();
            $receiver = User::query()->where('id', $materialIR->receiver_id)->first();
            $approver = User::query()->where('id', $materialIR->approved_by)->first();

            unset($materialIR->provider_id);

            $materialIR->provider = $provider;
            $materialIR->created_by = $creator;
            $materialIR->receiver_id = $receiver;
            $materialIR->approved_by = $approver;
        }

        return $materialIRs;
    }

    public function getMaterialImportReceiptById($id): ?Model
    {
        $materialIR = MaterialImportReceipt::query()->where('id', $id)->first();
        $provider = Provider::query()->where('id', $materialIR->provider_id)->first();
        $creator = User::query()->where('id', $materialIR->created_by)->first();
        $receiver = User::query()->where('id', $materialIR->receiver_id)->first();
        $approver = User::query()->where('id', $materialIR->approved_by)->first();

        if ($materialIR) {
            unset($materialIR->provider_id);

            $materialIR->provider = $provider;
            $materialIR->created_by = $creator;
            $materialIR->receiver_id = $receiver;
            $materialIR->approved_by = $approver;

            return $materialIR;
        } else {
            return null;
        }
    }

    public function getImportReceiptDetailsByImportReceipt($id)
    {
        $materialIRs = MaterialImportReceipt::query()->where('id', $id)->first();
        $materialIRDList = $materialIRs->details;
        foreach ($materialIRDList as $key => $value) {
            $material = Material::query()->where('id', $value->material_id)->first();
            $storage = StorageArea::query()->where('id', $value->storage_area_id)->first();
            unset($value->material_id);
            unset($value->storage_area_id);
            $value->material = $material;
            $value->storage_area = $storage;
        }
        return $materialIRDList;
    }

    public function createMaterialImportReceipt(): Model|string
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

    public function updateMaterialImportReceiptById($id): bool|int|string
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
        } else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }

    public function importMaterials()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            // Kiểm tra dữ liệu đầu vào
            if (!isset($data['provider_id'])) {
                throw new \Exception('provider_id là bắt buộc');
            }
            if (!isset($data['storage_area_id'])) {
                throw new \Exception('storage_area_id là bắt buộc');
            }
            if (!isset($data['receiver_id'])) {
                throw new \Exception('receiver_id là bắt buộc');
            }
            if (!isset($data['materials']) || !is_array($data['materials'])) {
                throw new \Exception('Danh sách materials là bắt buộc và phải là một mảng');
            }

            // Lấy token JWT từ header
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                throw new \Exception('Token không tồn tại');
            }

            // Giải mã token JWT và lấy ID người dùng
            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $createdById = $parsedToken->claims()->get('id');
            $approvedById = $parsedToken->claims()->get('id');

            $storageExists = StorageArea::where('id', $data['storage_area_id'])->exists();
            if (!$storageExists) {
                throw new \Exception('Kho nhập kho không tồn tại');
            }

            $providerExists = Provider::where('id', $data['provider_id'])->exists();
            if (!$providerExists) {
                throw new \Exception('Nhà cung cấp không tồn tại');
            }

            // Kiểm tra nếu người dùng gửi receipt_id hoặc nếu không gửi thì ta tự động tạo
            $receiptId = null;
            if (isset($data['receipt_id'])) {
                $receiptId = $data['receipt_id'];
                // Kiểm tra receipt_id đã tồn tại hay chưa
                $existingReceipt = MaterialImportReceipt::where('receipt_id', $receiptId)->first();
                if ($existingReceipt) {
                    throw new \Exception('Receipt ID đã tồn tại');
                }
            } else {
                // Tạo receipt_id ngẫu nhiên không trùng
                do {
                    $receiptId = mt_rand(1, 10000);
                    $existingReceipt = MaterialImportReceipt::where('receipt_id', $receiptId)->first();
                } while ($existingReceipt);
            }

            // Kiểm tra người nhận có tồn tại và đang hoạt động không
            $receiver = User::where('id', $data['receiver_id'])
                ->where('status', 'ACTIVE')
                ->where('deleted', false)
                ->first();

            if (!$receiver) {
                throw new \Exception('Người nhận không tồn tại hoặc không hoạt động');
            }

            // Kiểm tra tất cả các nguyên liệu trước khi thực hiện bất kỳ thao tác nào
            $invalidMaterials = [];
            foreach ($data['materials'] as $material) {
                if (!isset($material['material_id']) || !isset($material['quantity']) || !isset($material['price'])) {
                    throw new \Exception('Thông tin material_id, quantity và price là bắt buộc cho mỗi nguyên liệu');
                }

                $materialModel = Material::find($material['material_id']);
                if (!$materialModel) {
                    $invalidMaterials[] = $material['material_id'];
                }
            }

            // Nếu có bất kỳ nguyên liệu không hợp lệ nào, dừng quá trình và trả về lỗi
            if (!empty($invalidMaterials)) {
                throw new \Exception('Một số nguyên liệu không tồn tại: ' . implode(', ', $invalidMaterials));
            }

            // Nếu tất cả nguyên liệu đều hợp lệ, tiến hành nhập kho
            $materialImportReceipt = MaterialImportReceipt::create([
                'provider_id' => $data['provider_id'],
                'receipt_id' => $receiptId,
                'note' => $data['note'],
                'created_by' => $createdById,
                'approved_by' => $approvedById,
                'receiver_id' => $receiver->id,
            ]);

            $totalPrice = 0;

            foreach ($data['materials'] as $material) {
                $materialModel = Material::find($material['material_id']);
                $price = $material['price'];
                $quantity = $material['quantity'];
                $totalPrice += $price * $quantity;

                $materialImportReceiptDetail = $materialImportReceipt->details()->create([
                    'material_id' => $material['material_id'],
                    'storage_area_id' => $data['storage_area_id'],
                    'quantity' => $quantity,
                    'price' => $price,
                ]);

                $materialStorageLocation = MaterialStorageLocation::firstOrNew([
                    'material_id' => $material['material_id'],
                    'storage_area_id' => $data['storage_area_id'],
                ]);

                // Thêm vào bảng Material Storage Location
                $materialStorageLocation->quantity = ($materialStorageLocation->quantity ?? 0) + $quantity;
                $materialStorageLocation->provider_id = $data['provider_id'];
                $materialStorageLocation->save();

                // Thêm vào bảng Materials
                $materialModel->quantity_available += $quantity;

                // Chỉ cập nhật minimum_stock_level nếu có giá trị mới được cung cấp
                if (isset($material['minimum_stock_level'])) {
                    $materialModel->minimum_stock_level = $material['minimum_stock_level'];
                }

                $materialModel->save();
            }

            $materialImportReceipt->total_price = $totalPrice;
            $materialImportReceipt->status = 'PENDING';
            $materialImportReceipt->save();

            $response = [
                'message' => 'Nhập kho thành công',
                'material_import_receipt_id' => $materialImportReceipt->id,
                'receipt_id' => $materialImportReceipt->receipt_id
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