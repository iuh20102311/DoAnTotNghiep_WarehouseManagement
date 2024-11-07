<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\Provider;
use App\Utils\PaginationTrait;

class ProviderController
{
    use PaginationTrait;

    public function getProviders(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $provider = Provider::query()
                ->where('deleted', false)
                ->with(['materials', 'materialImportReceipts'])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
                ->orderBy('created_at', 'desc');

            // Add all filters
            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $provider->where('status', $status);
            }

            if (isset($_GET['name'])) {
                $name = urldecode($_GET['name']);
                $provider->where('name', 'like', '%' . $name . '%');
            }

            if (isset($_GET['email'])) {
                $email = urldecode($_GET['email']);
                $provider->where('email', 'like', '%' . $email . '%');
            }

            if (isset($_GET['phone'])) {
                $phone = urldecode($_GET['phone']);
                $length = strlen($phone);
                $provider->whereRaw('SUBSTRING(phone, 1, ?) = ?', [$length, $phone]);
            }

            if (isset($_GET['address'])) {
                $address = urldecode($_GET['address']);
                $provider->where('address', 'like', '%' . $address . '%');
            }

            if (isset($_GET['city'])) {
                $city = urldecode($_GET['city']);
                $provider->where('city', 'like', '%' . $city . '%');
            }

            if (isset($_GET['district'])) {
                $district = urldecode($_GET['district']);
                $provider->where('district', 'like', '%' . $district . '%');
            }

            if (isset($_GET['ward'])) {
                $ward = urldecode($_GET['ward']);
                $provider->where('ward', 'like', '%' . $ward . '%');
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $provider->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $provider->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $provider->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $provider->where('updated_at', '<=', $updatedTo);
            }

            return $this->paginateResults($provider, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProviders: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProviderById($id): array
    {
        try {
            $provider = Provider::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->with(['materials', 'materialImportReceipts'])
                ->first();

            if (!$provider) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy nhà cung cấp'
                ];
            }

            return $provider->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProviderById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialByProvider($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $provider = Provider::where('deleted', false)->find($id);

            if (!$provider) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy nhà cung cấp'
                ];
            }

            $materialsQuery = $provider->materials()
                ->with(['categories', 'providers', 'storageLocations'])
                ->getQuery();

            return $this->paginateResults($materialsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialByProvider: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getMaterialImportReceiptsByProvider($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $provider = Provider::where('deleted', false)->find($id);

            if (!$provider) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhà cung cấp'
                ];
            }

            $materialImportReceiptsQuery = $provider->materialImportReceipts()
                ->with(['provider', 'creator', 'approver', 'receiver', 'details'])
                ->getQuery();

            return $this->paginateResults($materialImportReceiptsQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getMaterialImportReceiptsByProvider: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addMaterialToProvider($id): array
    {
        try {
            $provider = Provider::where('deleted', false)->find($id);

            if (!$provider) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhà cung cấp'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['material_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin vật liệu'
                ];
            }

            $material = Material::where('deleted', false)->find($data['material_id']);

            if (!$material) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            if ($provider->materials()->where('material_id', $material->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Vật liệu đã tồn tại trong danh sách của nhà cung cấp này'
                ];
            }

            $provider->materials()->attach($material);

            return [
                'success' => true,
                'message' => 'Thêm vật liệu vào nhà cung cấp thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in addMaterialToProvider: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateMaterialInProvider($id): array
    {
        try {
            $provider = Provider::where('deleted', false)->find($id);

            if (!$provider) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhà cung cấp'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['old_material_id']) || !isset($data['new_material_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin vật liệu cũ hoặc mới'
                ];
            }

            $oldMaterial = Material::where('deleted', false)->find($data['old_material_id']);
            $newMaterial = Material::where('deleted', false)->find($data['new_material_id']);

            if (!$oldMaterial || !$newMaterial) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vật liệu cũ hoặc mới'
                ];
            }

            if (!$provider->materials()->where('material_id', $oldMaterial->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Vật liệu cũ không tồn tại trong danh sách vật liệu của nhà cung cấp này'
                ];
            }

            if ($provider->materials()->where('material_id', $newMaterial->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Vật liệu mới đã tồn tại trong danh sách vật liệu của nhà cung cấp này'
                ];
            }

            $provider->materials()->detach($oldMaterial);
            $provider->materials()->attach($newMaterial);

            return [
                'success' => true,
                'message' => 'Cập nhật vật liệu thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in updateMaterialInProvider: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removeMaterialFromProvider($id): array
    {
        try {
            $provider = Provider::where('deleted', false)->find($id);

            if (!$provider) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhà cung cấp'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['material_id'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Thiếu thông tin vật liệu'
                ];
            }

            $material = Material::where('deleted', false)->find($data['material_id']);

            if (!$material) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy vật liệu'
                ];
            }

            if (!$provider->materials()->where('material_id', $material->id)->exists()) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Vật liệu không tồn tại trong danh sách vật liệu của nhà cung cấp này'
                ];
            }

            $provider->materials()->detach($material);

            return [
                'success' => true,
                'message' => 'Xóa vật liệu khỏi nhà cung cấp thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removeMaterialFromProvider: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createProvider(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $provider = new Provider();
            $errors = $provider->validate($data);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $provider->fill($data);
            $provider->save();

            return [
                'success' => true,
                'data' => $provider->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createProvider: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProviderById($id): array
    {
        try {
            $provider = Provider::where('deleted', false)->find($id);

            if (!$provider) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhà cung cấp'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $provider->validate($data, true);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $provider->fill($data);
            $provider->save();

            return [
                'success' => true,
                'data' => $provider->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateProviderById: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteProvider($id): array
    {
        try {
            $provider = Provider::where('deleted', false)->find($id);

            if (!$provider) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy nhà cung cấp'
                ];
            }

            $provider->deleted = true;
            $provider->save();

            return [
                'success' => true,
                'message' => 'Xóa nhà cung cấp thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteProvider: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}