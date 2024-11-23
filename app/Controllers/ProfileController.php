<?php

namespace App\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Utils\PaginationTrait;

class ProfileController
{
    use PaginationTrait;

    public function getProfile(): array
    {
        try {
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $profile = Profile::query()
                ->where('deleted', false)
                ->with(['user', 'createdOrders'])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
                ->orderBy('created_at', 'desc');

            if (isset($_GET['phone'])) {
                $phone = urldecode($_GET['phone']);
                $profile->where(function($query) use ($phone) {
                    $query->where('phone', 'LIKE', '%'.$phone.'%')
                        ->orWhere('phone', 'LIKE', $phone.'%')
                        ->orWhere('phone', 'LIKE', '%'.$phone);
                });
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

    public function getProfileByCode($code): array
    {
        try {
            $profile = Profile::query()
                ->where('code', $code)
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
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

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
            $perPage = (int)($_GET['per_page'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

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

            // Required fields for User
            $userData = array_intersect_key($data, array_flip([
                'email',
                'password',
                'role_id',
                'status'
            ]));

            // Required fields for Profile
            $requiredProfileData = array_intersect_key($data, array_flip([
                'first_name',
                'last_name',
                'phone',
                'gender',
                'status'
            ]));

            // Optional fields for Profile - only include if they exist in input
            $optionalFields = ['avatar', 'address', 'ward', 'district', 'city', 'birthday'];
            $optionalProfileData = [];
            foreach ($optionalFields as $field) {
                if (isset($data[$field])) {
                    $optionalProfileData[$field] = $data[$field];
                }
            }

            // Merge required and optional profile fields
            $profileData = array_merge($requiredProfileData, $optionalProfileData);

            // Validate data
            $user = new User();
            $userErrors = $user->validate($userData);

            $profile = new Profile();
            $profileErrors = $profile->validate($profileData);

            // Combine validation errors if any
            $errors = array_merge($userErrors ?? [], $profileErrors ?? []);

            if (!empty($errors)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            // Create User first
            $user->fill($userData);
            $user->save();

            if (!$user->id) {
                throw new \Exception('Failed to create user');
            }

            // Generate new code for provider
            $currentMonth = date('m');
            $currentYear = date('y');
            $prefix = "NCC" . $currentMonth . $currentYear;

            // Get latest provider code with current prefix
            $latestProfile = Profile::query()
                ->where('code', 'LIKE', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            if ($latestProfile) {
                $sequence = intval(substr($latestProfile->code, -5)) + 1;
            } else {
                $sequence = 1;
            }

            // Format sequence to 5 digits
            $profileData['code'] = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
            $profileData['user_id'] = $user->id;

            // Create Profile
            $profile->fill($profileData);
            $saveResult = $profile->save();

            if (!$saveResult) {
                // If profile creation fails, try to delete the created user
                try {
                    $user->delete();
                } catch (\Exception $e) {
                    error_log("Failed to delete user after profile creation failed: " . $e->getMessage());
                }
                throw new \Exception('Failed to create profile');
            }

            // Load the profile with user relationship for response
            $profileWithUser = $profile->toArray();
            $profileWithUser['user'] = $user->toArray();
            unset($profileWithUser['user']['password']); // Remove password from response

            return [
                'success' => true,
                'data' => $profileWithUser
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

            // Remove code from update data to prevent modification
            if (isset($data['code'])) {
                unset($data['code']);
            }

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