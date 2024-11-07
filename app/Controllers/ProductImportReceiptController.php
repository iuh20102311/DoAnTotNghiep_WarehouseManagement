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

    public function countTotalReceipts(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['month']) || !isset($data['year'])) {
                return [
                    'success' => false,
                    'error' => 'Tháng và năm là bắt buộc'
                ];
            }

            $month = $data['month'];
            $year = $data['year'];

            $totalReceipts = (new ProductImportReceipt())
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->count();

            return [
                'success' => true,
                'data' => ['total_receipts' => $totalReceipts]
            ];

        } catch (\Exception $e) {
            error_log("Error in countTotalReceipts: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductImportReceipts(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $query = (new ProductImportReceipt())
                ->where('deleted', false)
                ->with([
                    'creator' => function($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'receiver' => function($query) {
                        $query->select('id', 'email', 'role_id')
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
                $query->where('type', urldecode($_GET['type']));
            }

            if (isset($_GET['quantity'])) {
                $query->where('quantity', urldecode($_GET['quantity']));
            }

            $result = $this->paginateResults($query, $perPage, $page)->toArray();

            if (isset($result['data']) && is_array($result['data'])) {
                foreach ($result['data'] as &$item) {
                    if (isset($item['creator']['profile'])) {
                        $item['creator']['full_name'] = trim($item['creator']['profile']['first_name'] . ' ' . $item['creator']['profile']['last_name']);
                    }
                    if (isset($item['receiver']['profile'])) {
                        $item['receiver']['full_name'] = trim($item['receiver']['profile']['first_name'] . ' ' . $item['receiver']['profile']['last_name']);
                    }
                }
            }

            return $result;

        } catch (\Exception $e) {
            error_log("Error in getProductImportReceipts: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductImportReceiptById($id): array
    {
        try {
            $productIR = (new ProductImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->with([
                    'creator' => function($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'receiver' => function($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details'
                ])
                ->first();

            if (!$productIR) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = $productIR->toArray();

            // Thêm full_name
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
            error_log("Error in getProductImportReceiptById: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getImportReceiptDetailsByImportReceipt($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $productIR = (new ProductImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$productIR) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $detailsQuery = $productIR->details()
                ->with(['product', 'storageArea', 'productImportReceipt'])
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

    public function createProductImportReceipt(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $productIR = new ProductImportReceipt();

            $errors = $productIR->validate($data);
            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $productIR->fill($data);
            $productIR->save();

            return [
                'success' => true,
                'data' => $productIR->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createProductImportReceipt: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProductImportReceiptById($id): array
    {
        try {
            $productIR = (new ProductImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$productIR) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $productIR->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $productIR->fill($data);
            $productIR->save();

            return [
                'success' => true,
                'data' => $productIR->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateProductImportReceiptById: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteProductImportReceipt($id): array
    {
        try {
            $productIR = (new ProductImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$productIR) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $productIR->deleted = true;
            $productIR->save();

            return [
                'success' => true,
                'message' => 'Xóa thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteProductImportReceipt: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
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

            $productImportReceipt->status = 'COMPLETED';
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