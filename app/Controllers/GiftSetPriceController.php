<?php

namespace App\Controllers;

use App\Models\GiftSetPrice;
use App\Models\GiftSet;
use App\Utils\PaginationTrait;

class GiftSetPriceController
{
    use PaginationTrait;

    public function getGiftSetPrices($perPage = 10, $page = 1, array $searchParams = []): array
    {
        try {
            $giftSetPrice = (new GiftSetPrice())->query()
                ->where('deleted', false)
                ->with(['giftSet']);

            // Search theo gift_set_id
            if (!empty($searchParams['gift_set_id'])) {
                $giftSetPrice->where('gift_set_id', $searchParams['gift_set_id']);
            }

            // Search theo khoảng giá
            if (!empty($searchParams['price_from'])) {
                $giftSetPrice->where('price', '>=', $searchParams['price_from']);
            }
            if (!empty($searchParams['price_to'])) {
                $giftSetPrice->where('price', '<=', $searchParams['price_to']);
            }

            // Search theo status
            if (!empty($searchParams['status'])) {
                $giftSetPrice->where('status', $searchParams['status']);
            }

            // Search theo khoảng thời gian hết hạn
            if (!empty($searchParams['expiry_from'])) {
                $giftSetPrice->where('date_expiry', '>=', $searchParams['expiry_from']);
            }
            if (!empty($searchParams['expiry_to'])) {
                $giftSetPrice->where('date_expiry', '<=', $searchParams['expiry_to']);
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
            $giftSet = (new GiftSet())->find($data['gift_set_id']);
            if (!$giftSet) {
                return [
                    'error' => 'Không tìm thấy quà tặng'
                ];
            }

            $giftSetPrice = new GiftSetPrice();
            $errors = $giftSetPrice->validate($data);

            if ($errors) {
                return [
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $giftSetPrice->fill($data);
            $giftSetPrice->save();

            return [
                'data' => $giftSetPrice->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateGiftSetPrice($id): array
    {
        try {
            $giftSetPrice = (new GiftSetPrice())->find($id);

            if (!$giftSetPrice) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Nếu có cập nhật gift_set_id thì kiểm tra gift set tồn tại
            if (!empty($data['gift_set_id'])) {
                $giftSet = (new GiftSet())->find($data['gift_set_id']);
                if (!$giftSet) {
                    return [
                        'error' => 'Không tìm thấy quà tặng'
                    ];
                }
            }

            $errors = $giftSetPrice->validate($data, true);

            if ($errors) {
                return [
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $giftSetPrice->fill($data);
            $giftSetPrice->save();

            return [
                'data' => $giftSetPrice->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in: " . $e->getMessage());
            return [
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

            $giftSet = (new GiftSet())->find($id);

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
            $giftSet = (new GiftSet())->find($id);

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
            $giftSet = (new GiftSet())->find($id);

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
            $giftSet = (new GiftSet())->find($id);

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