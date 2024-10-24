<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\ProductImportReceipt;
use App\Models\ProductStorageLocation;
use App\Models\StorageArea;
use App\Models\User;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class ProductImportReceiptController
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

        $totalReceipts = ProductImportReceipt::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();

        header('Content-Type: application/json');
        echo json_encode(['total_receipts' => $totalReceipts]);
    }

    public function getProductImportReceipts(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $productIRs = ProductImportReceipt::query()->where('status', '!=' , 'DELETED')
            ->with(['creator', 'approver', 'receiver','details']);

        if (isset($_GET['quantity'])) {
            $quantity = urldecode($_GET['quantity']);
            $productIRs->where('quantity', $quantity);
        }

        if (isset($_GET['type'])) {
            $type = urldecode($_GET['type']);
            $productIRs->where('type', $type);
        }

        return $this->paginateResults($productIRs, $perPage, $page)->toArray();
    }

    public function getProductImportReceiptById($id) : false|string
    {
        $productIR = ProductImportReceipt::query()->where('id',$id)
            ->with(['creator', 'approver', 'receiver','details'])
            ->first();

        if (!$productIR) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($productIR->toArray());
    }

    public function getImportReceiptDetailsByExportReceipt($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $productIRs = ProductImportReceipt::query()->where('id', $id)->firstOrFail();
        $productImportReceiptDetailsQuery = $productIRs->details()
            ->with(['product','productImportReceipt','storageArea'])
            ->getQuery();

        return $this->paginateResults($productImportReceiptDetailsQuery, $perPage, $page)->toArray();
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

        try {
            // Kiểm tra dữ liệu đầu vào
            if (!isset($data['storage_area_id'])) {
                throw new \Exception('storage_area_id là bắt buộc');
            }
            if (!isset($data['receiver_id'])) {
                throw new \Exception('receiver_id là bắt buộc');
            }
            if (!isset($data['products']) || !is_array($data['products'])) {
                throw new \Exception('Danh sách products là bắt buộc và phải là một mảng');
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

            // Kiểm tra người nhận có tồn tại và đang hoạt động không
            $receiver = User::where('id', $data['receiver_id'])
                ->where('status', 'ACTIVE')
                ->where('deleted', false)
                ->first();

            if (!$receiver) {
                throw new \Exception('Người nhận không tồn tại hoặc không hoạt động');
            }

            // Kiểm tra tất cả các sản phẩm trước khi thực hiện bất kỳ thao tác nào
            $invalidProducts = [];
            foreach ($data['products'] as $product) {
                if (!isset($product['product_id']) || !isset($product['quantity'])) {
                    throw new \Exception('Thông tin product_id và quantity là bắt buộc cho mỗi sản phẩm');
                }

                $productModel = Product::find($product['product_id']);
                if (!$productModel) {
                    $invalidProducts[] = $product['product_id'];
                }
            }

            // Nếu có bất kỳ sản phẩm không hợp lệ nào, dừng quá trình và trả về lỗi
            if (!empty($invalidProducts)) {
                throw new \Exception('Một số sản phẩm không tồn tại: ' . implode(', ', $invalidProducts));
            }

            // Nếu tất cả sản phẩm đều hợp lệ, tiến hành nhập kho
            $productImportReceipt = ProductImportReceipt::create([
                'note' => $data['note'] ?? null,
                'created_by' => $createdById,
                'approved_by' => $approvedById,
                'receiver_id' => $receiver->id,
            ]);

            foreach ($data['products'] as $product) {
                $productModel = Product::find($product['product_id']);
                $quantity = $product['quantity'];

                $productImportReceiptDetail = $productImportReceipt->details()->create([
                    'product_id' => $product['product_id'],
                    'storage_area_id' => $data['storage_area_id'],
                    'quantity' => $quantity,
                ]);

                $productStorageLocation = ProductStorageLocation::firstOrNew([
                    'product_id' => $product['product_id'],
                    'storage_area_id' => $data['storage_area_id'],
                ]);

                // Thêm vào bảng Product Storage Location
                $productStorageLocation->quantity = ($productStorageLocation->quantity ?? 0) + $quantity;
                $productStorageLocation->save();

                // Thêm vào bảng Products
                $productModel->quantity_available += $quantity;

                // Chỉ cập nhật minimum_stock_level nếu có giá trị mới được cung cấp
                if (isset($product['minimum_stock_level'])) {
                    $productModel->minimum_stock_level = $product['minimum_stock_level'];
                }

                $productModel->save();
            }

            $productImportReceipt->status = 'PENDING';
            $productImportReceipt->save();

            $response = [
                'message' => 'Nhập kho thành công',
                'product_import_receipt_id' => $productImportReceipt->id
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