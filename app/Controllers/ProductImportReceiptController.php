<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\ProductExportReceipt;
use App\Models\ProductImportReceipt;
use App\Models\ProductStorageHistory;
use App\Models\StorageArea;
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
                    'details',
                    'order'
                ])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")
                ->orderBy('created_at', 'desc');

            // Code filter
            if (isset($_GET['code'])) {
                $code = urldecode($_GET['code']);
                $query->where('code', 'LIKE', '%' . $code . '%');
            }

            // Creator filter
            if (isset($_GET['created_by'])) {
                $createdBy = urldecode($_GET['created_by']);
                $query->where('created_by', $createdBy);
            }

            // Receiver filter
            if (isset($_GET['receiver_id'])) {
                $receiverId = urldecode($_GET['receiver_id']);
                $query->where('receiver_id', $receiverId);
            }

            // Order filter
            if (isset($_GET['order_id'])) {
                $orderId = urldecode($_GET['order_id']);
                $query->where('order_id', $orderId);
            }

            // Receipt Date filters
            if (isset($_GET['receipt_date'])) {
                $receiptDate = urldecode($_GET['receipt_date']);
                $query->whereDate('receipt_date', $receiptDate);
            }
            if (isset($_GET['receipt_date_from'])) {
                $receiptDateFrom = urldecode($_GET['receipt_date_from']);
                $query->whereDate('receipt_date', '>=', $receiptDateFrom);
            }
            if (isset($_GET['receipt_date_to'])) {
                $receiptDateTo = urldecode($_GET['receipt_date_to']);
                $query->whereDate('receipt_date', '<=', $receiptDateTo);
            }

            // Type filter
            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                $query->where('type', $type);
            }

            // Status filter
            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $query->where('status', $status);
            }

            // Note filter
            if (isset($_GET['note'])) {
                $note = urldecode($_GET['note']);
                $query->where('note', 'LIKE', '%' . $note . '%');
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
                    'details.storageArea',
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

    public function importProducts(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $exportReceipt = null;

        try {
            // [BƯỚC 1] - Validate basic required fields
            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'RETURN'])) {
                throw new \Exception('Type phải là NORMAL hoặc RETURN');
            }

            if (!isset($data['receiver_id'])) {
                throw new \Exception('receiver_id là bắt buộc');
            }

            // [BƯỚC 2] - Validate allowed fields
            $allowedFields = [
                'NORMAL' => ['type', 'receiver_id', 'note', 'products'],
                'RETURN' => ['type', 'receiver_id', 'note', 'order_id', 'export_receipt_id', 'products']
            ];

            foreach ($data as $field => $value) {
                if (!in_array($field, $allowedFields[$data['type']])) {
                    throw new \Exception("Trường '$field' không được phép với type " . $data['type']);
                }
            }

            // [BƯỚC 3] - Validate JWT token
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

            if (!$token) {
                throw new \Exception('Token không tồn tại');
            }

            $parser = new Parser(new JoseEncoder());
            $parsedToken = $parser->parse($token);
            $createdById = $parsedToken->claims()->get('id');

            // [BƯỚC 4] - Validate receiver
            $receiver = User::where('id', $data['receiver_id'])
                ->where('status', 'ACTIVE')
                ->where('deleted', false)
                ->first();

            if (!$receiver) {
                throw new \Exception('Người nhận không tồn tại hoặc không hoạt động');
            }

            // [BƯỚC 5] - Validate based on type
            if ($data['type'] === 'RETURN') {
                if (!isset($data['export_receipt_id'])) {
                    throw new \Exception('export_receipt_id là bắt buộc với type RETURN');
                }

                $exportReceipt = ProductExportReceipt::with(['details.product', 'details.storageArea'])
                    ->where('id', $data['export_receipt_id'])
                    ->where('type', 'NORMAL')
                    ->where('status', 'COMPLETED')
                    ->where('deleted', false)
                    ->first();

                if (!$exportReceipt) {
                    throw new \Exception('Phiếu xuất không tồn tại hoặc không hợp lệ');
                }
            }

            // [BƯỚC 6] - Validate products array
            if (!isset($data['products']) || empty($data['products'])) {
                throw new \Exception('Danh sách products không được để trống');
            }

            // [BƯỚC 7] - Validate products và chuẩn bị dữ liệu
            $validatedProducts = [];
            foreach ($data['products'] as $product) {
                if (!isset($product['product_id']) || !isset($product['quantity']) ||
                    !isset($product['storage_area_id']) || !isset($product['expiry_date'])) {
                    throw new \Exception('product_id, quantity, storage_area_id và expiry_date là bắt buộc cho mỗi sản phẩm');
                }

                if ($product['quantity'] <= 0) {
                    throw new \Exception("Số lượng nhập phải lớn hơn 0");
                }

                $productModel = Product::find($product['product_id']);
                if (!$productModel) {
                    throw new \Exception("Sản phẩm không tồn tại");
                }

                $storageArea = StorageArea::where('id', $product['storage_area_id'])
                    ->where('deleted', false)
                    ->first();

                if (!$storageArea) {
                    throw new \Exception('Khu vực lưu trữ không tồn tại hoặc không hoạt động');
                }

                if (!strtotime($product['expiry_date']) || strtotime($product['expiry_date']) <= time()) {
                    throw new \Exception('expiry_date phải là ngày trong tương lai và đúng định dạng');
                }

                $validatedProducts[] = $product;
            }

            // [BƯỚC 8] - Generate receipt code
            $currentDay = date('d');
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "PNTP" . $currentDay . $currentMonth . $currentYear;

            $latestImportReceipt = ProductImportReceipt::where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            $sequence = $latestImportReceipt ? intval(substr($latestImportReceipt->code, -5)) + 1 : 1;
            $code = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);

            // [BƯỚC 9] - Create import receipt
            $importReceipt = ProductImportReceipt::create([
                'code' => $code,
                'type' => $data['type'],
                'note' => $data['note'] ?? null,
                'created_by' => $createdById,
                'receiver_id' => $receiver->id,
                'status' => 'COMPLETED',
                'order_id' => $data['order_id'] ?? null
            ]);


            // [BƯỚC 10] - Create import details and update inventory
            $importDetails = []; // Mảng lưu chi tiết import và history tương ứng

            foreach ($validatedProducts as $product) {
                // Tạo import detail
                $detail = $importReceipt->details()->create([
                    'product_id' => $product['product_id'],
                    'storage_area_id' => $product['storage_area_id'],
                    'quantity' => $product['quantity'],
                    'expiry_date' => $product['expiry_date']
                ]);

                // Kiểm tra history cũ
                $previousActiveRecord = ProductStorageHistory::where([
                    'product_id' => $product['product_id'],
                    'storage_area_id' => $product['storage_area_id'],
                    'expiry_date' => $product['expiry_date'],
                    'status' => 'ACTIVE',
                    'deleted' => false
                ])->first();

                // Tính toán quantity_available mới
                $newQuantityAvailable = $product['quantity'];
                if ($previousActiveRecord) {
                    $newQuantityAvailable += $previousActiveRecord->quantity_available;
                    $previousActiveRecord->status = 'INACTIVE';
                    $previousActiveRecord->save();
                }

                // Tạo history mới
                $historyRecord = new ProductStorageHistory();
                $historyRecord->product_id = $product['product_id'];
                $historyRecord->storage_area_id = $product['storage_area_id'];
                $historyRecord->expiry_date = $product['expiry_date'];
                $historyRecord->quantity = $product['quantity'];
                $historyRecord->quantity_available = $newQuantityAvailable;
                $historyRecord->status = 'ACTIVE';
                $historyRecord->deleted = false;
                $historyRecord->save();

                // Lưu detail và history vào mảng
                $importDetails[] = [
                    'detail' => $detail,
                    'history' => $historyRecord
                ];

                // Update product quantity
                $productModel = Product::find($product['product_id']);
                $productModel->quantity_available += $product['quantity'];
                $productModel->save();
            }

            // [BƯỚC 11] - Load relationships for response
            $importReceipt->load([
                'details.product',
                'details.storageArea',
                'creator.profile',
                'receiver.profile',
                'order'
            ]);

            // [BƯỚC 12] - Prepare and send response
            $response = [
                'message' => 'Nhập kho thành công',
                'data' => [
                    'id' => $importReceipt->id,
                    'code' => $importReceipt->code,
                    'type' => $importReceipt->type,
                    'status' => $importReceipt->status,
                    'note' => $importReceipt->note,
                    'order' => $importReceipt->order ? [
                        'id' => $importReceipt->order->id,
                        'code' => $importReceipt->order->code
                    ] : null,
                    'created_at' => $importReceipt->created_at,
                    'creator' => [
                        'id' => $importReceipt->creator->id,
                        'email' => $importReceipt->creator->email,
                        'profile' => [
                            'id' => $importReceipt->creator->profile->id,
                            'first_name' => $importReceipt->creator->profile->first_name,
                            'last_name' => $importReceipt->creator->profile->last_name,
                        ]
                    ],
                    'receiver' => [
                        'id' => $importReceipt->receiver->id,
                        'email' => $importReceipt->receiver->email,
                        'profile' => [
                            'id' => $importReceipt->receiver->profile->id,
                            'first_name' => $importReceipt->receiver->profile->first_name,
                            'last_name' => $importReceipt->receiver->profile->last_name,
                        ]
                    ],
                    'details' => array_map(function ($item) {
                        $detail = $item['detail'];
                        $history = $item['history'];

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
                            'created_at' => $detail->created_at,
                            'history' => [
                                'quantity_available' => $history->quantity_available,
                                'status' => $history->status
                            ]
                        ];
                    }, $importDetails)
                ]
            ];

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