<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\ProductExportReceipt;
use App\Models\ProductInventory;
use App\Models\ProductStorageLocation;
use App\Models\StorageArea;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;


class ProductExportReceiptController
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

        $totalReceipts = ProductExportReceipt::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();

        header('Content-Type: application/json');
        echo json_encode(['total_receipts' => $totalReceipts]);
    }

    public function getProductExportReceipts(): Collection
    {
        $productERs = ProductExportReceipt::query()->where('status', '!=', 'DELETED');

        if (isset($_GET['type'])) {
            $type = urldecode($_GET['type']);
            $productERs->where('type', $type);
        }

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $productERs->where('status', $status);
        }

        $productERs = $productERs->get();
        foreach ($productERs as $index => $productER) {
            $storage = StorageArea::query()->where('id', $productER->storage_area_id)->first();
            unset($productER->storage_area_id);
            $productER->storage = $storage;
        }

        return $productERs;
    }

    public function getProductExportReceiptById($id): ?Model
    {
        $productER = ProductExportReceipt::query()->where('id', $id)->first();
        $storage = StorageArea::query()->where('id', $productER->storage_area_id)->first();
        if ($productER) {
            unset($productER->storage_area_id);
            $productER->storage = $storage;
            return $productER;
        } else {
            return null;
        }
    }

    public function getExportReceiptDetailsByExportReceipt($id)
    {
        $productERs = ProductExportReceipt::query()->where('id', $id)->first();
        $productERList = $productERs->ProductExportReceiptDetails;
        foreach ($productERList as $key => $value) {
            $product = Product::query()->where('id', $value->product_id)->first();
            unset($value->product_id);
            $value->product = $product;
        }
        return $productERList;
    }

    public function createProductExportReceipt(): Model|string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $productER = new ProductExportReceipt();
        $error = $productER->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $productER->fill($data);
        $productER->save();
        return $productER;
    }

    public function updateProductExportReceiptById($id): bool|int|string
    {
        $productER = ProductExportReceipt::find($id);

        if (!$productER) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $productER->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $productER->fill($data);
        $productER->save();

        return $productER;
    }

    public function deleteProductExportReceipt($id): string
    {
        $productER = ProductExportReceipt::find($id);

        if ($productER) {
            $productER->status = 'DELETED';
            $productER->save();
            return "Xóa thành công";
        } else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }

    public function exportProducts()
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

            // Kiểm tra tất cả sản phẩm trước khi tạo hóa đơn
            $products = $data['products'] ?? [];
            $missingProducts = [];

            foreach ($products as $product) {
                $productLocation = ProductStorageLocation::where('product_id', $product['product_id'])
                    ->where('storage_area_id', $storageArea->id)
                    ->where('deleted', false)
                    ->first();

                if (!$productLocation) {
                    $productInfo = Product::find($product['product_id']);
                    $productName = $productInfo ? $productInfo->name : "Sản phẩm không xác định";
                    $missingProducts[] = "{$productName} (ID: {$product['product_id']}) không tồn tại trong kho";
                } elseif ($productLocation->quantity < $product['quantity']) {
                    $productInfo = Product::find($product['product_id']);
                    $productName = $productInfo ? $productInfo->name : "Sản phẩm không xác định";
                    $missingProducts[] = "{$productName} (ID: {$product['product_id']}) không đủ số lượng trong kho. Có sẵn: {$productLocation->quantity}, Yêu cầu: {$product['quantity']}";
                }
            }

            if (!empty($missingProducts)) {
                throw new \Exception("Tạo hóa đơn xuất kho thất bại. " . implode(". ", $missingProducts));
            }

            // Tạo mới ProductExportReceipt
            $productExportReceipt = ProductExportReceipt::create([
                'note' => $data['note'] ?? '',
                'type' => 'NORMAL',
                'status' => 'PENDING',
                'created_by' => $createdById,
                'approved_by' => $createdById,
                'receiver_id' => $receiver->id,
            ]);

            // Tạo chi tiết xuất kho và cập nhật số lượng
            foreach ($products as $product) {
                $productLocation = ProductStorageLocation::where('product_id', $product['product_id'])
                    ->where('storage_area_id', $storageArea->id)
                    ->where('deleted', false)
                    ->first();

                // Tạo chi tiết xuất kho
                $productExportReceipt->details()->create([
                    'product_id' => $product['product_id'],
                    'storage_area_id' => $storageArea->id,
                    'quantity' => $product['quantity'],
                ]);

                // Cập nhật số lượng trong kho
                $productLocation->quantity -= $product['quantity'];
                $productLocation->save();
            }

            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'receipt_id' => $productExportReceipt->id], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}