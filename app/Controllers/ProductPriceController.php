<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductPriceController
{
    use PaginationTrait;

    public function getProductPrices(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $productprices = ProductPrice::query()->where('status', '!=' , 'DISABLE')
            ->with(['product'])
            ->orderByRaw("CASE 
                WHEN status = 'ACTIVE' THEN 1 
                ELSE 2 
                END")  // Sort ACTIVE status first
            ->orderBy('created_at', 'desc');

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $productprices->where('status', $status);
        }

        if (isset($_GET['price'])) {
            $price = urldecode($_GET['price']);
            $productprices->where('price', $price);
        }

        if (isset($_GET['price_min'])) {
            $price_min = urldecode($_GET['price_min']);
            $productprices->where('price', '>=', $price_min);
        }

        if (isset($_GET['price_max'])) {
            $price_max = urldecode($_GET['price_max']);
            $productprices->where('price', '<=', $price_max);
        }

        return $this->paginateResults($productprices, $perPage, $page)->toArray();

    }

    public function getProductPriceById($id) : false|string
    {
        $productprice = ProductPrice::query()->where('id',$id)
            ->with(['product'])
            ->first();

        if (!$productprice) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($productprice->toArray());
    }

    public function getProductsByProductPrice($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $productprice = ProductPrice::query()->where('id', $id)->firstOrFail();
        $productsQuery = $productprice->product()
            ->getQuery();

        return $this->paginateResults($productsQuery, $perPage, $page)->toArray();
    }

    public function createProductPrice(): Model | string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $productprice = new ProductPrice();
        $error = $productprice->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $productprice->fill($data);
        $productprice->save();
        return $productprice;
    }

    public function updateProductPriceById($id): bool | int | string
    {
        $productprice = ProductPrice::find($id);

        if (!$productprice) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $productprice->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $productprice->fill($data);
        $productprice->save();

        return $productprice;
    }

    public function deleteProductPrice($id)
    {
        $productprice = ProductPrice::find($id);

        if ($productprice) {
            $productprice->status = 'DISABLE';
            $productprice->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}

