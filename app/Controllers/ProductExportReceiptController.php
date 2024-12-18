<?php

namespace App\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductExportReceipt;
use App\Models\ProductStorageHistoryDetail;
use App\Models\ProductStorageHistory;
use App\Models\StorageArea;
use App\Utils\PaginationTrait;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Illuminate\Database\Capsule\Manager as DB;


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
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $productER = (new ProductExportReceipt())
                ->where('deleted', false)
                ->with([
                    'creator' => function ($productER) {
                        $productER->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details'
                ])
                ->orderBy('created_at', 'desc');

            // Code filter
            if (isset($_GET['code'])) {
                $code = urldecode($_GET['code']);
                $productER->where('code', 'LIKE', '%' . $code . '%');
            }

            // Creator filter
            if (isset($_GET['created_by'])) {
                $createdBy = urldecode($_GET['created_by']);
                $productER->where('created_by', $createdBy);
            }

            // Receipt Date filters
            if (isset($_GET['receipt_date'])) {
                $receiptDate = urldecode($_GET['receipt_date']);
                $productER->whereDate('receipt_date', $receiptDate);
            }
            if (isset($_GET['receipt_date_from'])) {
                $receiptDateFrom = urldecode($_GET['receipt_date_from']);
                $productER->whereDate('receipt_date', '>=', $receiptDateFrom);
            }
            if (isset($_GET['receipt_date_to'])) {
                $receiptDateTo = urldecode($_GET['receipt_date_to']);
                $productER->whereDate('receipt_date', '<=', $receiptDateTo);
            }

            // Note filter
            if (isset($_GET['note'])) {
                $note = urldecode($_GET['note']);
                $productER->where('note', 'LIKE', '%' . $note . '%');
            }

            // Type filter
            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                $productER->where('type', $type);
            }

            // Status filter
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

            return $result;

        } catch (\Exception $e) {
            error_log("Error in getProductExportReceipts: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProductExportReceiptByCode($code): array
    {
        try {
            $productER = (new ProductExportReceipt())
                ->where('code', $code)
                ->where('deleted', false)
                ->with([
                    'creator' => function ($productER) {
                        $productER->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'details.storageArea',
                    'details.product'
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
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

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

//    public function exportProducts(): void
//    {
//        $data = json_decode(file_get_contents('php://input'), true);
//
//        try {
//            // [BƯỚC 1] - Validate basic required fields
//            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'CANCEL'])) {
//                throw new \Exception('Type phải là NORMAL hoặc CANCEL');
//            }
//
//            // [BƯỚC 2] - Validate allowed fields
//            $allowedFields = [
//                'NORMAL' => ['type', 'order_code', 'note', 'products'],
//                'CANCEL' => ['type', 'note', 'products']
//            ];
//
//            foreach ($data as $field => $value) {
//                if (!in_array($field, $allowedFields[$data['type']])) {
//                    throw new \Exception("Trường '$field' không được phép với type " . $data['type']);
//                }
//            }
//
//            // [BƯỚC 3] - Token validation
//            $headers = apache_request_headers();
//            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
//            if (!$token) {
//                throw new \Exception('Token không tồn tại');
//            }
//
//            $parser = new Parser(new JoseEncoder());
//            $parsedToken = $parser->parse($token);
//            $createdById = $parsedToken->claims()->get('id');
//
//            // [BƯỚC 4] - Type specific validation
//            $order = null;
//            if ($data['type'] === 'NORMAL') {
//                if (!isset($data['order_code'])) {
//                    throw new \Exception('order_code là bắt buộc với type NORMAL');
//                }
//
//                $order = Order::where('code', $data['order_code'])
//                    ->where('deleted', false)
//                    ->where('status', 'PROCESSED')
//                    ->first();
//
//                if (!$order) {
//                    throw new \Exception("Đơn hàng {$data['order_code']} không tồn tại hoặc chưa được xử lý");
//                }
//
//                // Kiểm tra đơn hàng đã xuất kho chưa
//                $existingExport = ProductExportReceipt::where('order_code', $data['order_code'])
//                    ->where('type', 'NORMAL')
//                    ->where('status', 'COMPLETED')
//                    ->where('deleted', false)
//                    ->first();
//
//                if ($existingExport) {
//                    throw new \Exception(
//                        "Đơn hàng {$data['order_code']} đã được xuất kho theo phiếu xuất {$existingExport->code}"
//                    );
//                }
//
//                // [BƯỚC 5] - Validate products cho type NORMAL
//                $orderDetails = $order->orderDetails()
//                    ->where('deleted', false)
//                    ->where('status', 'ACTIVE')
//                    ->get()
//                    ->keyBy('product_id');
//
//                if ($orderDetails->isEmpty()) {
//                    throw new \Exception('Đơn hàng không có sản phẩm');
//                }
//
//                $validatedProducts = [];
//
//                // Kiểm tra và xử lý products
//                foreach ($data['products'] as $product) {
//                    $storageArea = StorageArea::where('id', $product['storage_area_id'])
//                        ->where('deleted', false)
//                        ->first();
//
//                    if (!$storageArea) {
//                        throw new \Exception("Kho chứa ID {$product['storage_area_id']} không tồn tại hoặc đã bị xóa");
//                    }
//
//                    if ($storageArea->type !== 'PRODUCT') {
//                        throw new \Exception("Kho chứa {$storageArea->name} không phải là kho thành phẩm");
//                    }
//
//                    if (!isset($product['product_id']) || !isset($product['quantity']) ||
//                        !isset($product['storage_area_id'])) {
//                        throw new \Exception('product_id, quantity và storage_area_id là bắt buộc cho mỗi sản phẩm');
//                    }
//
//                    // Kiểm tra sản phẩm có trong đơn hàng không
//                    if (!$orderDetails->has($product['product_id'])) {
//                        throw new \Exception("Sản phẩm ID {$product['product_id']} không có trong đơn hàng");
//                    }
//
//                    $orderDetail = $orderDetails[$product['product_id']];
//
//                    // Kiểm tra số lượng xuất
//                    if ($product['quantity'] <= 0) {
//                        throw new \Exception("Số lượng xuất phải lớn hơn 0");
//                    }
//
//                    if ($product['quantity'] > $orderDetail->quantity) {
//                        throw new \Exception(
//                            "Số lượng xuất ({$product['quantity']}) vượt quá số lượng trong đơn hàng ({$orderDetail->quantity})"
//                        );
//                    }
//
//                    // Kiểm tra số lượng trong kho
//                    $activeHistory = ProductStorageHistory::where('product_id', $product['product_id'])
//                        ->where('storage_area_id', $product['storage_area_id'])
//                        ->where('status', 'ACTIVE')
//                        ->where('deleted', false)
//                        ->first();
//
//                    if (!$activeHistory) {
//                        throw new \Exception("Không tìm thấy sản phẩm ID {$product['product_id']} trong kho {$product['storage_area_id']}");
//                    }
//
//                    if ($activeHistory->quantity_available < $product['quantity']) {
//                        throw new \Exception(
//                            "Không đủ số lượng trong kho cho sản phẩm ID {$product['product_id']}. " .
//                            "Cần: {$product['quantity']}, Có sẵn: {$activeHistory->quantity_available}"
//                        );
//                    }
//
//                    $validatedProducts[] = $product;
//                }
//            } else {
//                // [BƯỚC 6] - Validate products cho type CANCEL
//                if (!isset($data['products']) || empty($data['products'])) {
//                    throw new \Exception('Danh sách sản phẩm không được để trống');
//                }
//
//                $validatedProducts = [];
//                foreach ($data['products'] as $product) {
//                    if (!isset($product['product_id']) || !isset($product['quantity']) ||
//                        !isset($product['storage_area_id'])) {
//                        throw new \Exception('product_id, quantity và storage_area_id là bắt buộc cho mỗi sản phẩm');
//                    }
//
//                    // Kiểm tra số lượng trong kho
//                    $activeHistory = ProductStorageHistory::where('product_id', $product['product_id'])
//                        ->where('storage_area_id', $product['storage_area_id'])
//                        ->where('status', 'ACTIVE')
//                        ->where('deleted', false)
//                        ->first();
//
//                    if (!$activeHistory) {
//                        throw new \Exception("Không tìm thấy sản phẩm ID {$product['product_id']} trong kho {$product['storage_area_id']}");
//                    }
//
//                    if ($activeHistory->quantity_available < $product['quantity']) {
//                        throw new \Exception(
//                            "Không đủ số lượng trong kho cho sản phẩm ID {$product['product_id']}. " .
//                            "Cần: {$product['quantity']}, Có sẵn: {$activeHistory->quantity_available}"
//                        );
//                    }
//
//                    $validatedProducts[] = $product;
//                }
//            }
//
//            // [BƯỚC 7] - Generate receipt code
//            $currentDay = date('d');
//            $currentMonth = date('m');
//            $currentYear = date('y');
//            $prefix = "PXTP" . $currentDay . $currentMonth . $currentYear;
//
//            $latestExportReceipt = ProductExportReceipt::where('code', 'LIKE', $prefix . '%')
//                ->orderBy('code', 'desc')
//                ->first();
//
//            $sequence = $latestExportReceipt ? intval(substr($latestExportReceipt->code, -5)) + 1 : 1;
//            $code = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
//
//            // [BƯỚC 8] - Create export receipt
//            $productExportReceipt = ProductExportReceipt::create([
//                'code' => $code,
//                'note' => $data['note'] ?? '',
//                'type' => $data['type'],
//                'status' => 'COMPLETED',
//                'created_by' => $createdById,
//                'order_code' => $data['type'] === 'NORMAL' ? $data['order_code'] : null
//            ]);
//
//            // [BƯỚC 9] - Create details and update quantities
//            foreach ($validatedProducts as $product) {
//                // Lấy history hiện tại
//                $currentHistory = ProductStorageHistory::where('product_id', $product['product_id'])
//                    ->where('storage_area_id', $product['storage_area_id'])
//                    ->where('status', 'ACTIVE')
//                    ->where('deleted', false)
//                    ->first();
//
//                // Set INACTIVE cho history cũ
//                $currentHistory->status = 'INACTIVE';
//                $currentHistory->save();
//
//                // Tạo history mới với số lượng đã trừ
//                $newHistory = new ProductStorageHistory();
//                $newHistory->product_id = $product['product_id'];
//                $newHistory->storage_area_id = $product['storage_area_id'];
//                $newHistory->expiry_date = $currentHistory->expiry_date;
//                $newHistory->quantity = $currentHistory->quantity;
//                $newHistory->quantity_available = $currentHistory->quantity_available - $product['quantity'];
//                $newHistory->status = 'ACTIVE';
//                $newHistory->deleted = false;
//                $newHistory->save();
//
//                // Tạo chi tiết xuất kho
//                $productExportReceipt->details()->create([
//                    'product_id' => $product['product_id'],
//                    'storage_area_id' => $product['storage_area_id'],
//                    'quantity' => $product['quantity'],
//                    'expiry_date' => $currentHistory->expiry_date
//                ]);
//
//                // Cập nhật số lượng trong bảng products
//                $productModel = Product::find($product['product_id']);
//                $oldQuantity = $productModel->quantity_available;  // Lưu số lượng cũ
//                $productModel->quantity_available -= $product['quantity'];
//                $productModel->save();
//
//                // Tạo product inventory history
//                $actionType = match($data['type']) {
//                    'NORMAL' => 'EXPORT_NORMAL',
//                    'CANCEL' => 'EXPORT_CANCEL'
//                };
//
//                ProductStorageHistoryDetail::create([
//                    'storage_area_id' => $product['storage_area_id'],
//                    'product_id' => $product['product_id'],
//                    'quantity_before' => $oldQuantity,
//                    'quantity_change' => -$product['quantity'],  // Dấu trừ vì là xuất kho
//                    'quantity_after' => $productModel->quantity_available,
//                    'remaining_quantity' => $productModel->quantity_available,
//                    'action_type' => $actionType,
//                    'created_by' => $createdById
//                ]);
//            }
//
//            // [BƯỚC 10] - Load relationships for response
//            $exportReceipt = ProductExportReceipt::with([
//                'details.product',
//                'details.storageArea',
//                'creator.profile'
//            ])->find($productExportReceipt->id);
//
//            // [BƯỚC 11] - Prepare response
//            $response = [
//                'success' => true,
//                'message' => 'Xuất kho thành công',
//                'data' => [
//                    'id' => $exportReceipt->id,
//                    'code' => $exportReceipt->code,
//                    'type' => $exportReceipt->type,
//                    'status' => $exportReceipt->status,
//                    'note' => $exportReceipt->note,
//                    'created_at' => $exportReceipt->created_at,
//                    'creator' => [
//                        'id' => $exportReceipt->creator->id,
//                        'email' => $exportReceipt->creator->email,
//                        'profile' => [
//                            'id' => $exportReceipt->creator->profile->id,
//                            'first_name' => $exportReceipt->creator->profile->first_name,
//                            'last_name' => $exportReceipt->creator->profile->last_name,
//                        ]
//                    ]
//                ]
//            ];
//
//            // Thêm thông tin đơn hàng nếu có
//            if ($data['type'] === 'NORMAL' && $order) {
//                $response['data']['order'] = [
//                    'code' => $order->code,
//                    'created_at' => $order->created_at,
//                    'customer' => [
//                        'id' => $order->customer_id,
//                        'name' => $order->customer->name,
//                        'code' => $order->customer->code
//                    ]
//                ];
//            }
//
//            // Thêm chi tiết xuất kho và thông tin history
//            $response['data']['details'] = $exportReceipt->details->map(function ($detail) {
//                // Lấy history mới nhất
//                $latestHistory = ProductStorageHistory::where([
//                    'product_id' => $detail->product_id,
//                    'storage_area_id' => $detail->storage_area_id,
//                    'status' => 'ACTIVE',
//                    'deleted' => false
//                ])->first();
//
//                return [
//                    'id' => $detail->id,
//                    'product' => [
//                        'id' => $detail->product->id,
//                        'sku' => $detail->product->sku,
//                        'name' => $detail->product->name,
//                    ],
//                    'storage_area' => [
//                        'id' => $detail->storageArea->id,
//                        'name' => $detail->storageArea->name,
//                        'code' => $detail->storageArea->code,
//                    ],
//                    'quantity' => $detail->quantity,
//                    'expiry_date' => $detail->expiry_date,
//                    'created_at' => $detail->created_at,
//                    'history' => [
//                        'quantity_available' => $latestHistory ? $latestHistory->quantity_available : 0,
//                        'status' => $latestHistory ? $latestHistory->status : null
//                    ]
//                ];
//            });
//
//            header('Content-Type: application/json');
//            echo json_encode($response, JSON_UNESCAPED_UNICODE);
//
//        } catch (\Exception $e) {
//            header('Content-Type: application/json');
//            http_response_code(400);
//            echo json_encode([
//                'success' => false,
//                'message' => $e->getMessage()
//            ], JSON_UNESCAPED_UNICODE);
//        }
//    }

    public function exportProducts(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            // Validate basic required fields
            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'CANCEL'])) {
                throw new \Exception('Type must be NORMAL or CANCEL');
            }

            // Validate allowed fields
            $allowedFields = [
                'NORMAL' => ['type', 'order_code', 'note', 'products'],
                'CANCEL' => ['type', 'note', 'products']
            ];

            foreach ($data as $field => $value) {
                if (!in_array($field, $allowedFields[$data['type']])) {
                    throw new \Exception("Field '$field' is not allowed for type " . $data['type']);
                }
            }

            // Token validation
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            if (!$token) {
                throw new \Exception('Token is missing');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $createdById = $parsedToken->claims()->get('id');

            // Validate order if type is NORMAL
            $order = null;
            if ($data['type'] === 'NORMAL') {
                if (!isset($data['order_code'])) {
                    throw new \Exception('order_code is required for type NORMAL');
                }

                $order = Order::where('code', $data['order_code'])
                    ->where('deleted', false)
                    ->where('status', 'PENDING')
                    ->first();

                if (!$order) {
                    throw new \Exception("Order {$data['order_code']} does not exist");
                }

                $existingExport = ProductExportReceipt::where('order_code', $data['order_code'])
                    ->where('type', 'NORMAL')
                    ->where('status', 'COMPLETED')
                    ->where('deleted', false)
                    ->first();

                if ($existingExport) {
                    throw new \Exception(
                        "Order {$data['order_code']} has already been exported with receipt {$existingExport->code}"
                    );
                }
            }

            // Validate products
            if (!isset($data['products']) || empty($data['products'])) {
                throw new \Exception('Products list cannot be empty');
            }

            $validatedProducts = [];
            foreach ($data['products'] as $product) {
                if (!isset($product['product_history_id']) || !isset($product['quantity'])) {
                    throw new \Exception('product_history_id and quantity are required for each product');
                }

                if ($product['quantity'] <= 0) {
                    throw new \Exception("Quantity must be greater than 0");
                }

                $history = ProductStorageHistory::with('product')
                    ->where('id', $product['product_history_id'])
                    ->where('status', 'ACTIVE')
                    ->where('deleted', false)
                    ->first();

                if (!$history) {
                    throw new \Exception("History ID {$product['product_history_id']} not found or inactive");
                }

                if ($history->quantity_available < $product['quantity']) {
                    throw new \Exception(
                        "Insufficient quantity in stock for history ID {$product['product_history_id']}. " .
                        "Required: {$product['quantity']}, Available: {$history->quantity_available}"
                    );
                }

                // Validate with order if type is NORMAL
                if ($data['type'] === 'NORMAL') {
                    $orderDetail = $order->orderDetails()
                        ->where('product_id', $history->product_id)
                        ->where('deleted', false)
                        ->where('status', 'ACTIVE')
                        ->first();

                    if (!$orderDetail) {
                        throw new \Exception("Product {$history->product->name} is not in the order");
                    }

                    $currentExportQuantity = $orderDetail->exportQuantity ?? 0;
                    $newExportQuantity = $currentExportQuantity + $product['quantity'];

                    // Check if new exportQuantity exceeds the allowed quantity
                    if ($newExportQuantity > $orderDetail->quantity) {
                        throw new \Exception(
                            "Export quantity for product {$history->product->name} exceeds order quantity. " .
                            "Allowed: {$orderDetail->quantity}, Current exported: {$currentExportQuantity}, Attempting to export: {$product['quantity']}"
                        );
                    }
                }

                $validatedProducts[] = [
                    'history' => $history,
                    'quantity' => $product['quantity'],
                    'orderDetail' => isset($orderDetail) ? $orderDetail : null
                ];
            }

            // Generate receipt code
            $currentDay = date('d');
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "PXTP" . $currentDay . $currentMonth . $currentYear;

            $latestExportReceipt = ProductExportReceipt::where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            $sequence = $latestExportReceipt ? intval(substr($latestExportReceipt->code, -5)) + 1 : 1;
            $code = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);

            // Create export receipt
            $productExportReceipt = ProductExportReceipt::create([
                'code' => $code,
                'note' => $data['note'] ?? '',
                'type' => $data['type'],
                'status' => 'COMPLETED',
                'created_by' => $createdById,
                'order_code' => $data['type'] === 'NORMAL' ? $data['order_code'] : null
            ]);

            // Create details and update quantities
            foreach ($validatedProducts as $product) {
                $history = $product['history'];
                $quantity = $product['quantity'];
                $orderDetail = $product['orderDetail'];

                // Update available quantity in history
                $history->quantity_available -= $quantity;
                $history->save();

                // Create export receipt detail
                $productExportReceipt->details()->create([
                    'product_id' => $history->product_id,
                    'storage_area_id' => $history->storage_area_id,
                    'quantity' => $quantity,
                    'expiry_date' => $history->expiry_date
                ]);

                // Update product quantity
                $history->product->quantity_available -= $quantity;
                $history->product->save();

                // Update exportQuantity in order detail if type is NORMAL
                if ($data['type'] === 'NORMAL' && $orderDetail) {
                    $currentTimestamp = date('Y-m-d H:i:s');

                    // Use order_id and product_id for the update
                    DB::table('order_details')
                        ->where('order_id', $orderDetail->order_id)
                        ->where('product_id', $orderDetail->product_id)
                        ->where('deleted', false)
                        ->where('status', 'ACTIVE')
                        ->update([
                            'exportQuantity' => DB::raw('COALESCE(exportQuantity, 0) + ' . $quantity),
                            'updated_at' => $currentTimestamp
                        ]);
                }

                // Create product inventory history
                ProductStorageHistoryDetail::create([
                    'product_storage_history_id' => $history->id,
                    'quantity_before' => $history->quantity_available + $quantity,
                    'quantity_change' => -$quantity,
                    'quantity_after' => $history->quantity_available,
                    'action_type' => $data['type'] === 'NORMAL' ? 'EXPORT_NORMAL' : 'EXPORT_CANCEL',
                    'created_by' => $createdById
                ]);
            }

            // Update order status if type is NORMAL
            if ($data['type'] === 'NORMAL') {
                $allOrderDetails = $order->orderDetails()
                    ->where('deleted', false)
                    ->where('status', 'ACTIVE')
                    ->get();

                $allFullyExported = true;
                foreach ($allOrderDetails as $detail) {
                    $exportedQuantity = $detail->exportQuantity ?? 0;
                    if ($exportedQuantity < $detail->quantity) {
                        $allFullyExported = false;
                        break;
                    }
                }

                $order->status = $allFullyExported ? 'PROCESSED' : 'PARTIAL_SHIPPING';
                $order->save();
            }

            // Prepare response
            $exportReceipt = ProductExportReceipt::with([
                'details.product',
                'details.storageArea',
                'creator.profile'
            ])->find($productExportReceipt->id);

            $response = [
                'success' => true,
                'message' => 'Export successful',
                'data' => [
                    'id' => $exportReceipt->id,
                    'code' => $exportReceipt->code,
                    'type' => $exportReceipt->type,
                    'status' => $exportReceipt->status,
                    'note' => $exportReceipt->note,
                    'created_at' => $exportReceipt->created_at,
                    'creator' => [
                        'id' => $exportReceipt->creator->id,
                        'email' => $exportReceipt->creator->email,
                        'profile' => [
                            'id' => $exportReceipt->creator->profile->id,
                            'first_name' => $exportReceipt->creator->profile->first_name,
                            'last_name' => $exportReceipt->creator->profile->last_name,
                        ]
                    ]
                ]
            ];

            // Add order information if type is NORMAL
            if ($data['type'] === 'NORMAL' && $order) {
                $response['data']['order'] = [
                    'code' => $order->code,
                    'created_at' => $order->created_at,
                    'customer' => [
                        'id' => $order->customer_id,
                        'name' => $order->customer->name,
                        'code' => $order->customer->code
                    ]
                ];
            }

            // Add export receipt details
            $response['data']['details'] = $exportReceipt->details->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'product' => [
                        'id' => $detail->product->id,
                        'sku' => $detail->product->sku,
                        'name' => $detail->product->name,
                    ],
                    'storage_area' => [
                        'id' => $detail->storageArea->id,
                        'name' => $detail->storageArea->name,
                        'code' => $detail->storageArea->code,
                    ],
                    'quantity' => $detail->quantity,
                    'expiry_date' => $detail->expiry_date,
                    'created_at' => $detail->created_at
                ];
            });

            header('Content-Type: application/json');
            echo json_encode($response, JSON_UNESCAPED_UNICODE);

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