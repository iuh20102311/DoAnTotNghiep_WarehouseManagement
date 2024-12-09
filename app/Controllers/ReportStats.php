<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialImportReceipt;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Utils\PaginationTrait;
use DateTime;

class ReportStats
{
    public function getStats(): void
    {
        try {
            // [BƯỚC 1] - Token validation
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            if (!$token) {
                throw new \Exception('Token không tồn tại');
            }

            // [BƯỚC 2] - Parse date parameters cho 3 trường hợp
            $queryParams = $_GET;
            $startDate = null;
            $endDate = null;

            if (isset($queryParams['date'])) {
                // Trường hợp 1: Xem theo ngày cụ thể
                $startDate = date('Y-m-d', strtotime($queryParams['date']));
                $endDate = $startDate;
            } elseif (isset($queryParams['startDate']) && isset($queryParams['endDate'])) {
                // Trường hợp 2: Xem theo khoảng thời gian
                $startDate = date('Y-m-d', strtotime($queryParams['startDate']));
                $endDate = date('Y-m-d', strtotime($queryParams['endDate']));

                if ($startDate > $endDate) {
                    throw new \Exception('Ngày bắt đầu không được lớn hơn ngày kết thúc');
                }
            } else {
                // Trường hợp 3: Không truyền param (mặc định từ thứ 2 đến hôm nay)
                $today = new DateTime();
                $startDate = (new DateTime())->modify('monday this week')->format('Y-m-d');
                $endDate = $today->format('Y-m-d');
            }

            // [BƯỚC 3] - Get total available
            $totalProduct = Product::where('deleted', false)
                ->where('status', 'ACTIVE')
                ->count();

            $totalMaterial = Material::where('deleted', false)
                ->where('status', 'ACTIVE')
                ->count();

            $todayRevenue = Order::where('deleted', false)
                ->where('status', 'DELIVERED')
                ->whereDate('created_at', date('Y-m-d'))
                ->sum('total_price');

            $totalUser = User::where('deleted', false)
                ->where('status', 'ACTIVE')
                ->count();

            $totalCustomer = Customer::where('deleted', false)
                ->where('status', 'ACTIVE')
                ->count();

            $pendingImports = MaterialImportReceipt::where('deleted', false)
                ->where('status', 'PENDING_APPROVED')
                ->count();

            // [BƯỚC 4] - Generate revenue data for each date in range
            $revenueData = [];
            $currentDate = new DateTime($startDate);
            $endDateTime = new DateTime($endDate);

            while ($currentDate <= $endDateTime) {
                $dateStr = $currentDate->format('Y-m-d');

                // Get revenue for this specific date
                $revenue = Order::where('deleted', false)
                    ->where('status', 'DELIVERED')
                    ->whereDate('created_at', $dateStr)
                    ->sum('total_price');

                $revenueData[] = [
                    'date' => $currentDate->format('d/m/Y'),
                    'totalRevenue' => (int)$revenue
                ];

                $currentDate->modify('+1 day');
            }

            // [BƯỚC 5] - Prepare response
            $response = [
                'summary' => [
                    'totalProduct' => (int)$totalProduct,
                    'totalMaterial' => (int)$totalMaterial,
                    'totalEmployee' => (int)$totalUser,
                    'totalCustomer' => (int)$totalCustomer,
                    'todayRevenue' => (int)$todayRevenue,
                    'pendingImports' => (int)$pendingImports
                ],
                'revenue' => $revenueData
            ];

            header('Content-Type: application/json');
            echo json_encode($response, JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}