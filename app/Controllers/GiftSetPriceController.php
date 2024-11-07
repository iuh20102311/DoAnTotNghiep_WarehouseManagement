<?php

namespace App\Controllers;

use App\Models\GiftSetPrice;
use App\Models\GiftSet;
use App\Utils\PaginationTrait;

class GiftSetPriceController
{
    use PaginationTrait;

    public function getGiftSetPrices(): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $giftSetPrice = (new GiftSetPrice())->query()
                ->where('deleted', false)
                ->with(['giftSet'])
                ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
                ->orderBy('created_at', 'desc');

            if (isset($_GET['gift_set_id'])) {
                $giftSetId = urldecode($_GET['gift_set_id']);
                $giftSetPrice->where('gift_set_id', $giftSetId);
            }

            if (isset($_GET['status'])) {
                $status = urldecode($_GET['status']);
                $giftSetPrice->where('status', $status);
            }

            if (isset($_GET['price_from'])) {
                $priceFrom = urldecode($_GET['price_from']);
                $giftSetPrice->where('price', '>=', $priceFrom);
            }

            if (isset($_GET['price_to'])) {
                $priceTo = urldecode($_GET['price_to']);
                $giftSetPrice->where('price', '>=', $priceTo);
            }

            if (isset($_GET['created_from'])) {
                $createdFrom = urldecode($_GET['created_from']);
                $giftSetPrice->where('created_at', '>=', $createdFrom);
            }

            if (isset($_GET['created_to'])) {
                $createdTo = urldecode($_GET['created_to']);
                $giftSetPrice->where('created_at', '<=', $createdTo);
            }

            if (isset($_GET['updated_from'])) {
                $updatedFrom = urldecode($_GET['updated_from']);
                $giftSetPrice->where('updated_at', '>=', $updatedFrom);
            }

            if (isset($_GET['updated_to'])) {
                $updatedTo = urldecode($_GET['updated_to']);
                $giftSetPrice->where('updated_at', '<=', $updatedTo);
            }

            return $this->paginateResults($giftSetPrice, $perPage, $page)->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getGiftSetPriceById($id): array
    {
        try {
            $giftSetPrice = (new GiftSetPrice())
                ->where('deleted', false)
                ->with(['giftSet'])
                ->find($id);

            if (!$giftSetPrice) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            return $giftSetPrice->toArray();

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function createGiftSetPrice(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Kiểm tra gift set tồn tại
            $giftSet = (new GiftSet())->where('deleted',false)->find($data['gift_set_id']);

            if (!$giftSet) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $giftSetPrice = new GiftSetPrice();
            $errors = $giftSetPrice->validate($data);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $giftSetPrice->fill($data);
            $giftSetPrice->save();

            return [
                'success' => true,
                'data' => $giftSetPrice->toArray()
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

    public function updateGiftSetPrice($id): array
    {
        try {
            $giftSetPrice = (new GiftSetPrice())->where('deleted',false)->find($id);

            if (!$giftSetPrice) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Nếu có cập nhật gift_set_id thì kiểm tra gift set tồn tại
            if (!empty($data['gift_set_id'])) {
                $giftSet = (new GiftSet())->where('deleted',false)->find($data['gift_set_id']);
                if (!$giftSet) {
                    return [
                        'success' => false,
                        'error' => 'Không tìm thấy quà tặng'
                    ];
                }
            }

            $errors = $giftSetPrice->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $giftSetPrice->fill($data);
            $giftSetPrice->save();

            return [
                'success' => true,
                'data' => $giftSetPrice->toArray()
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

    public function deleteGiftSetPrice($id): array
    {
        try {
            $giftSetPrice = (new GiftSetPrice())->find($id);

            if (!$giftSetPrice) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $giftSetPrice->deleted = true;
            $giftSetPrice->save();

            return [
                'message' => 'Xóa thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteGiftSetPrice: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function getPricesByGiftSet($id): array
    {
        try {
            $perPage = $_GET['per_page'] ?? 10;
            $page = $_GET['page'] ?? 1;

            $giftSet = (new GiftSet())->where('deleted',false)->find($id);

            if (!$giftSet) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy'
                ];
            }

            $pricesQuery = $giftSet->prices()
                ->where('deleted', false)
                ->getQuery();

            $result = $this->paginateResults($pricesQuery, $perPage, $page);

            return [
                'success' => true,
                'data' => $result->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in getPricesByGiftSet: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function addPriceToGiftSet($id): array
    {
        try {
            $giftSet = (new GiftSet())->where('deleted',false)->find($id);

            if (!$giftSet) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $data['gift_set_id'] = $id;

            // Tạo instance mới của GiftSetPrice
            $giftSetPrice = new GiftSetPrice();

            // Validate input
            $errors = $giftSetPrice->validate($data);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $giftSetPrice->fill($data);
            $giftSetPrice->save();

            return [
                'success' => true,
                'message' => 'Thêm giá cho quà tặng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in addPriceToGiftSet: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function removePriceFromGiftSet($id, $priceId): array
    {
        try {
            $giftSet = (new GiftSet())->where('deleted',false)->find($id);

            if (!$giftSet) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $giftSetPrice = (new GiftSetPrice())
                ->where('gift_set_id', $id)
                ->where('id', $priceId)
                ->first();

            if (!$giftSetPrice) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy giá của quà tặng'
                ];
            }

            // Soft delete
            $giftSetPrice->deleted = true;
            $giftSetPrice->save();

            return [
                'success' => true,
                'message' => 'Xóa giá của quà tặng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in removePriceFromGiftSet: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateGiftSetPriceByGiftSet($id, $priceId): array
    {
        try {
            $giftSet = (new GiftSet())->where('deleted',false)->find($id);

            if (!$giftSet) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $giftSetPrice = (new GiftSetPrice())
                ->where('deleted',false)
                ->where('gift_set_id', $id)
                ->where('id', $priceId)
                ->first();

            if (!$giftSetPrice) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy giá của quà tặng'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Không cho phép thay đổi gift_set_id
            unset($data['gift_set_id']);

            // Validate
            $errors = $giftSetPrice->validate($data, true);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $giftSetPrice->fill($data);
            $giftSetPrice->save();

            return [
                'success' => true,
                'message' => 'Cập nhật giá của quà tặng thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in updateGiftSetPrice: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}