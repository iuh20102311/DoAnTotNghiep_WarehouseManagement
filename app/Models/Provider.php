<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class Provider extends Model
{
    use HasFactory;
    protected $table = 'providers';
    protected $fillable = ['name', 'website', 'address', 'city', 'district', 'ward', 'phone', 'email', 'note', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'provider_materials');
    }

    public function materialImportReceipts(): HasMany
    {
        return $this->hasMany(MaterialImportReceipt::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'name' => ['required', 'max' => 255],
            'website' => ['required', 'url'],
            'address' => ['required', 'max' => 255],
            'city' => ['required', 'max' => 100],
            'district' => ['required', 'max' => 100],
            'ward' => ['required', 'max' => 100],
            'phone' => ['required', 'max' => 15],
            'email' => ['required', 'max' => 255, 'email'],
            'note' => ['max' => 1000],
            'status' => ['required', 'enum' => ['ACTIVE', 'INACTIVE', 'DELETED']]
        ];

        if ($isUpdate) {
            // Chỉ validate các trường có trong request
            $rules = array_intersect_key($rules, $data);

            // Bỏ qua validate required
            foreach ($rules as $field => $constraints) {
                $rules[$field] = array_filter($constraints, fn($c) => $c !== 'required');
            }
        }

        if (!$validator->validate($rules)) {
            return $validator->getErrors();
        }

        // Validate unique phone and email
        if (isset($data['phone'])) {
            $query = self::where('phone', $data['phone'])
                ->where('deleted', false);

            if ($isUpdate) {
                $query->where('id', '!=', $this->id);
            }

            if ($query->exists()) {
                return ['phone' => ['Số điện thoại đã được sử dụng']];
            }
        }

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
            'name' => [
                'required' => 'Tên nhà cung cấp là bắt buộc.',
                'max' => 'Tên nhà cung cấp không được vượt quá :max ký tự.'
            ],
            'website' => [
                'max' => 'Website không được vượt quá :max ký tự.',
                'url' => 'Website không hợp lệ.'
            ],
            'address' => [
                'required' => 'Địa chỉ là bắt buộc.',
                'max' => 'Địa chỉ không được vượt quá :max ký tự.'
            ],
            'city' => [
                'required' => 'Thành phố là bắt buộc.',
                'max' => 'Thành phố không được vượt quá :max ký tự.'
            ],
            'district' => [
                'required' => 'Quận/Huyện là bắt buộc.',
                'max' => 'Quận/Huyện không được vượt quá :max ký tự.'
            ],
            'ward' => [
                'required' => 'Phường/Xã là bắt buộc.',
                'max' => 'Phường/Xã không được vượt quá :max ký tự.'
            ],
            'phone' => [
                'required' => 'Số điện thoại là bắt buộc.',
                'max' => 'Số điện thoại không được vượt quá :max ký tự.',
            ],
            'email' => [
                'required' => 'Email là bắt buộc.',
                'max' => 'Email không được vượt quá :max ký tự.',
                'email' => 'Email không hợp lệ.'
            ],
            'note' => [
                'max' => 'Ghi chú không được vượt quá :max ký tự.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE, INACTIVE hoặc DELETED.'
            ]
        ];
    }
}