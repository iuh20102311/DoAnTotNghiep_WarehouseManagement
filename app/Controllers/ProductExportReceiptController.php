<?php

namespace App\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductExportReceipt;
use App\Models\ProductStorageHistory;
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
                    'details.productStorageLocation',
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

                $orderDetails = $order->orderDetails()
                    ->where('deleted', false)
                    ->get()
                    ->keyBy('product_id');

                if ($orderDetails->isEmpty()) {
                    throw new \Exception('Đơn hàng không có sản phẩm');
                }

                // Validate và map quantity từ order_details
                $productsWithQuantity = [];
                foreach ($data['products'] as $product) {
                    if (!isset($product['product_id']) || !isset($product['product_storage_location_id'])) {
                        throw new \Exception('product_id và product_storage_location_id là bắt buộc cho mỗi sản phẩm');
                    }

                    // Kiểm tra sản phẩm có trong order không
                    if (!$orderDetails->has($product['product_id'])) {
                        throw new \Exception("Sản phẩm ID {$product['product_id']} không có trong đơn hàng");
                    }

                    // Kiểm tra location
                    $location = ProductStorageHistory::where('id', $product['product_storage_location_id'])
                        ->where('product_id', $product['product_id'])
                        ->where('deleted', false)
                        ->first();

                    if (!$location) {
                        throw new \Exception("Sản phẩm ID {$product['product_id']} không có trong vị trí lưu trữ đã chọn");
                    }

                    $orderDetail = $orderDetails[$product['product_id']];
                    if ($location->quantity < $orderDetail->quantity) {
                        throw new \Exception("Vị trí lưu trữ không đủ số lượng cho sản phẩm ID {$product['product_id']}. Cần: {$orderDetail->quantity}, Có sẵn: {$location->quantity}");
                    }

                    // Thêm quantity từ order_detail
                    $productsWithQuantity[] = [
                        'product_id' => $product['product_id'],
                        'product_storage_location_id' => $product['product_storage_location_id'],
                        'quantity' => $orderDetail->quantity
                    ];
                }

                $data['products'] = $productsWithQuantity;
            }

            // Validate products
            if (!isset($data['products']) || empty($data['products'])) {
                throw new \Exception('Danh sách sản phẩm không được để trống');
            }

            // Check product availability
            $invalidProducts = [];
            foreach ($data['products'] as $product) {
                if (!isset($product['product_id']) || !isset($product['quantity']) || !isset($product['product_storage_location_id'])) {
                    throw new \Exception('product_id, quantity và product_storage_location_id là bắt buộc cho mỗi sản phẩm');
                }

                $productLocation = ProductStorageHistory::where('id', $product['product_storage_location_id'])
                    ->where('product_id', $product['product_id'])
                    ->where('deleted', false)
                    ->first();

                if (!$productLocation) {
                    $productInfo = Product::find($product['product_id']);
                    $productName = $productInfo ? $productInfo->name : "Sản phẩm không xác định";
                    $invalidProducts[] = "{$productName} (ID: {$product['product_id']}) không tồn tại trong vị trí lưu trữ đã chọn";
                } elseif ($productLocation->quantity < $product['quantity']) {
                    $productInfo = Product::find($product['product_id']);
                    $productName = $productInfo ? $productInfo->name : "Sản phẩm không xác định";
                    $invalidProducts[] = "{$productName} (ID: {$product['product_id']}) không đủ số lượng trong kho. Có sẵn: {$productLocation->quantity}, Yêu cầu: {$product['quantity']}";
                }
            }

            if (!empty($invalidProducts)) {
                throw new \Exception("Xuất kho thất bại. " . implode(". ", $invalidProducts));
            }

            // Tạo mã phiếu xuất tự động
            $currentDay = date('d');
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "PXTP" . $currentDay . $currentMonth . $currentYear;

            // Lấy phiếu xuất mới nhất với prefix hiện tại
            $latestExportReceipt = ProductExportReceipt::query()
                ->where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            if ($latestExportReceipt) {
                // Lấy số thứ tự và tăng lên 1
                $sequence = intval(substr($latestExportReceipt->code, -5)) + 1;
            } else {
                $sequence = 1;
            }

            // Định dạng số thứ tự thành 5 chữ số
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
            foreach ($data['products'] as $product) {
                $productLocation = ProductStorageHistory::find($product['product_storage_location_id']);

                // Create export detail
                $productExportReceipt->details()->create([
                    'product_id' => $product['product_id'],
                    'product_storage_location_id' => $product['product_storage_location_id'],
                    'quantity' => $product['quantity']
                ]);

                // Update storage location quantity
                $productLocation->quantity -= $product['quantity'];
                $productLocation->save();

                // Update product quantity
                $productModel = Product::find($product['product_id']);
                $productModel->quantity_available -= $product['quantity'];
                $productModel->save();
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Xuất kho thành công',
                'data' => [
                    'product_export_receipt_id' => $productExportReceipt->id,
                    'code' => $productExportReceipt->code,
                    'type' => $productExportReceipt->type,
                    'status' => $productExportReceipt->status,
                    'details' => $productExportReceipt->details()
                        ->with(['product:id,name', 'productStorageLocation:id,storage_area_id'])
                        ->get()
                        ->map(function($detail) {
                            return [
                                'product_id' => $detail->product_id,
                                'product_name' => $detail->product->name,
                                'quantity' => $detail->quantity,
                                'storage_location' => $detail->productStorageLocation
                            ];
                        })
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