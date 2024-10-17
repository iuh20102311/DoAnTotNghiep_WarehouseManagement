<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\ProductImportReceipt;
use App\Models\ProductInventory;
use App\Models\Provider;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductImportReceiptController
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

        $totalReceipts = ProductImportReceipt::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();

        header('Content-Type: application/json');
        echo json_encode(['total_receipts' => $totalReceipts]);
    }

    public function getProductImportReceipts(): Collection
    {
        $productIRs = ProductImportReceipt::query()->where('status', '!=' , 'DELETED');

        if (isset($_GET['quantity'])) {
            $quantity = urldecode($_GET['quantity']);
            $productIRs->where('quantity', $quantity);
        }

        if (isset($_GET['type'])) {
            $type = urldecode($_GET['type']);
            $productIRs->where('type', $type);
        }

        $productIRs = $productIRs->get();
        foreach ($productIRs as $index => $productIR) {
            $warehouse = Warehouse::query()->where('id', $productIR->warehouse_id)->first();
            unset($productIR->warehouse_id);
            $productIR->warehouse = $warehouse;
        }

        return $productIRs;
    }

    public function getProductImportReceiptById($id) : ?Model
    {
        $productIR = ProductImportReceipt::query()->where('id',$id)->first();
        $warehouse = Warehouse::query()->where('id',$productIR->warehouse_id)->first();
        if ($productIR) {
            unset($productIR->warehouse_id);
            $productIR->warehouse = $warehouse;
            return $productIR;
        } else {
            return null;
        }
    }

    public function getImportReceiptDetailsByExportReceipt($id)
    {
        $productIRs = ProductImportReceipt::query()->where('id',$id)->first();
        $productIRList = $productIRs->ProductImportReceiptDetails;
        foreach ($productIRList as $key => $value) {
            $product = Product::query()->where('id', $value->product_id)->first();
            unset($value->product_id);
            $value->product = $product;
        }
        return $productIRList;
    }

    public function createProductImportReceipt(): Model | string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $productIR = new ProductImportReceipt();
        $error = $productIR->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $productIR->fill($data);
        $productIR->save();
        return $productIR;
    }

    public function updateProductImportReceiptById($id): bool | int | string
    {
        $productIR = ProductImportReceipt::find($id);

        if (!$productIR) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $productIR->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $productIR->fill($data);
        $productIR->save();

        return $productIR;
    }

    public function deleteProductImportReceipt($id): string
    {
        $productIR = ProductImportReceipt::find($id);

        if ($productIR) {
            $productIR->status = 'DELETED';
            $productIR->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }

    public function importProducts()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $warehouseExists = Warehouse::where('id', $data['warehouse_id'])->exists();
        if (!$warehouseExists) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Kho nhập kho không tồn tại']);
            return;
        }

        $productImportReceipt = ProductImportReceipt::create([
            'warehouse_id' => $data['warehouse_id']
        ]);

        $products = $data['products'] ?? [];

        $totalPrice = 0;

        foreach ($products as $product) {
            $productInventory = ProductInventory::where('product_id', $product['product_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->first();

            $productImportReceiptDetail = $productImportReceipt->details()->create([
                'product_id' => $product['product_id'],
                'quantity' => $product['quantity'],
            ]);

            if ($productInventory) {
                $productInventory->quantity_available += $product['quantity'];
                $productInventory->minimum_stock_level = max($productInventory->minimum_stock_level, $product['minimum_stock_level']);
                $productInventory->save();
            } else {
                ProductInventory::create([
                    'product_id' => $product['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'quantity_available' => $product['quantity'],
                    'minimum_stock_level' => $product['minimum_stock_level'],
                ]);
            }

            // Cập nhật số lượng sản phẩm trong bảng products
            $productModel = Product::find($product['product_id']);
            if ($productModel) {
                $productModel->quantity += $product['quantity'];
                $productModel->save();
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Nhập kho thành công']);
    }

}