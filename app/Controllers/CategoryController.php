<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Material;
use App\Models\Product;
use App\Utils\CustomPaginator;
use App\Utils\PaginationTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CategoryController
{
    use PaginationTrait;

    public function getCategories(): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $category = Category::query()->where('status', '!=', 'DELETED')->with(['products', 'discounts', 'materials']);

        if (isset($_GET['status'])) {
            $status = urldecode($_GET['status']);
            $category->where('status', $status);
        }

        if (isset($_GET['name'])) {
            $name = urldecode($_GET['name']);
            $category->where('name', 'like', '%' . $name . '%');
        }

        if (isset($_GET['type'])) {
            $type = urldecode($_GET['type']);
            $category->where('type', $type);
        }

        return $this->paginateResults($category, $perPage, $page)->toArray();
    }

    public function getCategoryById($id)
    {
        $category = Category::query()->where('id', $id)
            ->with(['products', 'discounts', 'materials'])
            ->first();

        if (!$category) {
            return json_encode(['error' => 'Không tìm thấy']);
        }

        return json_encode($category->toArray());
    }

    public function getProductByCategory($id): array
    {
        $perPage = $_GET['per_page'] ?? 10;
        $page = $_GET['page'] ?? 1;

        $category = Category::findOrFail($id);
        $productsQuery = $category->products()
            ->with('categories')
            ->getQuery();

        return $this->paginateResults($productsQuery, $perPage, $page)->toArray();
    }

    public function addProductToCategory($id)
    {
        $category = Category::query()->where('id', $id)->first();
        $data = json_decode(file_get_contents('php://input'), true);
        $product = Product::query()->where('id', $data['product_id'])->first();
        $category->products()->attach($product);
        return 'Thêm thành công';
    }

    public function getDiscountByCategory($id): array
    {
        $perPage = $_GET['per_page'] ?? 15;
        $page = $_GET['page'] ?? 1;

        $category = Category::query()->where('id', $id)->firstOrFail();
        $discountsQuery = $category->discounts()
            ->with('categories')
            ->getQuery();

        return $this->paginateResults($discountsQuery, $perPage, $page)->toArray();
    }

    public function addDiscountToCategory($id)
    {
        $category = Category::query()->where('id', $id)->first();
        $data = json_decode(file_get_contents('php://input'), true);
        $discount = Discount::query()->where('id', $data['discount_id'])->first();
        $category->discounts()->attach($discount);
        return 'Thêm thành công';
    }

    public function getMaterialByCategory($id): array
    {
        $perPage = $_GET['per_page'] ?? 15;
        $page = $_GET['page'] ?? 1;

        $category = Category::query()->where('id', $id)->firstOrFail();
        $discountsQuery = $category->materials()
            ->with('categories')
            ->getQuery();

        return $this->paginateResults($discountsQuery, $perPage, $page)->toArray();
    }

    public function addMaterialToCategory($id)
    {
        $category = Category::query()->where('id', $id)->first();
        $data = json_decode(file_get_contents('php://input'), true);
        $material = Material::query()->where('id', $data['material_id'])->first();
        $category->materials()->attach($material);
        return 'Thêm thành công';
    }

    /**
     * @throws Exception
     */

    public function createCategory(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $category = new Category();
            $errors = $category->validate($data);

            if ($errors) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $category->fill($data);
            $category->save();

            return [
                'success' => true,
                'data' => $category->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in createCategory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function updateCategoryById($id): array
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $errors = $category->validate($data, true);

            if ($errors) {
                return [
                    'error' => 'Validation failed',
                    'details' => $errors
                ];
            }

            $category->fill($data);
            $category->save();

            return [
                'data' => $category->toArray()
            ];

        } catch (\Exception $e) {
            error_log("Error in updateCategoryById: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    public function deleteCategory($id): array
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return [
                    'error' => 'Không tìm thấy'
                ];
            }

            $category->deleted = true;
            $category->save();

            return [
                'message' => 'Xóa thành công'
            ];

        } catch (\Exception $e) {
            error_log("Error in deleteCategory: " . $e->getMessage());
            return [
                'error' => 'Database error occurred',
                'details' => $e->getMessage()
            ];
        }
    }
}