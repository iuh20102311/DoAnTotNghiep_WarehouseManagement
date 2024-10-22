<?php

namespace App\Utils;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CustomPaginator extends LengthAwarePaginator
{
    private function toSnakeCase($input)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    public function toArray()
    {
        $items = $this->items->map(function ($item) {
            if (method_exists($item, 'toArray')) {
                $array = $item->toArray();
                // Include loaded relationships
                foreach ($item->getRelations() as $relation => $model) {
                    $snakeRelation = $this->toSnakeCase($relation);
                    if (!isset($array[$snakeRelation])) {
                        if ($model instanceof Collection) {
                            // Handle many-to-many relationships
                            $array[$snakeRelation] = $model->map(function ($relatedItem) {
                                return $relatedItem->makeHidden('pivot')->toArray();
                            })->all();
                        } elseif (is_array($model)) {
                            $array[$snakeRelation] = array_map(function ($relatedItem) {
                                return $relatedItem->makeHidden('pivot')->toArray();
                            }, $model);
                        } else {
                            $array[$snakeRelation] = $model ? $model->makeHidden('pivot')->toArray() : null;
                        }
                    }
                    // Remove camelCase version if snake_case exists
                    if ($relation !== $snakeRelation && isset($array[$relation])) {
                        unset($array[$relation]);
                    }
                }
                // Remove redundant foreign keys and other fields if needed
                // $fieldsToRemove = ['product_id', 'category_id', 'discount_id', 'material_id', 'created_by', 'provider_id',
                //                    'receiver_id', 'approved_by', 'material_export_receipt_id', 'storage_area_id', 'receipt_id',
                //                    'material_import_receipt_id' , 'customer_id'];
                //  foreach ($fieldsToRemove as $field) {
                //  if (isset($array[$field])) {
                //  unset($array[$field]);
                //  }
                //  }
                return $array;
            }
            return (array)$item;
        })->toArray();

        return [
            'current_page' => $this->currentPage(),
            'data' => $items,
            'last_page' => $this->lastPage(),
            'per_page' => $this->perPage(),
            'total' => $this->total(),
        ];
    }
}