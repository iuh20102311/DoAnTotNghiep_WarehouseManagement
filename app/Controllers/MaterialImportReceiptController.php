<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialImportReceipt;
use App\Models\MaterialStorageLocation;
use App\Models\Provider;
use App\Models\StorageArea;
use App\Models\User;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class MaterialImportReceiptController
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

            $totalReceipts = (new MaterialImportReceipt())
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

    public function getMaterialImportReceipts(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialIR = (new MaterialImportReceipt())
                ->where('deleted', false)
                ->with([
                    'provider',
                    'creator' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'approver' => function ($query) {
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
                WHEN status = 'COMPLETED' THEN 1 
                WHEN status = 'PENDING_APPROVED' THEN 2
                WHEN status = 'APPROVED' THEN 3 
                WHEN status = 'REJECTED' THEN 4 
                END")
                ->orderByRaw("CASE 
                WHEN status = 'RETURN' THEN 1 
                WHEN status = 'NORMAL' THEN 2
                WHEN status = 'OTHER' THEN 3 
                END")
                ->orderBy('created_at', 'desc');

            if (isset($_GET['provider_id'])) {
                $providerId = urldecode($_GET['provider_id']);
                $materialIR->where('provider_id', $providerId);
            }

            if (isset($_GET['receipt_id'])) {
                $receipt_id = urldecode($_GET['receipt_id']);
                $materialIR->where('receipt_id', 'like', '%' . $receipt_id . '%');
            }

            if (isset($_GET['type'])) {
                $materialIR->where('type', urldecode($_GET['type']));
            }

            if (isset($_GET['status'])) {
                $materialIR->where('status', urldecode($_GET['status']));
            }

            if (isset($_GET['total_price'])) {
                $materialIR->where('total_price', urldecode($_GET['total_price']));
            }

            if (isset($_GET['total_price_min'])) {
                $materialIR->where('total_price', '>=', urldecode($_GET['total_price_min']));
            }

            if (isset($_GET['total_price_max'])) {
                $materialIR->where('total_price', '<=', urldecode($_GET['total_price_max']));
            }

            if (isset($_GET['note'])) {
                $note = urldecode($_GET['note']);
                $materialIR->where('note', '%' . $note . '%');
            }

            $result = $this->paginateResults($materialIR, $perPage, $page)->toArray();

            if (isset($result['data']) && is_array($result['data'])) {
                foreach ($result['data'] as &$item) {
                    if (isset($item['creator']['profile'])) {
                        $item['creator']['full_name'] = trim($item['creator']['profile']['first_name'] . ' ' . $item['creator']['profile']['last_name']);
                    }
                    if (isset($item['approver']['profile'])) {
                        $item['approver']['full_name'] = trim($item['approver']['profile']['first_name'] . ' ' . $item['approver']['profile']['last_name']);
                    }
                    if (isset($item['receiver']['profile'])) {
                        $item['receiver']['full_name'] = trim($item['receiver']['profile']['first_name'] . ' ' . $item['receiver']['profile']['last_name']);
                    }
                }
            }

            return $result;

        } catch (\Exception $e) {
            error_log("Error in getMaterialImportReceipts: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialImportReceiptById($id): array
    {
        try {
            $materialIR = (new MaterialImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->with([
                    'provider',
                    'creator' => function ($query) {
                        $query->select('id', 'email', 'role_id')
                            ->with(['profile' => function ($q) {
                                $q->select('user_id', 'first_name', 'last_name');
                            }]);
                    },
                    'approver' => function ($query) {
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
                ])->first();

            if (!$materialIR) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = $materialIR->toArray();

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
            error_log("Error in getMaterialImportReceiptById: " . $e->getMessage());
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

            $materialIR = (new MaterialImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$materialIR) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $detailsQuery = $materialIR->details()
                ->with(['material', 'storageArea', 'materialImportReceipt'])
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

    public function getProvidersByImportReceipt($id): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $materialIR = (new MaterialImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$materialIR) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $providersQuery = $materialIR->provider()
                ->with(['materials', 'materialImportReceipt'])
                ->getQuery();

            return $this->paginateResults($providersQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProvidersByImportReceipt: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createMaterialImportReceipt(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $materialIR = new MaterialImportReceipt();

            $errors = $materialIR->validate($data);
            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $materialIR->fill($data);
            $materialIR->save();

            return [
                'success' => true,
                'data' => $materialIR->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createMaterialImportReceipt: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateMaterialImportReceiptById($id): array
    {
        try {
            $materialIR = (new MaterialImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$materialIR) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $materialIR->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $materialIR->fill($data);
            $materialIR->save();

            return [
                'success' => true,
                'data' => $materialIR->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateMaterialImportReceiptById: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteMaterialImportReceipt($id): array
    {
        try {
            $materialIR = (new MaterialImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->first();

            if (!$materialIR) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $materialIR->deleted = true;
            $materialIR->save();

            return [
                'success' => true,
                'message' => 'Xóa thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteMaterialImportReceipt: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function approveImportReceipt($id): array
    {
        try {
            $materialIR = (new MaterialImportReceipt())
                ->where('id', $id)
                ->where('deleted', false)
                ->where('status', 'PENDING')
                ->first();

            if (!$materialIR) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy phiếu nhập hoặc phiếu không ở trạng thái chờ duyệt'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['approved_by'])) {
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin người duyệt'
                ];
            }

            // Cập nhật trạng thái và người duyệt
            $materialIR->status = 'COMPLETED';
            $materialIR->approved_by = $data['approved_by'];
            $materialIR->save();

            return [
                'success' => true,
                'message' => 'Duyệt phiếu nhập thành công',
                'data' => $materialIR->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in approveImportReceipt: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function importMaterials()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            // Kiểm tra dữ liệu đầu vào
            if (!isset($data['provider_id'])) {
                throw new \Exception('provider_id là bắt buộc');
            }
            if (!isset($data['storage_area_id'])) {
                throw new \Exception('storage_area_id là bắt buộc');
            }
            if (!isset($data['receiver_id'])) {
                throw new \Exception('receiver_id là bắt buộc');
            }
            if (!isset($data['materials']) || !is_array($data['materials'])) {
                throw new \Exception('Danh sách materials là bắt buộc và phải là một mảng');
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

            $providerExists = Provider::where('id', $data['provider_id'])->exists();
            if (!$providerExists) {
                throw new \Exception('Nhà cung cấp không tồn tại');
            }

            // Kiểm tra nếu người dùng gửi receipt_id hoặc nếu không gửi thì ta tự động tạo
            $receiptId = null;
            if (isset($data['receipt_id'])) {
                $receiptId = $data['receipt_id'];
                // Kiểm tra receipt_id đã tồn tại hay chưa
                $existingReceipt = MaterialImportReceipt::where('receipt_id', $receiptId)->first();
                if ($existingReceipt) {
                    throw new \Exception('Receipt ID đã tồn tại');
                }
            } else {
                // Tạo receipt_id ngẫu nhiên không trùng
                do {
                    $receiptId = mt_rand(1, 10000);
                    $existingReceipt = MaterialImportReceipt::where('receipt_id', $receiptId)->first();
                } while ($existingReceipt);
            }

            // Kiểm tra người nhận có tồn tại và đang hoạt động không
            $receiver = User::where('id', $data['receiver_id'])
                ->where('status', 'ACTIVE')
                ->where('deleted', false)
                ->first();

            if (!$receiver) {
                throw new \Exception('Người nhận không tồn tại hoặc không hoạt động');
            }

            // Kiểm tra tất cả các nguyên liệu trước khi thực hiện bất kỳ thao tác nào
            $invalidMaterials = [];
            foreach ($data['materials'] as $material) {
                if (!isset($material['material_id']) || !isset($material['quantity']) || !isset($material['price'])) {
                    throw new \Exception('Thông tin material_id, quantity và price là bắt buộc cho mỗi nguyên liệu');
                }

                $materialModel = Material::find($material['material_id']);
                if (!$materialModel) {
                    $invalidMaterials[] = $material['material_id'];
                }
            }

            // Nếu có bất kỳ nguyên liệu không hợp lệ nào, dừng quá trình và trả về lỗi
            if (!empty($invalidMaterials)) {
                throw new \Exception('Một số nguyên liệu không tồn tại: ' . implode(', ', $invalidMaterials));
            }

            // Nếu tất cả nguyên liệu đều hợp lệ, tiến hành nhập kho
            $materialImportReceipt = MaterialImportReceipt::create([
                'provider_id' => $data['provider_id'],
                'receipt_id' => $receiptId,
                'note' => $data['note'],
                'created_by' => $createdById,
                'receiver_id' => $receiver->id,
            ]);

            $totalPrice = 0;

            foreach ($data['materials'] as $material) {
                $materialModel = Material::find($material['material_id']);
                $price = $material['price'];
                $quantity = $material['quantity'];
                $totalPrice += $price * $quantity;

                $materialImportReceiptDetail = $materialImportReceipt->details()->create([
                    'material_id' => $material['material_id'],
                    'storage_area_id' => $data['storage_area_id'],
                    'quantity' => $quantity,
                    'price' => $price,
                ]);

                $materialStorageLocation = MaterialStorageLocation::firstOrNew([
                    'material_id' => $material['material_id'],
                    'storage_area_id' => $data['storage_area_id'],
                ]);

                // Thêm vào bảng Material Storage Location
                $materialStorageLocation->quantity = ($materialStorageLocation->quantity ?? 0) + $quantity;
                $materialStorageLocation->provider_id = $data['provider_id'];
                $materialStorageLocation->save();

                // Thêm vào bảng Materials
                $materialModel->quantity_available += $quantity;

                // Chỉ cập nhật minimum_stock_level nếu có giá trị mới được cung cấp
                if (isset($material['minimum_stock_level'])) {
                    $materialModel->minimum_stock_level = $material['minimum_stock_level'];
                }

                $materialModel->save();
            }

            $materialImportReceipt->total_price = $totalPrice;
            $materialImportReceipt->status = 'PENDING';
            $materialImportReceipt->save();

            $response = [
                'message' => 'Nhập kho thành công',
                'material_import_receipt_id' => $materialImportReceipt->id,
                'receipt_id' => $materialImportReceipt->receipt_id
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