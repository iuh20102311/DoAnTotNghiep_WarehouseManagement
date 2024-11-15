<?php

namespace App\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImportReceipt;
use App\Models\ProductStorageLocation;
use App\Models\User;
use App\Utils\PaginationTrait;
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
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $query = (new ProductImportReceipt())
                ->where('deleted', false)
                ->with([
                    'creator' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'receiver' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
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

            if (isset($_GET['status'])) {
                $query->where('status', urldecode($_GET['status']));
            }

            if (isset($_GET['note'])) {
                $note = urldecode($_GET['note']);
                $query->where('note', '%' . $note . '%');
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

    public function getProductImportReceiptByCode($code): array
    {
        try {
            $productIR = (new ProductImportReceipt())
                ->where('code', $code)
                ->where('deleted', false)
                ->with([
                    'creator' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'receiver' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details.productStorageLocation',
                    'details.product'
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
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

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
            // Validate basic required fields
            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'RETURN'])) {
                throw new \Exception('Type phải là NORMAL hoặc RETURN');
            }

            if (!isset($data['product_storage_location_id'])) {
                throw new \Exception('product_storage_location_id là bắt buộc');
            }

            if (!isset($data['receiver_id'])) {
                throw new \Exception('receiver_id là bắt buộc');
            }

            // Validate fields based on type
            $allowedFields = [
                'NORMAL' => ['type', 'product_storage_location_id', 'receiver_id', 'note', 'products'],
                'RETURN' => ['type', 'product_storage_location_id', 'receiver_id', 'note', 'order_code', 'products']
            ];

            // Check for unexpected fields
            foreach ($data as $field => $value) {
                if (!in_array($field, $allowedFields[$data['type']])) {
                    throw new \Exception("Trường '$field' không được phép với type " . $data['type']);
                }
            }

            // Validate token JWT
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                throw new \Exception('Token không tồn tại');
            }

            // Giải mã token JWT và lấy ID người dùng
            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $createdById = $parsedToken->claims()->get('id');

            // Validate storage location
            $storageLocationExists = ProductStorageLocation::where('id', $data['product_storage_location_id'])
                ->exists();
            if (!$storageLocationExists) {
                throw new \Exception('Vị trí lưu trữ không tồn tại');
            }

            // Validate receiver
            $receiver = User::where('id', $data['receiver_id'])
                ->where('status', 'ACTIVE')
                ->where('deleted', false)
                ->first();

            if (!$receiver) {
                throw new \Exception('Người nhận không tồn tại hoặc không hoạt động');
            }

            // Process based on type
            if ($data['type'] === 'NORMAL') {
                if (!isset($data['products']) || !is_array($data['products'])) {
                    throw new \Exception('Danh sách products là bắt buộc và phải là một mảng');
                }

                // Validate product format
                foreach ($data['products'] as $product) {
                    if (!isset($product['product_id']) || !isset($product['quantity'])) {
                        throw new \Exception('Thông tin product_id và quantity là bắt buộc cho mỗi sản phẩm');
                    }

                    $allowedKeys = ['product_id', 'quantity'];
                    foreach ($product as $key => $value) {
                        if (!in_array($key, $allowedKeys)) {
                            throw new \Exception("Trường '$key' không được phép trong products");
                        }
                    }
                }
            } else { // RETURN type
                if (!isset($data['order_code'])) {
                    throw new \Exception('order_code là bắt buộc với type RETURN');
                }

                // Lấy thông tin đơn hàng
                $order = Order::where('code', $data['order_code'])
                    ->where('deleted', false)
                    ->first();

                if (!$order) {
                    throw new \Exception('Đơn hàng không tồn tại');
                }

                $orderDetails = $order->orderDetails()
                    ->where('deleted', false)
                    ->get();

                if ($orderDetails->isEmpty()) {
                    throw new \Exception('Đơn hàng không có sản phẩm');
                }

                if (isset($data['products'])) {
                    // Validate format của products
                    if (!is_array($data['products'])) {
                        throw new \Exception('products phải là một mảng');
                    }

                    foreach ($data['products'] as $product) {
                        // Validate required fields
                        if (!isset($product['product_id']) || !isset($product['quantity'])) {
                            throw new \Exception('Thông tin product_id và quantity là bắt buộc cho mỗi sản phẩm');
                        }

                        // Chỉ cho phép product_id và quantity
                        $allowedKeys = ['product_id', 'quantity'];
                        foreach ($product as $key => $value) {
                            if (!in_array($key, $allowedKeys)) {
                                throw new \Exception("Trường '$key' không được phép trong products với type RETURN");
                            }
                        }

                        // Kiểm tra sản phẩm có trong order không
                        $orderDetail = $orderDetails->firstWhere('product_id', $product['product_id']);
                        if (!$orderDetail) {
                            throw new \Exception("Sản phẩm ID {$product['product_id']} không có trong đơn hàng");
                        }

                        // Kiểm tra số lượng
                        if ($product['quantity'] > $orderDetail->quantity) {
                            throw new \Exception("Số lượng trả về của sản phẩm ID {$product['product_id']} không được lớn hơn số lượng đã bán ({$orderDetail->quantity})");
                        }
                    }
                } else {
                    // Nếu không có products, lấy toàn bộ từ order
                    $data['products'] = $orderDetails->map(function ($detail) {
                        return [
                            'product_id' => $detail->product_id,
                            'quantity' => $detail->quantity
                        ];
                    })->toArray();
                }
            }

            // Validate products existence
            $invalidProducts = [];
            foreach ($data['products'] as $product) {
                $productModel = Product::find($product['product_id']);
                if (!$productModel) {
                    $invalidProducts[] = $product['product_id'];
                }
            }

            if (!empty($invalidProducts)) {
                throw new \Exception('Một số sản phẩm không tồn tại: ' . implode(', ', $invalidProducts));
            }

            // Tạo mã phiếu nhập tự động
            do {
                $code = 'IMP' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $existingReceipt = ProductImportReceipt::where('code', $code)->exists();
            } while ($existingReceipt);

            // Tạo phiếu nhập
            $productImportReceipt = ProductImportReceipt::create([
                'code' => $code,
                'type' => $data['type'],
                'note' => $data['note'] ?? null,
                'created_by' => $createdById,
                'receiver_id' => $receiver->id,
                'status' => 'COMPLETED',
                'order_code' => $data['type'] === 'RETURN' ? $data['order_code'] : null
            ]);

            foreach ($data['products'] as $product) {
                $productModel = Product::find($product['product_id']);
                $quantity = $product['quantity'];

                // Tạo chi tiết phiếu nhập
                $productImportReceipt->details()->create([
                    'product_id' => $product['product_id'],
                    'product_storage_location_id' => $data['product_storage_location_id'],
                    'quantity' => $quantity,
                ]);

                // Cập nhật số lượng trong kho
                $productStorageLocation = ProductStorageLocation::find($data['product_storage_location_id']);
                $productStorageLocation->quantity += $quantity;
                $productStorageLocation->save();

                // Cập nhật số lượng trong bảng Products
                $productModel->quantity_available += $quantity;
                $productModel->save();
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Nhập kho thành công',
                'data' => [
                    'product_import_receipt_id' => $productImportReceipt->id,
                    'code' => $productImportReceipt->code,
                    'type' => $productImportReceipt->type,
                    'status' => $productImportReceipt->status,
                    'details' => $productImportReceipt->details()
                        ->with(['product:id,name,sku', 'productStorageLocation:id,storage_area_id'])
                        ->get()
                ]
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}