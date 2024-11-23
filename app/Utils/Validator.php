<?php

namespace App\Utils;

use DateTime;
use Illuminate\Support\Facades\DB;

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

            // Xử lý các rule có parameter được định nghĩa theo format "rule:param1,param2"
            if (is_string($rule) && strpos($rule, ':') !== false) {
                list($ruleName, $parameter) = explode(':', $rule, 2);
                $rule = $ruleName;
            }

            $ruleParts = explode('_', $rule);
            $rulePascalCase = '';
            foreach ($ruleParts as $part) {
                $rulePascalCase .= ucfirst($part);
            }
            $method = "validate" . $rulePascalCase;

            if (method_exists($this, $method)) {
                if (!$this->$method($field, $value, $parameter)) {
                    break;
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

    public function validateNullable($field, $value)
    {
        // Nếu field có thể null và giá trị là null hoặc rỗng thì cho qua
        if (empty($value) && $value !== '0') {
            // Remove any existing errors for this field since it's nullable
            if (isset($this->errors[$field])) {
                unset($this->errors[$field]);
            }
            return false; // Return false để dừng việc validate các rule khác
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
        $excludeId = $parameter[2] ?? null;

        if ($excludeId) {
            // Kiểm tra xem giá trị có thay đổi không
            $currentRecord = $model::find($excludeId);
            if ($currentRecord && $currentRecord->$column === $value) {
                return true; // Nếu giá trị không thay đổi, bỏ qua validate unique
            }
        }

        $query = $model::where($column, $value);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
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

    public function validateNumeric($field, $value)
    {
        // Kiểm tra xem giá trị có phải là số (integer hoặc float) hay string chứa số
        if (!is_numeric($value)) {
            $this->addError($field, 'numeric');
            return false;
        }
        return true;
    }

    public function validatePositive($field, $value)
    {
        if (!is_numeric($value) || $value <= 0) {
            $this->addError($field, 'positive');
            return false;
        }
        return true;
    }

    public function validateNegative($field, $value)
    {
        if (!is_numeric($value) || $value >= 0) {
            $this->addError($field, 'negative');
            return false;
        }
        return true;
    }

    public function validateDecimal($field, $value, $parameter)
    {
        // Parameter là số chữ số thập phân mong muốn
        if (!is_numeric($value)) {
            $this->addError($field, 'numeric');
            return false;
        }

        // Chuyển về string để đếm số chữ số thập phân
        $decimal = explode('.', (string)$value);
        if (isset($decimal[1]) && strlen($decimal[1]) > $parameter) {
            $this->addError($field, 'decimal', ['decimal' => $parameter]);
            return false;
        }
        return true;
    }

    public function validateBetween($field, $value, $parameter)
    {
        if (!is_numeric($value)) {
            $this->addError($field, 'numeric');
            return false;
        }

        list($min, $max) = explode(',', $parameter);
        if ($value < $min || $value > $max) {
            $this->addError($field, 'between', ['min' => $min, 'max' => $max]);
            return false;
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
            // Parse date cần validate
            $dateToValidate = new DateTime($value);

            if (isset($this->data[$parameter])) {
                // So sánh với field khác
                $compareDate = new DateTime($this->data[$parameter]);
            } else {
                // So sánh với ngày cụ thể
                $compareDate = new DateTime($parameter);
            }

            // Nếu format của date chỉ là Y-m-d, reset time về 00:00:00
            if (strlen($value) <= 10) { // Y-m-d có độ dài là 10
                $dateToValidate->setTime(0, 0, 0);
            }
            if (strlen($parameter) <= 10 || (isset($this->data[$parameter]) && strlen($this->data[$parameter]) <= 10)) {
                $compareDate->setTime(0, 0, 0);
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

    public function validateXor($field, $value, $parameter)
    {
        $otherField = $parameter;
        $otherValue = $this->data[$otherField] ?? null;

        // Both fields are set or both are empty
        if ((!empty($value) && !empty($otherValue)) ||
            (empty($value) && empty($otherValue))) {
            $this->addError($field, 'xor', ['other' => $otherField]);
            return false;
        }
        return true;
    }

    public function validateUrl($field, $value, $parameter = null)
    {
        // Bỏ qua validate nếu giá trị rỗng (sẽ được xử lý bởi rule required)
        if (empty($value)) {
            return true;
        }

        // Kiểm tra URL có protocol hợp lệ không
        $validProtocols = ['http', 'https', 'ftp'];

        // Nếu có parameter được truyền vào, sử dụng nó làm danh sách protocol
        if ($parameter) {
            $validProtocols = explode(',', $parameter);
        }

        // Parse URL để kiểm tra các thành phần
        $urlParts = parse_url($value);

        // Kiểm tra xem URL có đúng format và có protocol hợp lệ không
        if (!$urlParts ||
            !isset($urlParts['scheme']) ||
            !isset($urlParts['host']) ||
            !in_array(strtolower($urlParts['scheme']), $validProtocols) ||
            !filter_var($value, FILTER_VALIDATE_URL)
        ) {
            if ($parameter) {
                $this->addError($field, 'url_with_protocols', ['protocols' => implode(', ', $validProtocols)]);
            } else {
                $this->addError($field, 'url');
            }
            return false;
        }

        return true;
    }

    public function validateArray($field, $value)
    {
        if (!is_array($value)) {
            $this->addError($field, 'array');
            return false;
        }
        return true;
    }

    public function validateArrayOf($field, $value, $type)
    {
        if (!$this->validateArray($field, $value)) {
            return false;
        }

        foreach ($value as $index => $item) {
            $valid = true;
            switch ($type) {
                case 'integer':
                    $valid = filter_var($item, FILTER_VALIDATE_INT) !== false;
                    break;
                case 'numeric':
                    $valid = is_numeric($item);
                    break;
                case 'string':
                    $valid = is_string($item);
                    break;
                case 'boolean':
                    $valid = is_bool($item);
                    break;
                default:
                    throw new \Exception("Unsupported array type validation: {$type}");
            }

            if (!$valid) {
                $this->addError($field, 'array_of', ['type' => $type, 'index' => $index]);
                return false;
            }
        }
        return true;
    }

    public function validateArraySize($field, $value, $size)
    {
        if (!$this->validateArray($field, $value)) {
            return false;
        }

        if (count($value) !== (int)$size) {
            $this->addError($field, 'array_size', ['size' => $size]);
            return false;
        }
        return true;
    }

    public function validateArrayMin($field, $value, $min)
    {
        if (!$this->validateArray($field, $value)) {
            return false;
        }

        if (count($value) < (int)$min) {
            $this->addError($field, 'array_min', ['min' => $min]);
            return false;
        }
        return true;
    }

    public function validateArrayMax($field, $value, $max)
    {
        if (!$this->validateArray($field, $value)) {
            return false;
        }

        if (count($value) > (int)$max) {
            $this->addError($field, 'array_max', ['max' => $max]);
            return false;
        }
        return true;
    }

    public function validateArrayUnique($field, $value)
    {
        if (!$this->validateArray($field, $value)) {
            return false;
        }

        if (count($value) !== count(array_unique($value))) {
            $this->addError($field, 'array_unique');
            return false;
        }
        return true;
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
            'exists' => ':field không tồn tại trong hệ thống.',
            'xor' => 'Chỉ được chọn một trong hai trường :field hoặc :other.',
            'numeric' => ':field phải là một số.',
            'positive' => ':field phải là số dương.',
            'negative' => ':field phải là số âm.',
            'decimal' => ':field chỉ được có tối đa :decimal chữ số thập phân.',
            'between' => ':field phải nằm trong khoảng từ :min đến :max.',
            'url' => ':field phải là URL hợp lệ.',
            'url_with_protocols' => ':field phải là URL hợp lệ và sử dụng một trong các giao thức sau: :protocols.',
            'array' => ':field phải là một mảng.',
            'array_of' => 'Phần tử tại vị trí :index trong :field phải là kiểu :type.',
            'array_size' => ':field phải có chính xác :size phần tử.',
            'array_min' => ':field phải có ít nhất :min phần tử.',
            'array_max' => ':field không được vượt quá :max phần tử.',
            'array_unique' => 'Các phần tử trong :field phải là duy nhất.',
        ];
        return str_replace(':field', $field, $messages[$rule] ?? ':field không hợp lệ.');
    }

    public function getErrors()
    {
        return $this->errors;
    }
}