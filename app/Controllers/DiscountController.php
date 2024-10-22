<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Utils\PaginationTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DiscountController
{
    use PaginationTrait;

    public function getDiscounts(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $discount = Discount::query()->where('status', '!=' , 'DELETED')->with(['categories', 'products']);

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

        return $this->paginateResults($discount, $perPage, $page)->toArray();
    }

    public function getDiscountById($id) : string
    {
        $discount = Discount::query()->where('id',$id)
            ->with(['categories', 'products'])
            ->first();

        if (!$discount) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($discount->toArray());
    }

    public function getProductByDiscount($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $discount = Discount::query()->where('id', $id)->firstOrFail();
        $productsQuery = $discount->products()->with(['discounts'])->getQuery();

        return $this->paginateResults($productsQuery, $perPage, $page)->toArray();
    }

    public function addProductToDiscount($id): string
    {
        $discount = Discount::query()->where('id',$id)->first();
        $data = json_decode(file_get_contents('php://input'),true);
        $product = Product::query()->where('id',$data['product_id'])->first();
        $discount->products()->attach($product);
        return 'Thêm thành công';
    }

    public function getCategoryByDiscount($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $discount = Discount::query()->where('id', $id)->firstOrFail();
        $categoriesQuery = $discount->categories()->with(['discounts'])->getQuery();

        return $this->paginateResults($categoriesQuery, $perPage, $page)->toArray();
    }

    public function addCategoryToDiscount($id): string
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

    public function deleteDiscount($id): string
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