<?php

namespace App\Utils;

use DateTime;

class Validator
{
    protected $errors = [];
    protected $data;
    protected $messages = [];

    public function __construct(array $data, array $messages = [])
    {
        $this->data = $data;
        $this->messages = $messages;
    }

    public function validate(array $rules)
    {
        foreach ($rules as $field => $fieldRules) {
            $this->applyRules($field, $fieldRules);
        }
        return empty($this->errors);
    }

    protected function applyRules($field, $fieldRules)
    {
        $value = $this->data[$field] ?? null;
        foreach ($fieldRules as $rule => $parameter) {
            if (is_numeric($rule)) {
                $rule = $parameter;
                $parameter = null;
            }
            $ruleParts = explode('_', $rule);
            $rulePascalCase = '';
            foreach ($ruleParts as $part) {
                $rulePascalCase .= ucfirst($part);
            }
            $method = "validate" . $rulePascalCase;

            if (method_exists($this, $method)) {
                if (!$this->$method($field, $value, $parameter)) {
                    break; // Nếu muốn tiếp tục kiểm tra tất cả các quy tắc, hãy xóa dòng này
                }
            } else {
                throw new \Exception("Validation rule '{$rule}' is not defined.");
            }
        }
    }

    public function validateRequired($field, $value)
    {
        if (empty($value) && $value !== '0') {
            $this->addError($field, 'required');
            return false;
        }
        return true;
    }

