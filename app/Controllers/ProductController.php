<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductController
{
    public function countProducts()
    {
        $total = Product::where('status', 'IN_STOCK')->count();
        $result = ['total' => $total];
        return json_encode($result);
    }


    public function getProducts() : Collection
    {
        $product = Product::query()->where('status', '!=' , 'DELETED');

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $product->where('status', $status);
        }

        if (isset($_GET['name'])) {
            $name = urldecode($_GET['name']);
            $product->where('name', 'like', '%' . $name . '%');
        }

        if (isset($_GET['packing'])) {
            $packing = urldecode($_GET['packing']);
            $product->where('packing', 'like', '%' . $packing . '%');
        }

        if (isset($_GET['sku'])) {
            $sku = urldecode($_GET['sku']);
            $product->where('sku', 'like',  '%' . $sku . '%');
        }

        if (isset($_GET['quantity'])) {
            $quantity = urldecode($_GET['quantity']);
            $product->where('quantity', $quantity);
        }

        if (isset($_GET['weight'])) {
            $weight = urldecode($_GET['weight']);
            $product->where('weight', $weight);
        }

        if (isset($_GET['price'])) {
            $price = urldecode($_GET['price']);
            $product->where('price', $price);
        }

        if (isset($_GET['price_min'])) {
            $price_min = urldecode($_GET['price_min']);
            $product->where('price', '>=', $price_min);
        }

        if (isset($_GET['price_max'])) {
            $price_max = urldecode($_GET['price_max']);
            $product->where('price', '<=', $price_max);
        }

        return $product->get();
    }

    public function getProductById($id) : Model
    {
        $product = Product::query()->where('id',$id)->first();
        return $product;
    }

    public function getCategoryByProduct($id)
    {
        $product = Product::query()->where('id',$id)->first();
        return $product->categories;
    }

    public function addCategoryToProduct($id)
    {
        $product = Product::query()->where('id',$id)->first();
        $data = json_decode(file_get_contents('php://input'),true);
        $category = Category::query()->where('id',$data['category_id'])->first();
        $product->categories()->attach($category);
        return 'Thêm thành công';
    }

    public function getMaterialByProduct($id)
    {
        $product = Product::query()->where('id',$id)->first();
        return $product->materials;
    }

    public function getDiscountByProduct($id)
    {
        $product = Product::query()->where('id',$id)->first();
        return $product->discounts;
    }

    public function addDiscountToProduct($id)
    {
        $product = Product::query()->where('id',$id)->first();
        $data = json_decode(file_get_contents('php://input'),true);
        $discount = Discount::query()->where('id',$data['discount_id'])->first();
        $product->discounts()->attach($discount);
        return 'Thêm thành công';
    }

    public function getOrderByProduct($id)
    {
        $product = Product::query()->where('id',$id)->first();
        return $product->orders;
    }

    public function addOrderToProduct($id)
    {
        $product = Product::query()->where('id',$id)->first();
        $data = json_decode(file_get_contents('php://input'),true);
        $order = Order::query()->where('id',$data['order_id'])->first();
        $product->orders()->attach($order);
        return 'Thêm thành công';
    }

    public function getProductIventoryByProduct($id)
    {
        $product = Product::query()->where('id',$id)->first();
        return $product->inventories;
    }

    public function createProduct(): Model | string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $product = new Product();
        $error = $product->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $product->fill($data);
        $product->save();
        return $product;
    }

    public function updateProductById($id): bool | int | string
    {
        $product = Product::find($id);

        if (!$product) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $product->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $product->fill($data);
        $product->save();

        return $product;
    }

    public function deleteProduct($id)
    {
        $product = Product::find($id);

        if ($product) {
            $product->status = 'DELETED';
            $product->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
        //$results = Product::destroy($id);
        //$results === 0 && http_response_code(404);
        //return $results === 1 ? "Xóa thành công" : "Không tìm thấy";
    }
}

