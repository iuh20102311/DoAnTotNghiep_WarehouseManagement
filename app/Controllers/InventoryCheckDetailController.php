<?php

namespace App\Controllers;

use App\Models\InventoryCheckDetail;
use App\Models\Material;
use App\Models\Product;
use App\Utils\PaginationTrait;

class InventoryCheckDetailController
{
    use PaginationTrait;

    public function getInventoryCheckDetails(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $inventoryCheckDetail = InventoryCheckDetail::query()
                ->where('deleted', false)
                ->with(['inventoryCheck', 'product', 'material'])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
                ->orderBy('created_at', 'desc');

            if (isset($_GET['inventory_check_id'])) {
                $inventoryCheckId = urldecode($_GET['inventory_check_id']);
                $inventoryCheckDetail->where('inventory_check_id', $inventoryCheckId);
            }

            if (isset($_GET['type'])) {
                $type = urldecode($_GET['type']);
                if ($type === 'product') {
                    $inventoryCheckDetail->whereNotNull('product_id')->whereNull('material_id');
                } else if ($type === 'material') {
                    $inventoryCheckDetail->whereNotNull('material_id')->whereNull('product_id');
                }
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $inventoryCheckDetail->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $inventoryCheckDetail->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $inventoryCheckDetail->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $inventoryCheckDetail->where('updated_at', '<=', $updatedTo);
            }

            return $this->paginateResults($inventoryCheckDetail, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getInventoryCheckDetails: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryCheckDetailById($id): array
    {
        try {
            $detail = InventoryCheckDetail::query()
                ->where('deleted', false)
                ->with(['inventoryCheck', 'product', 'material'])
                ->find($id);

            if (!$detail) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            return $detail->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createInventoryCheckDetail(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $detail = new InventoryCheckDetail();
            $errors = $detail->validate($data);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $detail->fill($data);
            $detail->save();

            return [
                'success' => true,
                'data' => $detail->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateInventoryCheckDetailById($id): array
    {
        try {
            $detail = InventoryCheckDetail::query()
                ->where('deleted', false)
                ->find($id);

            if (!$detail) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $detail->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $detail->fill($data);
            $detail->save();

            return [
                'success' => true,
                'data' => $detail->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteInventoryCheckDetail($id): array
    {
        try {
            $detail = InventoryCheckDetail::find($id);

            if (!$detail) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $detail->deleted = true;
            $detail->save();

            return [
                'success' => false,
                'message' => 'Xóa thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getInventoryChecksByInventoryCheckDetail($id): array
    {
        try {
            $detail = InventoryCheckDetail::find($id);

            if (!$detail) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = $detail->inventoryCheck()->with([
                'creator' => function ($productER) {
                    $productER->select('id', 'email', 'role_id')
                        ->with(['profile' => function ($q) {
                            $q->select('user_id', 'first_name', 'last_name');
                        }]);
                },
                'storageArea'
            ])->get()->toArray();

            if (isset($data['creator']['profile'])) {
                $data['creator']['full_name'] = trim($data['creator']['profile']['first_name'] . ' ' . $data['creator']['profile']['last_name']);
            }

            return $data;

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}