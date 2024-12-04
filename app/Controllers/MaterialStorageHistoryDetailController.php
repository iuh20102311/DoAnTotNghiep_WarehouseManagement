<?php

namespace App\Controllers;

use App\Models\MaterialStorageHistoryDetail;
use App\Utils\PaginationTrait;

class MaterialStorageHistoryDetailController
{
    use PaginationTrait;

    public function getAll(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $query = MaterialStorageHistoryDetail::query()
                ->with(['materialStorageHistory', 'materialStorageHistory.material', 'materialStorageHistory.storageArea', 'creator'])
                ->orderBy('created_at', 'desc');

            // Search by material name or SKU
            if (isset($_GET['search_material'])) {
                $searchMaterial = urldecode($_GET['search_material']);
                $query->whereHas('materialStorageHistory.material', function ($q) use ($searchMaterial) {
                    $q->where('name', 'LIKE', '%' . $searchMaterial . '%')
                        ->orWhere('sku', 'LIKE', '%' . $searchMaterial . '%');
                });
            }

            // Search by storage area name or code
            if (isset($_GET['search_storage_area'])) {
                $searchStorageArea = urldecode($_GET['search_storage_area']);
                $query->whereHas('materialStorageHistory.storageArea', function ($q) use ($searchStorageArea) {
                    $q->where('code', 'LIKE', '%' . $searchStorageArea . '%')
                        ->orWhere('name', 'LIKE', '%' . $searchStorageArea . '%');
                });
            }

            // Filter by material ID
            if (isset($_GET['material_id'])) {
                $materialId = urldecode($_GET['material_id']);
                $query->whereHas('materialStorageHistory', function ($q) use ($materialId) {
                    $q->where('material_id', $materialId);
                });
            }

            // Filter by storage area ID
            if (isset($_GET['storage_area_id'])) {
                $storageAreaId = urldecode($_GET['storage_area_id']);
                $query->whereHas('materialStorageHistory', function ($q) use ($storageAreaId) {
                    $q->where('storage_area_id', $storageAreaId);
                });
            }

            // Filter by quantity before/after change
            if (isset($_GET['quantity_before'])) {
                $quantityBefore = urldecode($_GET['quantity_before']);
                $query->where('quantity_before', $quantityBefore);
            }
            if (isset($_GET['quantity_after'])) {
                $quantityAfter = urldecode($_GET['quantity_after']);
                $query->where('quantity_after', $quantityAfter);
            }

            // Filter by quantity change
            if (isset($_GET['quantity_change'])) {
                $quantityChange = urldecode($_GET['quantity_change']);
                $query->where('quantity_change', $quantityChange);
            }
            if (isset($_GET['quantity_change_min'])) {
                $quantityChangeMin = urldecode($_GET['quantity_change_min']);
                $query->where('quantity_change', '>=', $quantityChangeMin);
            }
            if (isset($_GET['quantity_change_max'])) {
                $quantityChangeMax = urldecode($_GET['quantity_change_max']);
                $query->where('quantity_change', '<=', $quantityChangeMax);
            }

            // Filter by action type
            if (isset($_GET['action_type'])) {
                $actionType = urldecode($_GET['action_type']);
                $query->where('action_type', $actionType);
            }

            // Filter by creation date
            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $query->where('created_at', '>=', $createdFrom);
            }
            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $query->where('created_at', '<=', $createdTo);
            }

            // Filter by creator
            if (isset($_GET['created_by'])) {
                $createdBy = urldecode($_GET['created_by']);
                $query->where('created_by', $createdBy);
            }

            return $this->paginateResults($query, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log('Error in getAll MaterialStorageHistoryDetail: ' . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}