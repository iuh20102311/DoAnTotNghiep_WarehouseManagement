<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialImportReceipt;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as DB;
use DateTime;

class ReportStats
{
    public function getStats(): void
    {
        try {
            // [STEP 1] - Token validation
            $headers = apache_request_headers();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            if (!$token) {
                throw new \Exception('Token không tồn tại');
            }

            // [STEP 2] - Parse date parameters for 3 cases
            $queryParams = $_GET;
            $startDate = null;
            $endDate = null;

            if (isset($queryParams['date'])) {
                // Case 1: View by specific date
                $startDate = date('Y-m-d', strtotime($queryParams['date']));
                $endDate = $startDate;
            } elseif (isset($queryParams['startDate']) && isset($queryParams['endDate'])) {
                // Case 2: View by date range
                $startDate = date('Y-m-d', strtotime($queryParams['startDate']));
                $endDate = date('Y-m-d', strtotime($queryParams['endDate']));

                if ($startDate > $endDate) {
                    throw new \Exception('Ngày bắt đầu không được lớn hơn ngày kết thúc');
                }
            } else {
                // Case 3: No params (default from Monday to today)
                $today = new DateTime();
                $startDate = (new DateTime())->modify('monday this week')->format('Y-m-d');
                $endDate = $today->format('Y-m-d');
            }

            // [STEP 3] - Get product sales statistics
            $productStatsQuery = OrderDetail::select('product_id', DB::raw('SUM(quantity) as totalQuantity'), DB::raw('SUM(quantity * price) as totalRevenue'))
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('orders.status', 'PROCESSED')
                ->where('orders.deleted', false)
                ->with('product') // Eager load product information
                ->groupBy('product_id');

            // Apply date filters
            if (isset($queryParams['date'])) {
                $productStatsQuery->whereDate('orders.created_at', $startDate);
            } elseif (isset($queryParams['startDate']) && isset($queryParams['endDate'])) {
                $productStatsQuery->whereDate('orders.created_at', '>=', $startDate)
                    ->whereDate('orders.created_at', '<=', $endDate);
            } else {
                $productStatsQuery->whereDate('orders.created_at', '>=', $startDate)
                    ->whereDate('orders.created_at', '<=', $endDate);
            }

            $productStats = $productStatsQuery->get()->map(function ($item) {
                return [
                    'name' => $item->product->name ?? 'Unknown',
                    'totalQuantity' => (int)$item->totalQuantity,
                    'totalRevenue' => (int)$item->totalRevenue
                ];
            });

            // [STEP 4] - Get other summary statistics (unchanged)
            $totalProduct = Product::where('deleted', false)
                ->where('status', 'ACTIVE')
                ->count();

            $totalMaterial = Material::where('deleted', false)
                ->where('status', 'ACTIVE')
                ->count();

            $todayRevenue = Order::where('deleted', false)
                ->where('status', 'PROCESSED')
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

            // [STEP 5] - Generate revenue data for each date in range
            $revenueData = [];
            $currentDate = new DateTime($startDate);
            $endDateTime = new DateTime($endDate);

            while ($currentDate <= $endDateTime) {
                $dateStr = $currentDate->format('Y-m-d');

                // Get revenue for this specific date
                $revenue = Order::where('deleted', false)
                    ->where('status', 'PROCESSED')
                    ->whereDate('created_at', $dateStr)
                    ->sum('total_price');

                $revenueData[] = [
                    'date' => $currentDate->format('d/m/Y'),
                    'totalRevenue' => (int)$revenue
                ];

                $currentDate->modify('+1 day');
            }

            // [STEP 6] - Prepare response
            $response = [
                'summary' => [
                    'totalProduct' => (int)$totalProduct,
                    'totalMaterial' => (int)$totalMaterial,
                    'totalEmployee' => (int)$totalUser,
                    'totalCustomer' => (int)$totalCustomer,
                    'todayRevenue' => (int)$todayRevenue,
                    'pendingImports' => (int)$pendingImports
                ],
                'revenue' => $revenueData,
                'productStats' => $productStats
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