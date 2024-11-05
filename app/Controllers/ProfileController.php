<?php

namespace App\Controllers;

use App\Models\Profile;
use App\Utils\PaginationTrait;

class ProfileController
{
    use PaginationTrait;

    public function getProfile(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $profile = Profile::query()
                ->where('deleted', false)
                ->with(['user', 'createdOrders']);

            // Add all filters
            if (isset($_GET['phone'])) {
                $phone = urldecode($_GET['phone']);
                $length = strlen($phone);
                $profile->whereRaw('SUBSTRING(phone, 1, ?) = ?', [$length, $phone]);
            }

            if (isset($_GET['gender'])) {
                $gender = urldecode($_GET['gender']);
                $profile->where('gender', $gender);
            }

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $profile->where('status', $status);
            }

            if (isset($_GET['first_name'])) {
                $first_name = urldecode($_GET['first_name']);
                $profile->where('first_name', 'like', '%' . $first_name . '%');
            }

            if (isset($_GET['last_name'])) {
                $last_name = urldecode($_GET['last_name']);
                $profile->where('last_name', 'like', '%' . $last_name . '%');
            }

            if (isset($_GET['birthday_from'])) {
                $birthdayFrom = urldecode($_GET['birthday_from']);
                $profile->where('birthday', '>=', $birthdayFrom);
            }

            if (isset($_GET['birthday_to'])) {
                $birthdayTo = urldecode($_GET['birthday_to']);
                $profile->where('birthday', '<=', $birthdayTo);
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $profile->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $profile->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $profile->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $profile->where('updated_at', '<=', $updatedTo);
            }

            return $this->paginateResults($profile, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProfile: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getProfileById($id): array
    {
        try {
            $profile = Profile::query()
                ->where('id', $id)
                ->where('deleted', false)
                ->with(['user', 'createdOrders'])
                ->first();

            if (!$profile) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy hồ sơ'
                ];
            }

            return $profile->toArray();

        } catch (\Exception $e) {
            error_log("Error in getProfileById: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getUserByProfile($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $profile = Profile::where('deleted', false)->find($id);

            if (!$profile) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy hồ sơ'
                ];
            }

            $usersQuery = $profile->user()
                ->with(['orders', 'role', 'createdInventoryChecks', 'inventoryHistory'])
                ->getQuery();

            return $this->paginateResults($usersQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getUserByProfile: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getCreatedOrdersByProfile($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $profile = Profile::where('deleted', false)->find($id);

            if (!$profile) {
                http_response_code(404);
                return [
                    'error' => 'Không tìm thấy hồ sơ'
                ];
            }

            $createdOrdersQuery = $profile->createdOrders()
                ->with(['customer', 'creator', 'orderDetails', 'giftSets', 'orderGiftSets'])
                ->getQuery();

            return $this->paginateResults($createdOrdersQuery, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in getCreatedOrdersByProfile: " . $e->getMessage());
            http_response_code(500);
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createProfile(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $profile = new Profile();
            $errors = $profile->validate($data);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $profile->fill($data);
            $profile->save();

            return [
                'success' => true,
                'data' => $profile->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createProfile: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateProfileById($id): array
    {
        try {
            $profile = Profile::where('deleted', false)->find($id);

            if (!$profile) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy hồ sơ'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $profile->validate($data, true);

            if ($errors) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $profile->fill($data);
            $profile->save();

            return [
                'success' => true,
                'data' => $profile->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateProfileById: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteProfile($id): array
    {
        try {
            $profile = Profile::where('deleted', false)->find($id);

            if (!$profile) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy hồ sơ'
                ];
            }

            $profile->deleted = true;
            $profile->save();

            return [
                'success' => true,
                'message' => 'Xóa hồ sơ thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteProfile: " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}