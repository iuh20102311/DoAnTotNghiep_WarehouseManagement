<?php

namespace App\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductExportReceipt;
use App\Models\ProductStorageHistory;
use App\Models\StorageArea;
use App\Utils\PaginationTrait;
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
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
                ->orderBy('created_at', 'desc');

            if (isset($_GET['note'])) {
                $note = urldecode($_GET['note']);
                $productER->where('note', '%' . $note . '%');
            }

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

    public function exportProducts(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            // Basic validation
            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'CANCEL'])) {
                throw new \Exception('Type phải là NORMAL hoặc CANCEL');
            }

            // Allowed fields based on type
            $allowedFields = [
                'NORMAL' => ['type', 'order_code', 'note', 'products'],
                'CANCEL' => ['type', 'note', 'products']
            ];

            // Check unexpected fields
            foreach ($data as $field => $value) {
                if (!in_array($field, $allowedFields[$data['type']])) {
                    throw new \Exception("Trường '$field' không được phép với type " . $data['type']);
                }
            }

            // Token validation
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            if (!$token) {
                throw new \Exception('Token không tồn tại');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $createdById = $parsedToken->claims()->get('id');

            // Type specific validation
            $order = null;
            if ($data['type'] === 'NORMAL') {
                if (!isset($data['order_code'])) {
                    throw new \Exception('order_code là bắt buộc với type NORMAL');
                }

                $order = Order::where('code', $data['order_code'])
                    ->where('deleted', false)
                    ->where('status', 'PROCESSED')
                    ->first();

                if (!$order) {
                    throw new \Exception("Đơn hàng {$data['order_code']} không tồn tại hoặc chưa được xử lý");
                }

                // Kiểm tra đơn hàng đã xuất kho chưa
                $existingExport = ProductExportReceipt::where('order_code', $data['order_code'])
                    ->where('type', 'NORMAL')
                    ->where('status', 'COMPLETED')
                    ->where('deleted', false)
                    ->first();

                if ($existingExport) {
                    throw new \Exception(
                        "Đơn hàng {$data['order_code']} đã được xuất kho theo phiếu xuất {$existingExport->code} " .
                        "vào lúc " . $existingExport->created_at->format('H:i:s d/m/Y')
                    );
                }

                // Lấy chi tiết đơn hàng từ order_details
                $orderDetails = $order->orderDetails()
                    ->where('deleted', false)
                    ->where('status', 'ACTIVE')
                    ->get()
                    ->keyBy('product_id');

                if ($orderDetails->isEmpty()) {
                    throw new \Exception('Đơn hàng không có sản phẩm');
                }

                // Xử lý products dựa vào 3 trường hợp
                $productsWithQuantity = [];

                // TH1: Có truyền products đầy đủ
                if (isset($data['products']) && !empty($data['products'])) {
                    foreach ($data['products'] as $product) {
                        if (!isset($product['product_id'])) {
                            throw new \Exception('product_id là bắt buộc cho mỗi sản phẩm');
                        }

                        // Kiểm tra sản phẩm có trong đơn hàng không
                        if (!$orderDetails->has($product['product_id'])) {
                            throw new \Exception("Sản phẩm ID {$product['product_id']} không có trong đơn hàng");
                        }

                        $orderDetail = $orderDetails[$product['product_id']];

                        // Kiểm tra và lấy số lượng xuất
                        $requestQuantity = $product['quantity'] ?? $orderDetail->quantity;

                        // Kiểm tra số lượng xuất phải lớn hơn 0
                        if ($requestQuantity <= 0) {
                            throw new \Exception("Số lượng xuất của sản phẩm ID {$product['product_id']} phải lớn hơn 0");
                        }

                        // Kiểm tra số lượng xuất không vượt quá số lượng đơn hàng
                        if ($requestQuantity > $orderDetail->quantity) {
                            throw new \Exception(
                                "Số lượng xuất ({$requestQuantity}) của sản phẩm ID {$product['product_id']} " .
                                "vượt quá số lượng trong đơn hàng ({$orderDetail->quantity})"
                            );
                        }

                        // Sử dụng storage_area_id từ product hoặc từ data chung
                        $storageAreaId = $product['storage_area_id'] ?? $data['storage_area_id'] ?? null;
                        if (!$storageAreaId) {
                            throw new \Exception("Thiếu storage_area_id cho sản phẩm ID {$product['product_id']}");
                        }

                        // Kiểm tra storage_area tồn tại
                        if (!isset($data['storage_area_id']) || $storageAreaId !== $data['storage_area_id']) {
                            $storageArea = StorageArea::where('id', $storageAreaId)
                                ->where('deleted', false)
                                ->first();

                            if (!$storageArea) {
                                throw new \Exception("Kho chứa ID {$storageAreaId} không tồn tại hoặc đã bị xóa");
                            }
                        }

                        // Kiểm tra số lượng trong kho
                        $totalAvailable = ProductStorageHistory::where('product_id', $product['product_id'])
                            ->where('storage_area_id', $storageAreaId)
                            ->where('deleted', false)
                            ->sum('quantity');

                        if ($totalAvailable < $requestQuantity) {
                            throw new \Exception(
                                "Không đủ số lượng trong kho cho sản phẩm ID {$product['product_id']}. " .
                                "Cần: {$requestQuantity}, Có sẵn: {$totalAvailable}"
                            );
                        }

                        $productsWithQuantity[] = [
                            'product_id' => $product['product_id'],
                            'storage_area_id' => $storageAreaId,
                            'quantity' => $requestQuantity
                        ];
                    }
                }
                // TH2: Chỉ truyền mảng product_ids và quantities
                else if (isset($data['product_ids']) && is_array($data['product_ids'])) {
                    if (!isset($data['storage_area_id'])) {
                        throw new \Exception('storage_area_id là bắt buộc khi chỉ định product_ids');
                    }

                    // Kiểm tra mảng quantities nếu có
                    $quantities = $data['quantities'] ?? [];
                    if (!empty($quantities) && count($quantities) !== count($data['product_ids'])) {
                        throw new \Exception('Số lượng phần tử trong mảng quantities phải bằng số lượng product_ids');
                    }

                    foreach ($data['product_ids'] as $index => $productId) {
                        if (!$orderDetails->has($productId)) {
                            throw new \Exception("Sản phẩm ID {$productId} không có trong đơn hàng");
                        }

                        $orderDetail = $orderDetails[$productId];
                        $requestQuantity = $quantities[$index] ?? $orderDetail->quantity;

                        // Kiểm tra số lượng xuất phải lớn hơn 0
                        if ($requestQuantity <= 0) {
                            throw new \Exception("Số lượng xuất của sản phẩm ID {$productId} phải lớn hơn 0");
                        }

                        // Kiểm tra số lượng xuất không vượt quá số lượng đơn hàng
                        if ($requestQuantity > $orderDetail->quantity) {
                            throw new \Exception(
                                "Số lượng xuất ({$requestQuantity}) của sản phẩm ID {$productId} " .
                                "vượt quá số lượng trong đơn hàng ({$orderDetail->quantity})"
                            );
                        }

                        // Kiểm tra số lượng trong kho
                        $totalAvailable = ProductStorageHistory::where('product_id', $productId)
                            ->where('storage_area_id', $data['storage_area_id'])
                            ->where('deleted', false)
                            ->sum('quantity');

                        if ($totalAvailable < $requestQuantity) {
                            throw new \Exception(
                                "Không đủ số lượng trong kho cho sản phẩm ID {$productId}. " .
                                "Cần: {$requestQuantity}, Có sẵn: {$totalAvailable}"
                            );
                        }

                        $productsWithQuantity[] = [
                            'product_id' => $productId,
                            'storage_area_id' => $data['storage_area_id'],
                            'quantity' => $requestQuantity
                        ];
                    }
                }
                // TH3: Không truyền products, lấy tất cả từ đơn hàng
                else {
                    if (!isset($data['storage_area_id'])) {
                        throw new \Exception('storage_area_id là bắt buộc khi không chỉ định products');
                    }

                    foreach ($orderDetails as $orderDetail) {
                        // Kiểm tra số lượng trong kho
                        $totalAvailable = ProductStorageHistory::where('product_id', $orderDetail->product_id)
                            ->where('storage_area_id', $data['storage_area_id'])
                            ->where('deleted', false)
                            ->sum('quantity');

                        if ($totalAvailable < $orderDetail->quantity) {
                            throw new \Exception(
                                "Không đủ số lượng trong kho cho sản phẩm ID {$orderDetail->product_id}. " .
                                "Cần: {$orderDetail->quantity}, Có sẵn: {$totalAvailable}"
                            );
                        }

                        $productsWithQuantity[] = [
                            'product_id' => $orderDetail->product_id,
                            'storage_area_id' => $data['storage_area_id'],
                            'quantity' => $orderDetail->quantity
                        ];
                    }
                }

                $data['products'] = $productsWithQuantity;
            }

            // Validate products cho CANCEL type
            if ($data['type'] === 'CANCEL') {
                if (!isset($data['products']) || empty($data['products'])) {
                    throw new \Exception('Danh sách sản phẩm không được để trống');
                }

                foreach ($data['products'] as $product) {
                    if (!isset($product['product_id']) || !isset($product['quantity']) || !isset($product['storage_area_id'])) {
                        throw new \Exception('product_id, quantity và storage_area_id là bắt buộc cho mỗi sản phẩm');
                    }

                    // Kiểm tra storage_area tồn tại
                    $storageArea = StorageArea::where('id', $product['storage_area_id'])
                        ->where('deleted', false)
                        ->first();

                    if (!$storageArea) {
                        throw new \Exception("Kho chứa ID {$product['storage_area_id']} không tồn tại hoặc đã bị xóa");
                    }

                    // Lấy tổng số lượng có sẵn trong kho
                    $totalAvailable = ProductStorageHistory::where('product_id', $product['product_id'])
                        ->where('storage_area_id', $product['storage_area_id'])
                        ->where('deleted', false)
                        ->sum('quantity');

                    if ($totalAvailable < $product['quantity']) {
                        $productInfo = Product::find($product['product_id']);
                        $productName = $productInfo ? $productInfo->name : "Sản phẩm không xác định";
                        throw new \Exception("{$productName} không đủ số lượng trong kho. Có sẵn: {$totalAvailable}, Yêu cầu: {$product['quantity']}");
                    }
                }
            }

            // Tạo mã phiếu xuất tự động
            $currentDay = date('d');
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "PXTP" . $currentDay . $currentMonth . $currentYear;

            $latestExportReceipt = ProductExportReceipt::query()
                ->where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            if ($latestExportReceipt) {
                $sequence = intval(substr($latestExportReceipt->code, -5)) + 1;
            } else {
                $sequence = 1;
            }

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

            // Create details and update quantities using FIFO
            foreach ($data['products'] as $product) {
                $remainingQuantity = $product['quantity'];

                // Lấy các lô trong kho, sắp xếp theo hạn sử dụng gần nhất
                $storageBatches = ProductStorageHistory::where('product_id', $product['product_id'])
                    ->where('storage_area_id', $product['storage_area_id'])
                    ->where('deleted', false)
                    ->where('quantity', '>', 0)
                    ->orderBy('expiry_date', 'asc')
                    ->get();

                foreach ($storageBatches as $batch) {
                    if ($remainingQuantity <= 0) break;

                    $quantityFromBatch = min($remainingQuantity, $batch->quantity);

                    // Tạo chi tiết xuất kho cho từng lô
                    $productExportReceipt->details()->create([
                        'product_id' => $product['product_id'],
                        'storage_area_id' => $product['storage_area_id'],
                        'quantity' => $quantityFromBatch,
                        'expiry_date' => $batch->expiry_date
                    ]);

                    // Cập nhật số lượng trong batch
                    $batch->quantity -= $quantityFromBatch;
                    $batch->save();

                    $remainingQuantity -= $quantityFromBatch;
                }

                // Cập nhật tổng số lượng trong bảng products
                $productModel = Product::find($product['product_id']);
                $productModel->quantity_available -= $product['quantity'];
                $productModel->save();
            }

            // Load relationships cho response
            $exportReceipt = ProductExportReceipt::with([
                'details.product',
                'details.storageArea',
                'creator.profile'
            ])->find($productExportReceipt->id);

            // Chuẩn bị response
            $response = [
                'success' => true,
                'message' => 'Xuất kho thành công',
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

            // Thêm thông tin đơn hàng nếu là type NORMAL
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

            // Thêm chi tiết xuất kho
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