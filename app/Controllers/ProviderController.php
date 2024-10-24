<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\Provider;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class ProviderController
{
    use PaginationTrait;

    public function getProviders(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $provider = Provider::query()->where('status', '!=', 'DELETED')
            ->with(['materials', 'materialImportReceipts']);

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

        return $this->paginateResults($provider, $perPage, $page)->toArray();
    }

    public function getProviderById($id): false|string
    {
        $provider = Provider::query()->where('id', $id)
            ->with(['materials', 'materialImportReceipts'])
            ->first();

        if (!$provider) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($provider->toArray());
    }

    public function getMaterialByProvider($id)
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $provider = Provider::query()->where('id', $id)->firstOrFail();
        $materialsQuery = $provider->materials()
            ->with(['categories', 'providers', 'storageLocations','exportReceiptDetails',
                    'importReceiptDetails','inventoryCheckDetails','inventoryHistory'])
            ->getQuery();

        return $this->paginateResults($materialsQuery, $perPage, $page)->toArray();
    }

    public function getMaterialImportReceiptsByProvider($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $provider = Provider::query()->where('id', $id)->firstOrFail();
        $materialImportReceiptsQuery = $provider->materialImportReceipts()
            ->with(['provider', 'creator', 'approver', 'receiver', 'details'])
            ->getQuery();

        return $this->paginateResults($materialImportReceiptsQuery, $perPage, $page)->toArray();
    }

    public function addMaterialToProvider($id)
    {
        $provider = Provider::query()->where('id', $id)->first();
        $data = json_decode(file_get_contents('php://input'), true);
        $material = Material::query()->where('id', $data['material_id'])->first();
        $provider->material()->attach($material);
        return 'Thêm thành công';
    }

    public function createProvider(): Model|string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $provider = new Provider();
        $error = $provider->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $provider->fill($data);
        $provider->save();
        return $provider;
    }

    public function updateProviderById($id): bool|int|string
    {
        $provider = Provider::find($id);

        if (!$provider) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $provider->validate($data, true); // Gọi hàm validate với tham số thứ hai là true để chỉ kiểm tra những trường được cập nhật

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $provider->fill($data);
        $provider->save();

        return $provider;
    }

    public function deleteProvider($id)
    {
        $provider = Provider::find($id);

        if ($provider) {
            $provider->status = 'DELETED';
            $provider->save();
            return "Xóa thành công";
        } else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}