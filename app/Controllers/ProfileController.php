<?php

namespace App\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProfileController
{
    use PaginationTrait;

    public function getProfile(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $profile = Profile::query()
            ->where('deleted', false)
            ->with(['user', 'createdOrders']);

        if (isset($_GET['phone'])) {
            $phone = urldecode($_GET['phone']);
            $length = strlen($phone);
            $profile->whereRaw('SUBSTRING(phone, 1, ?) = ?', [$length, $phone]);
        }

        if (isset($_GET['gender'])) {
            $gender = urldecode($_GET['gender']);
            $profile->where('gender', $gender);
        }

        if (isset($_GET['first_name'])) {
            $first_name = urldecode($_GET['first_name']);
            $profile->where('first_name', 'like', '%' . $first_name . '%');
        }

        if (isset($_GET['last_name'])) {
            $last_name = urldecode($_GET['last_name']);
            $profile->where('last_name', 'like', '%' . $last_name . '%');
        }

        return $this->paginateResults($profile, $perPage, $page)->toArray();
    }

    public function getProfileById($id): false|string
    {
        $profile = Profile::query()->where('id', $id)
            ->with(['user', 'createdOrders'])
            ->first();

        if (!$profile) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($profile->toArray());
    }

    public function getUserByProfile($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $profile = Profile::query()->where('id', $id)->firstOrFail();
        $usersQuery = $profile->product()
            ->with(['orders', 'role','createdInventoryChecks','inventoryHistory'])
            ->getQuery();

        return $this->paginateResults($usersQuery, $perPage, $page)->toArray();
    }

    public function getCreatedOrdersByProfile($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $profile = Profile::query()->where('id', $id)->firstOrFail();
        $createdOrdersQuery = $profile->createdOrders()
            ->with(['customer', 'creator','orderDetails','giftSets','orderGiftSets'])
            ->getQuery();

        return $this->paginateResults($createdOrdersQuery, $perPage, $page)->toArray();
    }

    public function createProfile(): Model|string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $profile = new Profile();
        $error = $profile->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $profile->fill($data);
        $profile->save();
        return $profile;
    }

    public function updateProfileById($id): bool|int|string
    {
        $profile = Profile::find($id);

        if (!$profile) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $profile->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $profile->fill($data);
        $profile->save();

        return $profile;
    }

    public function deleteProfile($id)
    {
        $results = Profile::destroy($id);
        $results === 0 && http_response_code(404);
        return $results === 1 ? "Xóa thành công" : "Không tìm thấy";
    }
}