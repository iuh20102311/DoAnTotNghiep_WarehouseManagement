<?php

namespace App\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductExportReceipt;
use App\Models\ProductImportReceipt;
use App\Models\ProductImportReceiptDetail;
use App\Models\ProductStorageHistory;
use App\Models\StorageArea;
use App\Models\User;
use App\Utils\PaginationTrait;
use Illuminate\Support\Facades\DB;
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

    public function importProducts(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $exportReceipt = null;

        try {
            // [BƯỚC 1] - Validate basic required fields
            if (!isset($data['type']) || !in_array($data['type'], ['NORMAL', 'RETURN'])) {
                throw new \Exception('Type phải là NORMAL hoặc RETURN');
            }

            if (!isset($data['storage_area_id'])) {
                throw new \Exception('storage_area_id là bắt buộc');
            }

            if (!isset($data['receiver_id'])) {
                throw new \Exception('receiver_id là bắt buộc');
            }

            // [BƯỚC 2] - Validate allowed fields
            $allowedFields = [
                'NORMAL' => ['type', 'storage_area_id', 'receiver_id', 'note', 'products'],
                'RETURN' => ['type', 'storage_area_id', 'receiver_id', 'note', 'order_code', 'export_receipt_id', 'products']
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

            // [BƯỚC 4] - Validate storage area
            $storageArea = StorageArea::where('id', $data['storage_area_id'])
                ->where('deleted', false)
                ->first();

            if (!$storageArea) {
                throw new \Exception('Khu vực lưu trữ không tồn tại hoặc không hoạt động');
            }

            // [BƯỚC 5] - Validate receiver
            $receiver = User::where('id', $data['receiver_id'])
                ->where('status', 'ACTIVE')
                ->where('deleted', false)
                ->first();

            if (!$receiver) {
                throw new \Exception('Người nhận không tồn tại hoặc không hoạt động');
            }

            // [BƯỚC 6] - Validate based on type
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

                $exportDetails = $exportReceipt->details()
                    ->where('deleted', false)
                    ->get()
                    ->keyBy('product_id');

                if ($exportDetails->isEmpty()) {
                    throw new \Exception('Phiếu xuất không có sản phẩm');
                }
            }

            // [BƯỚC 7] - Validate products array
            if (!isset($data['products']) || empty($data['products'])) {
                throw new \Exception('Danh sách products không được để trống');
            }

            // [BƯỚC 8] - Validate products và chuẩn bị dữ liệu
            $validatedProducts = [];
            foreach ($data['products'] as $product) {
                if (!isset($product['product_id']) || !isset($product['quantity'])) {
                    throw new \Exception('product_id và quantity là bắt buộc cho mỗi sản phẩm');
                }

                $productModel = Product::find($product['product_id']);
                if (!$productModel) {
                    throw new \Exception("Sản phẩm (ID: {$product['product_id']}) không tồn tại");
                }

                if ($data['type'] === 'NORMAL') {
                    if (!isset($product['expiry_date'])) {
                        throw new \Exception('expiry_date là bắt buộc với type NORMAL');
                    }

                    if (!strtotime($product['expiry_date']) || strtotime($product['expiry_date']) <= time()) {
                        throw new \Exception('expiry_date phải là ngày trong tương lai và đúng định dạng');
                    }

                    if ($product['quantity'] <= 0) {
                        throw new \Exception("Số lượng nhập của {$productModel->name} phải lớn hơn 0");
                    }

                    $product['storage_area_id'] = $data['storage_area_id'];

                } else { // RETURN type
                    $exportDetail = $exportDetails->get($product['product_id']);
                    if (!$exportDetail) {
                        throw new \Exception("Sản phẩm {$productModel->name} không có trong phiếu xuất");
                    }

                    if ($product['quantity'] > $exportDetail->quantity) {
                        throw new \Exception(
                            "Số lượng trả về của {$productModel->name} ({$product['quantity']}) " .
                            "không được lớn hơn số lượng đã xuất ({$exportDetail->quantity})"
                        );
                    }

                    if ($product['quantity'] <= 0) {
                        throw new \Exception("Số lượng trả về của {$productModel->name} phải lớn hơn 0");
                    }

                    $product['storage_area_id'] = $exportDetail->storage_area_id;
                    $product['expiry_date'] = $exportDetail->expiry_date;
                }

                $validatedProducts[] = $product;
            }

            // [BƯỚC 9] - Generate receipt code
            $currentDay = date('d');
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "PNTP" . $currentDay . $currentMonth . $currentYear;

            $latestImportReceipt = ProductImportReceipt::where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            $sequence = $latestImportReceipt ? intval(substr($latestImportReceipt->code, -5)) + 1 : 1;
            $code = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);

            // [BƯỚC 10] - Create import receipt
            $importReceipt = ProductImportReceipt::create([
                'code' => $code,
                'type' => $data['type'],
                'note' => $data['note'] ?? null,
                'created_by' => $createdById,
                'receiver_id' => $receiver->id,
                'status' => 'COMPLETED',
                'order_code' => $data['type'] === 'RETURN' ? ($exportReceipt->order_code ?? null) : null
            ]);

            // [BƯỚC 11] - Create import details and update inventory
            $importDetailIds = [];
            foreach ($validatedProducts as $product) {
                // Create import detail
                $detail = $importReceipt->details()->create([
                    'product_id' => $product['product_id'],
                    'storage_area_id' => $product['storage_area_id'],
                    'quantity' => $product['quantity'],
                    'expiry_date' => $product['expiry_date']
                ]);

                $importDetailIds[] = $detail->id;

                // Update storage history
                $storageHistory = ProductStorageHistory::where([
                    'product_id' => $product['product_id'],
                    'storage_area_id' => $product['storage_area_id'],
                    'expiry_date' => $product['expiry_date'],
                    'deleted' => false
                ])->first();

                if ($storageHistory) {
                    $storageHistory->quantity += $product['quantity'];
                    $storageHistory->save();
                } else {
                    ProductStorageHistory::create([
                        'product_id' => $product['product_id'],
                        'storage_area_id' => $product['storage_area_id'],
                        'expiry_date' => $product['expiry_date'],
                        'quantity' => $product['quantity'],
                        'deleted' => false
                    ]);
                }

                // Update product quantity
                $productModel = Product::find($product['product_id']);
                $productModel->quantity_available += $product['quantity'];
                $productModel->save();
            }

            // [BƯỚC 12] - Load relationships for response
            $importReceipt->load([
                'details' => function($query) use ($importDetailIds) {
                    $query->whereIn('id', $importDetailIds);
                },
                'details.product',
                'details.storageArea',
                'creator.profile',
                'receiver.profile'
            ]);

            // [BƯỚC 13] - Prepare and send response
            $response = [
                'message' => 'Nhập kho thành công',
                'data' => [
                    'id' => $importReceipt->id,
                    'code' => $importReceipt->code,
                    'type' => $importReceipt->type,
                    'status' => $importReceipt->status,
                    'note' => $importReceipt->note,
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
                    'details' => $importReceipt->details->map(function ($detail) {
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
                    })->values()->toArray()
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