    public function validateEmail($field, $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email');
            return false;
        }
        return true;
    }

    public function validateString($field, $value)
    {
        if (!is_string($value)) {
            $this->addError($field, 'string');
            return false;
        }
        return true;
    }

    public function validateUnique($field, $value, $parameter)
    {
        $model = $parameter[0];
        $column = $parameter[1] ?? $field;
        if ($model::where($column, $value)->exists()) {
            $this->addError($field, 'unique');
            return false;
        }
        return true;
    }

    public function validateMin($field, $value, $parameter)
    {
        if (is_string($value)) {
            if (strlen($value) < $parameter) {
                $this->addError($field, 'min_string', ['min' => $parameter]);
                return false;
            }
        } elseif (is_numeric($value)) {
            if ($value < $parameter) {
                $this->addError($field, 'min_numeric', ['min' => $parameter]);
                return false;
            }
        }
        return true;
    }

    public function validateMax($field, $value, $parameter)
    {
        if (is_string($value)) {
            if (strlen($value) > $parameter) {
                $this->addError($field, 'max_string', ['max' => $parameter]);
                return false;
            }
        } elseif (is_numeric($value)) {
            if ($value > $parameter) {
                $this->addError($field, 'max_numeric', ['max' => $parameter]);
                return false;
            }
        }
        return true;
    }

    public function validateDate($field, $value, $format = 'Y-m-d')
    {
        $date = DateTime::createFromFormat($format, $value);
        if (!$date || $date->format($format) !== $value) {
            $this->addError($field, 'date', ['format' => $format]);
            return false;
        }
        return true;
    }

    public function validateInteger($field, $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, 'integer');
            return false;
        }
        return true;
    }

    public function validateEnum($field, $value, $parameter)
    {
        if (!in_array($value, $parameter)) {
            $this->addError($field, 'enum', ['values' => implode(', ', $parameter)]);
            return false;
        }
        return true;
    }

    public function validateNoSpecialChars($field, $value)
    {
        if (preg_match('/[!@#$%^&*(),.?":{}|<>]/', $value)) {
            $this->addError($field, 'no_special_chars');
            return false;
        }
        return true;
    }

    public function validateNoEmoji($field, $value)
    {
        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        if (preg_match($emojiPattern, $value)) {
            $this->addError($field, 'no_emoji');
            return false;
        }
        return true;
    }

    public function validateNoWhitespace($field, $value)
    {
        if (is_string($value) && trim($value) !== $value) {
            $this->addError($field, 'no_whitespace');
            return false;
        }
        return true;
    }
    
    public function validateCapitalizedWords($field, $value)
    {
        $words = explode(' ', $value);
        foreach ($words as $word) {
            if ($word !== '' && !ctype_upper($word[0])) {
                $this->addError($field, 'capitalized_words');
                return false;
            }
        }
        return true;
    }

    public function validateDatetime($field, $value, $format = 'Y-m-d H:i:s')
    {
        if (empty($value)) {
            return true; // Bỏ qua nếu giá trị rỗng (sẽ được xử lý bởi rule required)
        }

        // Chuyển đổi string sang datetime object
        $date = DateTime::createFromFormat($format, $value);
        $lastErrors = DateTime::getLastErrors();

        // Kiểm tra xem parse có thành công và không có warning/errors
        if ($date && $lastErrors['warning_count'] === 0 && $lastErrors['error_count'] === 0) {
            return true;
        }

        $this->addError($field, 'datetime', ['format' => $format]);
        return false;
    }

    public function validateAfter($field, $value, $parameter)
    {
        // $parameter có thể là một ngày cụ thể hoặc tên của field khác
        try {
            $dateToValidate = new DateTime($value);

            if (isset($this->data[$parameter])) {
                // So sánh với field khác
                $compareDate = new DateTime($this->data[$parameter]);
            } else {
                // So sánh với ngày cụ thể
                $compareDate = new DateTime($parameter);
            }

            if ($dateToValidate <= $compareDate) {
                $this->addError($field, 'after', ['date' => $parameter]);
                return false;
            }
            return true;
        } catch (\Exception $e) {
            $this->addError($field, 'invalid_date');
            return false;
        }
    }

    public function validateAfterOrEqual($field, $value, $parameter)
    {
        try {
            $dateToValidate = new DateTime($value);

            if (isset($this->data[$parameter])) {
                // So sánh với field khác
                $compareDate = new DateTime($this->data[$parameter]);
            } else {
                // So sánh với ngày cụ thể
                $compareDate = new DateTime($parameter);
            }

            if ($dateToValidate < $compareDate) {
                $this->addError($field, 'after_or_equal', ['date' => $parameter]);
                return false;
            }
            return true;
        } catch (\Exception $e) {
            $this->addError($field, 'invalid_date');
            return false;
        }
    }

    protected function addError($field, $rule, $parameters = [])
    {
        $message = $this->messages[$field][$rule] ?? $this->getDefaultMessage($field, $rule);
        foreach ($parameters as $key => $value) {
            $message = str_replace(':' . $key, $value, $message);
        }
        $this->errors[$field][] = $message;
    }

    protected function getDefaultMessage($field, $rule)
    {
        $messages = [
            'required' => ':field là bắt buộc.',
            'email' => ':field phải là email hợp lệ.',
            'string' => ':field phải là chuỗi.',
            'unique' => ':field đã tồn tại.',
            'min_string' => ':field phải có ít nhất :min ký tự.',
            'min_numeric' => ':field phải lớn hơn hoặc bằng :min.',
            'max_string' => ':field không được vượt quá :max ký tự.',
            'max_numeric' => ':field phải nhỏ hơn hoặc bằng :max.',
            'enum' => ':field phải là một trong các giá trị: :values.',
            'date' => ':field phải là ngày hợp lệ theo định dạng :format.',
            'datetime' => ':field phải là ngày giờ hợp lệ theo định dạng :format.',
            'integer' => ':field phải là số nguyên.',
            'no_whitespace' => ':field không được chứa khoảng trắng.',
            'no_special_chars' => ':field không được chứa ký tự đặc biệt.',
            'no_emoji' => ':field không được chứa emoji.',
            'capitalized_words' => 'Mỗi từ trong :field phải bắt đầu bằng chữ cái viết hoa.',
            'after' => ':field phải sau ngày :date.',
            'after_or_equal' => ':field phải sau hoặc bằng ngày :date.',
            'invalid_date' => ':field không phải là ngày hợp lệ.',
        ];
        return str_replace(':field', $field, $messages[$rule] ?? ':field không hợp lệ.');
    }

    public function getErrors()
    {
        return $this->errors;
    }
}