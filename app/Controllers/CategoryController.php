<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Material;
use App\Models\Product;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CategoryController
{
    public function getCategories(): Collection
    {
        $category = Category::query()->where('status', '!=' , 'DELETED');

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $category->where('status', $status);
        }

        if (isset($_GET['name'])) {
            $name = urldecode($_GET['name']);
            $category->where('name', 'like',  '%' . $name . '%');
        }

        if (isset($_GET['type'])) {
            $type = urldecode($_GET['type']);
            $category->where('type', $type);
        }

        return $category->get();
    }

    public function getCategoryById($id)
    {
        $category = Category::query()->where('id',$id)->first();
        return $category;
    }

    public function getProductByCategory($id)
    {
        $category = Category::query()->where('id',$id)->first();
        return $category->products;
    }

    public function addProductToCategory($id)
    {
        $category = Category::query()->where('id',$id)->first();
        $data = json_decode(file_get_contents('php://input'),true);
        $product = Product::query()->where('id',$data['product_id'])->first();
        $category->products()->attach($product);
        return 'Thêm thành công';
    }

    public function getDiscountByCategory($id)
    {
        $category = Category::query()->where('id',$id)->first();
        return $category->discounts;
    }

    public function addDiscountToCategory($id)
    {
        $category = Category::query()->where('id',$id)->first();
        $data = json_decode(file_get_contents('php://input'),true);
        $discount = Discount::query()->where('id',$data['discount_id'])->first();
        $category->discounts()->attach($discount);
        return 'Thêm thành công';
    }

    public function getMaterialByCategory($id)
    {
        $category = Category::query()->where('id',$id)->first();
        return $category->materials;
    }

    public function addMaterialToCategory($id)
    {
        $category = Category::query()->where('id',$id)->first();
        $data = json_decode(file_get_contents('php://input'),true);
        $material = Material::query()->where('id',$data['material_id'])->first();
        $category->materials()->attach($material);
        return 'Thêm thành công';
    }

    /**
     * @throws Exception
     */

    public function createCategory(): Model | string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $category = new Category();
        $errors = $category->validate($data);

        if ($errors) {
            http_response_code(422);
            return json_encode(["errors" => $errors]);
        }

        $category->fill($data);
        $category->save();

        http_response_code(201);
        return json_encode($category);
    }

    public function updateCategoryById($id): bool | int | string
    {
        $category = Category::find($id);

        if (!$category) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $errors = $category->validate($data, true);

        if ($errors) {
            http_response_code(422);
            return json_encode(["errors" => $errors]);
        }

        $category->fill($data);
        $category->save();

        http_response_code(200);
        return json_encode($category);
    }

    public function deleteCategory($id)
    {
        $category = Category::find($id);

        if ($category) {
            $category->status = 'DELETED';
            $category->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}