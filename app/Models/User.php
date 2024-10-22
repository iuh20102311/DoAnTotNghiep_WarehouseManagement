<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

class User extends Model
{
    use HasFactory;

    protected $table = 'users';
    protected $fillable = ['role_id', 'email', 'email_verified_at', 'password', 'status', 'reset_password_token', 'token_expiry', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;


    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function createdMaterialExportReceipts()
    {
        return $this->hasMany(MaterialExportReceipt::class, 'created_by');
    }

    public function approvedMaterialExportReceipts()
    {
        return $this->hasMany(MaterialExportReceipt::class, 'approved_by');
    }

    public function createdMaterialImportReceipts()
    {
        return $this->hasMany(MaterialImportReceipt::class, 'created_by');
    }

    public function approvedMaterialImportReceipts()
    {
        return $this->hasMany(MaterialImportReceipt::class, 'approved_by');
    }

    public function receivedMaterialImportReceipts()
    {
        return $this->hasMany(MaterialImportReceipt::class, 'receiver_id');
    }

    public function createdProductExportReceipts()
    {
        return $this->hasMany(ProductExportReceipt::class, 'created_by');
    }

    public function approvedProductExportReceipts()
    {
        return $this->hasMany(ProductExportReceipt::class, 'approved_by');
    }

    public function createdProductImportReceipts()
    {
        return $this->hasMany(ProductImportReceipt::class, 'created_by');
    }

    public function approvedProductImportReceipts()
    {
        return $this->hasMany(ProductImportReceipt::class, 'approved_by');
    }

    public function receivedProductImportReceipts()
    {
        return $this->hasMany(ProductImportReceipt::class, 'receiver_id');
    }

    public function createdInventoryChecks()
    {
        return $this->hasMany(InventoryCheck::class, 'created_by');
    }

    public function inventoryHistory()
    {
        return $this->hasMany(InventoryHistory::class, 'created_by');
    }


    /**
     * @throws Exception
     */
    public function validate(array $data, bool $isUpdate = false): string
    {
        $rules = [
            'role_id' => ['required', 'integer'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
        ];

        $messages = [
            'role_id.required' => 'ID vai trò không được để trống.',
            'role_id.integer' => 'ID vai trò phải là một số nguyên.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
        ];

        if ($isUpdate) {
            $rules = array_intersect_key($rules, $data);
        }

        $translator = new Translator(new ArrayLoader(), 'en');
        $factory = new Factory($translator);
        $validator = $factory->make($data, $rules, $messages);

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        // Manually check for role existence
        if (isset($data['role_id'])) {
            $role = Role::find($data['role_id']);
            if (!$role) {
                return 'ID vai trò không tồn tại.';
            }
        }

        // Manually check for email uniqueness
        if (isset($data['email'])) {
            $existingUser = self::where('email', $data['email'])->first();
            if ($existingUser && (!$isUpdate || $existingUser->id != $this->id)) {
                return 'Email đã tồn tại.';
            }
        }

        return '';
    }
}