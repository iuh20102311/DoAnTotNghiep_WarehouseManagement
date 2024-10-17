<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DiscountController
{
    public function getDiscounts(): Collection
    {
        $discount = Discount::query()->where('status', '!=' , 'DELETED');

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $discount->where('status', $status);
        }

        if (isset($_GET['coupon_code'])) {
            $coupon_code = urldecode($_GET['coupon_code']);
            $discount->where('coupon_code', $coupon_code);
        }

        if (isset($_GET['discount_value'])) {
            $discount_value = urldecode($_GET['discount_value']);
            $discount->where('discount_value', $discount_value);
        }

        return $discount->get();
    }

    public function getDiscountById($id) : Model
    {
        $discount = Discount::query()->where('id',$id)->first();
        return $discount;
    }

    public function getProductByDiscount($id)
    {
        $discount = Discount::query()->where('id',$id)->first();
        return $discount->products;
    }

    public function addProductToDiscount($id)
    {
        $discount = Discount::query()->where('id',$id)->first();
        $data = json_decode(file_get_contents('php://input'),true);
        $product = Product::query()->where('id',$data['product_id'])->first();
        $discount->products()->attach($product);
        return 'Thêm thành công';
    }

    public function getCategoryByDiscount($id)
    {
        $discount = Discount::query()->where('id',$id)->first();
        return $discount->categories;
    }

    public function addCategoryToDiscount($id)
    {
        $discount = Discount::query()->where('id',$id)->first();
        $data = json_decode(file_get_contents('php://input'),true);
        $category = Category::query()->where('id',$data['category_id'])->first();
        $discount->categories()->attach($category);
        return 'Thêm thành công';
    }

    public function createDiscount(): Model | string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $discount = new Discount();

        $errors = $discount->validate($data);

        if ($errors) {
            http_response_code(422);
            return json_encode(["errors" => $errors]);
        }

        $discount->fill($data);
        $discount->save();

        http_response_code(201);
        return json_encode($discount);
    }

    public function updateDiscountById($id): bool | int | string
    {
        $discount = Discount::find($id);

        if (!$discount) {
            http_response_code(404);
            return json_encode(["error" => "Discount not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $errors = $discount->validate($data, true);

        if ($errors) {
            http_response_code(422);
            return json_encode(["errors" => $errors]);
        }

        $discount->fill($data);
        $discount->save();

        http_response_code(200);
        return json_encode($discount);
    }

    public function deleteDiscount($id)
    {
        $discount = Discount::find($id);

        if ($discount) {
            $discount->status = 'DELETED';
            $discount->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}