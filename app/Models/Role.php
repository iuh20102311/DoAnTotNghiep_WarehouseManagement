<?php

namespace App\Models;

use App\Utils\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Exception;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';
    protected $fillable = ['name', 'status', 'created_at', 'updated_at', 'deleted'];
    protected $primaryKey = 'id';
    public $timestamps = true;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function validate(array $data, $isUpdate = false)
    {
        $validator = new Validator($data, $this->messages());

        $rules = [
            'name' => ['required', 'max' => 100],
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

        // Validate unique name
        if (isset($data['name'])) {
            $query = self::where('name', $data['name'])
                ->where('deleted', false);

            if ($isUpdate) {
                $query->where('id', '!=', $this->id);
            }

            if ($query->exists()) {
                return ['name' => ['Tên vai trò đã tồn tại']];
            }
        }

        return null;
    }

    protected function messages()
    {
        return [
            'name' => [
                'required' => 'Tên vai trò là bắt buộc.',
                'max' => 'Tên vai trò không được vượt quá :max ký tự.'
            ],
            'status' => [
                'required' => 'Trạng thái là bắt buộc.',
                'enum' => 'Trạng thái phải là ACTIVE, INACTIVE hoặc DELETED.'
            ]
        ];
    }
}
