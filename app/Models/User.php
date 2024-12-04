<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    use HasFactory;

    protected $table = 'users';
    protected $fillable = ['role_id', 'email', 'email_verified_at', 'password', 'status', 'reset_password_token', 'token_expiry', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;


    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
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
        return $this->hasMany(MaterialStorageHistoryDetail::class, 'created_by');
    }


    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'role_id' => ['required', 'integer'],
            'email' => ['required', 'max' => 255, 'email'],
            'password' => ['required', 'min' => 6, 'max' => 255],
        ];

        if ($isUpdate) {
            foreach ($rules as $field => $constraints) {
                $rules[$field] = array_filter($constraints, fn($c) => $c !== 'required');
            }
        }

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        // Validate role existence
        if (isset($data['role_id'])) {
            $role = Role::where('deleted', false)->find($data['role_id']);
            if (!$role) {
                return ['role_id' => ['Vai trò không tồn tại']];
            }
        }

        // Validate unique email
        if (isset($data['email'])) {
            $query = self::where('email', $data['email'])
                ->where('deleted', false);

            if ($isUpdate) {
                $query->where('id', '!=', $this->id);
            }

            if ($query->exists()) {
                return ['email' => ['Email đã được sử dụng']];
            }
        }

        return null;
    }

    protected function messages()
    {
        return [
            'role_id' => [
                'required' => 'Vai trò người dùng là bắt buộc.',
                'integer' => 'Vai trò người dùng phải là số nguyên.'
            ],
            'email' => [
                'required' => 'Email là bắt buộc.',
                'max' => 'Email không được vượt quá :max ký tự.',
                'email' => 'Email không hợp lệ.'
            ],
            'password' => [
                'required' => 'Mật khẩu là bắt buộc.',
                'min' => 'Mật khẩu phải có ít nhất :min ký tự.',
                'max' => 'Mật khẩu không được vượt quá :max ký tự.'
            ],
            'reset_password_token' => [
                'max' => 'Token không được vượt quá :max ký tự.'
            ],
            'token_expiry' => [
                'date' => 'Thời gian hết hạn token không hợp lệ.'
            ],
            'email_verified_at' => [
                'date' => 'Thời gian xác thực email không hợp lệ.'
            ]
        ];
    }
}