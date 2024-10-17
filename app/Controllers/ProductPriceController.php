<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductPriceController
{
    public function getProductPrices(): Collection
    {
        $productprices = ProductPrice::query()->where('status', '!=' , 'DISABLE');

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

        $productprices = $productprices->get();
        foreach ($productprices as $index => $productprice) {
            $product = Product::query()->where('id', $productprice->product_id)->first();
            unset($productprice->product_id);
            $productprice->product = $product;
        }

        return $productprices;
    }

    public function getProductPriceById($id) : ?Model
    {
        $productprice = ProductPrice::query()->where('id',$id)->first();
        $product = Product::query()->where('id',$productprice->product_id)->first();
        if ($productprice) {
            unset($productprice->product_id);
            $productprice->product = $product;
            return $productprice;
        } else {
            return null;
        }
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

