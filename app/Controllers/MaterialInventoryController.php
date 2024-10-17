<?php

namespace App\Controllers;

use App\Models\Material;
use App\Models\MaterialInventory;
use App\Models\Provider;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MaterialInventoryController
{
    public function getMaterialInventories() : Collection
    {
        $materialinventories = MaterialInventory::query()->where('status', '!=' , 'DISABLE')->where('status', '!=' , 'DISABLE')->orderBy('quantity_available', 'asc');

        if (isset($_GET['quantity_available'])) {
            $quantity_available = urldecode($_GET['quantity_available']);
            $materialinventories->where('quantity_available', $quantity_available);
        }

        if (isset($_GET['minimum_stock_level'])) {
            $minimum_stock_level = urldecode($_GET['minimum_stock_level']);
            $materialinventories->where('minimum_stock_level', $minimum_stock_level);
        }

        $materialinventories = $materialinventories->get();
        foreach ($materialinventories as $index => $materialinventory) {
            $provider = Provider::query()->where('id', $materialinventory->provider_id)->first();
            unset($materialinventory->provider_id);
            $materialinventory->provider = $provider;


            $material = Material::query()->where('id',$materialinventory->material_id)->first();
            unset($materialinventory->material_id);
            $materialinventory->Material = $material;

            $warehouse = Warehouse::query()->where('id', $materialinventory->warehouse_id)->first();
            unset($materialinventory->warehouse_id);
            $materialinventory->warehouse = $warehouse;
        }
        return $materialinventories;
    }

    public function getMaterialInventoryById($id) : ?Model
    {
        $materialinventory = MaterialInventory::query()->where('id', $id)->first();

        if (!$materialinventory) {
            return null;
        }

        $provider = Provider::query()->where('id', $materialinventory->provider_id)->first();
        $material = Material::query()->where('id', $materialinventory->material_id)->first();
        $warehouse = Warehouse::query()->where('id', $materialinventory->warehouse_id)->first();
        unset($materialinventory->material_id, $materialinventory->warehouse_id, $materialinventory->provider_id);
        $materialinventory->provider = $provider;
        $materialinventory->material = $material;
        $materialinventory->warehouse = $warehouse;
        return $materialinventory;
    }

    public function createMaterialInventory(): Model | string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $materialinventory = new MaterialInventory();
        $error = $materialinventory->validate($data);
        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }
        $materialinventory->fill($data);
        $materialinventory->save();
        return $materialinventory;
    }

    public function updateMaterialInventoryById($id): bool | int | string
    {
        $materialinventory = MaterialInventory::find($id);

        if (!$materialinventory) {
            http_response_code(404);
            return json_encode(["error" => "Provider not found"]);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $error = $materialinventory->validate($data, true);

        if ($error != "") {
            http_response_code(404);
            error_log($error);
            return json_encode(["error" => $error]);
        }

        $materialinventory->fill($data);
        $materialinventory->save();

        return $materialinventory;
    }

    public function deleteMaterialInventory($id)
    {
        $materialinventory = MaterialInventory::find($id);

        if ($materialinventory) {
            $materialinventory->status = 'DISABLE';
            $materialinventory->save();
            return "Xóa thành công";
        }
        else {
            http_response_code(404);
            return "Không tìm thấy";
        }
    }
}

 