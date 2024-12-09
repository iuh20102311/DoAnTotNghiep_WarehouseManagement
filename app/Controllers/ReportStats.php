<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialImportReceipt;
use App\Models\Order;
use App\Models\Product;
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

            // [BƯỚC 2] - Parse date parameters
            $queryParams = $_GET;
            $startDate = null;
            $endDate = null;
            $specificDate = null;

            if (isset($queryParams['date'])) {
                // Nếu truyền vào ngày cụ thể
                $specificDate = date('Y-m-d', strtotime($queryParams['date']));
                $startDate = $specificDate;
                $endDate = $specificDate;
            } else if (isset($queryParams['startDate']) && isset($queryParams['endDate'])) {
                // Nếu truyền vào khoảng thời gian
                $startDate = date('Y-m-d', strtotime($queryParams['startDate']));
                $endDate = date('Y-m-d', strtotime($queryParams['endDate']));

                if ($startDate > $endDate) {
                    throw new \Exception('Ngày bắt đầu không được lớn hơn ngày kết thúc');
                }
            } else {
                // Mặc định lấy từ thứ 2 tuần này đến hôm nay
                $today = new DateTime();
                $startDate = $today->modify('monday this week')->format('Y-m-d');
                $endDate = date('Y-m-d');
            }

            // [BƯỚC 3] - Get total available products
            $totalProduct = Product::where('deleted', false)
                ->where('status', 'ACTIVE')
                ->sum('quantity_available');

            // [BƯỚC 4] - Get total available materials
            $totalMaterial = Material::where('deleted', false)
                ->where('status', 'ACTIVE')
                ->sum('quantity_available');

            // [BƯỚC 5] - Get today's completed orders revenue
            $todayRevenue = Order::where('deleted', false)
                ->where('status', 'DELIVERED')
                ->whereDate('created_at', date('Y-m-d'))
                ->sum('total_price');

            // [BƯỚC 6] - Get pending import receipts count
            $pendingImports = MaterialImportReceipt::where('deleted', false)
                ->where('status', 'TEMPORARY')
                ->count();

            // [BƯỚC 7] - Get revenue by date range
            $revenueQuery = Order::where('deleted', false)
                ->where('status', 'DELIVERED')
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as totalOrders, SUM(total_price) as totalRevenue')
                ->groupBy('date')
                ->orderBy('date');

            $revenueByDate = $revenueQuery->get()->map(function ($item) {
                return [
                    'date' => date('d/m/Y', strtotime($item->date)),
                    'totalOrders' => (int)$item->totalOrders,
                    'totalRevenue' => (int)$item->totalRevenue
                ];
            });

            // Tính tổng trong khoảng thời gian
            $periodTotals = [
                'totalOrders' => $revenueByDate->sum('totalOrders'),
                'totalRevenue' => $revenueByDate->sum('totalRevenue')
            ];

            // [BƯỚC 8] - Prepare response
            $response = [
                'summary' => [
                    'totalProduct' => (int)$totalProduct,
                    'totalMaterial' => (int)$totalMaterial,
                    'todayRevenue' => (int)$todayRevenue,
                    'pendingImports' => (int)$pendingImports
                ],
                'revenue' => [
                    'startDate' => date('d/m/Y', strtotime($startDate)),
                    'endDate' => date('d/m/Y', strtotime($endDate)),
                    'totalOrders' => $periodTotals['totalOrders'],
                    'totalRevenue' => $periodTotals['totalRevenue'],
                    'details' => $revenueByDate
                ]
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