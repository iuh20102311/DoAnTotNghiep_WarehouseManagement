<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\ProductExportReceipt;
use App\Models\ProductInventory;
use App\Models\ProductStorageLocation;
use App\Models\StorageArea;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;


class ProductExportReceiptController
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

            $totalReceipts = (new ProductExportReceipt())
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

    public function getProductExportReceipts(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $productER = (new ProductExportReceipt())
                ->where('deleted', false)
                ->with([
                    'creator' => function($productER) {
                        $productER->select('id', 'email', 'role_id')
                            ->with(['profile' => function($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details'
                ]);

            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                $productER->where('type', $type);
            }

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $productER->where('status', $status);
            }

            $result = $this->paginateResults($productER, $perPage, $page)->toArray();

            if (isset($result['data']) && is_array($result['data'])) {
                foreach ($result['data'] as &$item) {
                    if (isset($item['creator']['profile'])) {
                        $item['creator']['full_name'] = trim($item['creator']['profile']['first_name'] . ' ' . $item['creator']['profile']['last_name']);
                    }
                }
            }

            return  $result;

        } catch (\Exception $e) {
            error_log("Error in getProductExportReceipts: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductExportReceiptById($id): array
    {
        try {
            $productER = (new ProductExportReceipt())
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

            if (!$productER) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = $productER->toArray();

            if (isset($data['creator']['profile'])) {
                $data['creator']['full_name'] = trim($data['creator']['profile']['first_name'] . ' ' . $data['creator']['profile']['last_name']);
            }

            return $data;

        } catch (\Exception $e) {
            error_log("Error in getProductExportReceiptById: " . $e->getMessage());
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

            $productER = (new ProductExportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$productER) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $detailsQuery = $productER->details()
                ->with(['product', 'storageArea'])
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

    public function createProductExportReceipt(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $productER = new ProductExportReceipt();

            $errors = $productER->validate($data);
            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $productER->fill($data);
            $productER->save();

            return [
                'success' => true,
                'data' => $productER->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createProductExportReceipt: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProductExportReceiptById($id): array
    {
        try {
            $productER = (new ProductExportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$productER) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $productER->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $productER->fill($data);
            $productER->save();

            return [
                'success' => true,
                'data' => $productER->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateProductExportReceiptById: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteProductExportReceipt($id): array
    {
        try {
            $productER = (new ProductExportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$productER) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $productER->deleted = true;
            $productER->save();

            return [
                'success' => true,
                'message' => 'Xóa thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteProductExportReceipt: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function exportProducts(): void
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