<?php
namespace Fzr;

/**
 * フォーム処理
 */
class Form {
    protected array $data = [];
    protected array $errors = [];
    protected ?string $name = null;

    public function __construct(?string $name = null, ?array $data = null) {
        $this->name = $name;
        $this->data = $data ?? $_POST;
    }

    public function getData(): array { return $this->data; }
    public function getErrors(): array { return $this->errors; }
    public function hasErrors(): bool { return !empty($this->errors); }
    public function isValid(): bool { return empty($this->errors); }

    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    public function addError(string $field, string $message): void {
        $this->errors[$field][] = $message;
    }

    public function getError(string $field): ?string {
        return $this->errors[$field][0] ?? null;
    }

    public function validate(array $rules, ?array $labels = null): bool {
        $validator = new FormValidator($this->data, $rules, $labels);
        if (!$validator->validate()) {
            $this->errors = $validator->getErrors();
            return false;
        }
        return true;
    }

    /**
     * Attr\Field アノテーションからバリデーションルールを自動生成して実行する。
     *
     * 使用例:
     *   class UserForm {
     *       #[Label('ユーザー名')]
     *       #[Required]
     *       #[MaxLength(50)]
     *       public string $name;
     *
     *       #[Label('メール')]
     *       #[Required, Email]
     *       public string $email;
     *   }
     *   $form = new Form(null, $_POST);
     *   if ($form->validateFromObject(new UserForm())) { ... }
     */
    public function validateFromObject(object $obj): bool {
        $refClass = new \ReflectionClass($obj);
        $rules  = [];
        $labels = [];

        $attrClasses = [
            \Fzr\Attr\Field\Required::class,
            \Fzr\Attr\Field\MaxLength::class,
            \Fzr\Attr\Field\MinLength::class,
            \Fzr\Attr\Field\Email::class,
            \Fzr\Attr\Field\Numeric::class,
            \Fzr\Attr\Field\Integer::class,
            \Fzr\Attr\Field\Url::class,
            \Fzr\Attr\Field\Regex::class,
            \Fzr\Attr\Field\In::class,
            \Fzr\Attr\Field\NotIn::class,
            \Fzr\Attr\Field\Between::class,
            \Fzr\Attr\Field\Confirmed::class,
            \Fzr\Attr\Field\SameAs::class,
            \Fzr\Attr\Field\Date::class,
        ];

        foreach ($refClass->getProperties() as $prop) {
            $name = $prop->getName();

            $labelAttrs = $prop->getAttributes(\Fzr\Attr\Field\Label::class);
            $labels[$name] = !empty($labelAttrs) ? $labelAttrs[0]->newInstance()->label : $name;

            $fieldRules = [];
            foreach ($attrClasses as $attrClass) {
                $attrs = $prop->getAttributes($attrClass);
                if (!empty($attrs)) {
                    $fieldRules = array_merge($fieldRules, $attrs[0]->newInstance()->toValidation());
                }
            }
            if (!empty($fieldRules)) {
                $rules[$name] = $fieldRules;
            }
        }

        return empty($rules) ? true : $this->validate($rules, $labels);
    }
}

/**
 * バリデーター
 *
 * エラーメッセージは env.ini の [validation] セクションで上書き可能。
 *   [validation]
 *   required  = ":field is required."
 *   maxLength = ":field must be at most :len characters."
 */
class FormValidator {
    protected array $data;
    protected array $rules;
    protected ?array $labels;
    protected array $errors = [];

    private const ENV_PFX = 'validation.';

    /** Default messages (English fallback) */
    private const DEFAULT_MESSAGES = [
        'required'   => ':field is required.',
        'max'        => ':field must be at most :len characters.',
        'min'        => ':field must be at least :len characters.',
        'maxLength'  => ':field must be at most :len characters.',
        'minLength'  => ':field must be at least :len characters.',
        'email'      => ':field must be a valid email address.',
        'numeric'    => ':field must be numeric.',
        'integer'    => ':field must be an integer.',
        'url'        => ':field must be a valid URL.',
        'regex'      => ':field format is invalid.',
        'match'      => ':field does not match.',
        'in'         => 'Invalid value for :field.',
        'notIn'      => 'Invalid value for :field.',
        'between'    => ':field must be between :min and :max.',
        'confirmed'  => ':field confirmation does not match.',
        'date'       => ':field must be a valid date.',
    ];

    public function __construct(array $data, array $rules, ?array $labels = null) {
        $this->data = $data;
        $this->rules = $rules;
        $this->labels = $labels;
    }

    /** env.ini の validation.* からメッセージを取得し、プレースホルダを置換する */
    protected function msg(string $rule, array $vars = []): string {
        $template = Env::get(self::ENV_PFX . $rule, self::DEFAULT_MESSAGES[$rule] ?? $rule);
        foreach ($vars as $k => $v) {
            $template = str_replace(':' . $k, (string)$v, $template);
        }
        return $template;
    }

    public function validate(): bool {
        foreach ($this->rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;
            $label = $this->labels[$field] ?? $field;

            foreach ($fieldRules as $rule => $param) {
                if (is_int($rule)) { $rule = $param; $param = true; }
                $errorMsg = $this->applyRule($field, $label, $value, $rule, $param);
                if ($errorMsg !== null) {
                    $this->errors[$field][] = $errorMsg;
                }
            }
        }
        return empty($this->errors);
    }

    protected function applyRule(string $field, string $label, mixed $value, string $rule, mixed $param): ?string {
        $skip = ($value === null || $value === '');
        return match ($rule) {
            'required'  => $skip ? $this->msg('required', ['field' => $label]) : null,
            'max',
            'maxLength' => (!$skip && is_string($value) && mb_strlen($value) > $param)
                            ? $this->msg('maxLength', ['field' => $label, 'len' => $param]) : null,
            'min',
            'minLength' => (!$skip && is_string($value) && mb_strlen($value) < $param)
                            ? $this->msg('minLength', ['field' => $label, 'len' => $param]) : null,
            'email'     => (!$skip && !filter_var($value, FILTER_VALIDATE_EMAIL))
                            ? $this->msg('email', ['field' => $label]) : null,
            'numeric'   => (!$skip && !is_numeric($value))
                            ? $this->msg('numeric', ['field' => $label]) : null,
            'integer'   => (!$skip && !preg_match('/^-?[0-9]+$/', (string)$value))
                            ? $this->msg('integer', ['field' => $label]) : null,
            'url'       => (!$skip && !filter_var($value, FILTER_VALIDATE_URL))
                            ? $this->msg('url', ['field' => $label]) : null,
            'regex'     => (!$skip && !preg_match($param, (string)$value))
                            ? $this->msg('regex', ['field' => $label]) : null,
            'match'     => ($value !== ($this->data[$param] ?? null))
                            ? $this->msg('match', ['field' => $label]) : null,
            'in'        => (!$skip && !in_array($value, (array)$param, true))
                            ? $this->msg('in', ['field' => $label]) : null,
            'notIn'     => (!$skip && in_array($value, (array)$param, true))
                            ? $this->msg('notIn', ['field' => $label]) : null,
            'between'   => (!$skip && (!is_numeric($value) || (float)$value < $param[0] || (float)$value > $param[1]))
                            ? $this->msg('between', ['field' => $label, 'min' => $param[0], 'max' => $param[1]]) : null,
            'confirmed' => ($value !== ($this->data[$field . '_confirmation'] ?? null))
                            ? $this->msg('confirmed', ['field' => $label]) : null,
            'date'      => (!$skip && strtotime((string)$value) === false)
                            ? $this->msg('date', ['field' => $label]) : null,
            default     => null,
        };
    }

    public function getErrors(): array { return $this->errors; }
    public function hasErrors(): bool { return !empty($this->errors); }
}

/**
 * フォームレンダリング
 */
class FormRender {
    /** CSRFフィールド出力 */
    public static function csrf(): string {
        return Security::csrfField();
    }

    /** inputタグ生成 */
    public static function input(string $type, string $name, mixed $value = '', array $attrs = []): string {
        $attr  = self::buildAttributes($attrs);
        $val   = h((string)$value);
        return "<input type=\"{$type}\" name=\"{$name}\" value=\"{$val}\"{$attr}>";
    }

    /** textareaタグ生成 */
    public static function textarea(string $name, mixed $value = '', array $attrs = []): string {
        $attr  = self::buildAttributes($attrs);
        $val   = h((string)$value);
        return "<textarea name=\"{$name}\"{$attr}>{$val}</textarea>";
    }

    /** selectタグ生成 */
    public static function select(string $name, array $options, mixed $selected = null, array $attrs = []): string {
        $attr = self::buildAttributes($attrs);
        $html = "<select name=\"{$name}\"{$attr}>";
        foreach ($options as $k => $v) {
            $sel = ((string)$k === (string)$selected) ? ' selected' : '';
            $html .= "<option value=\"" . h((string)$k) . "\"{$sel}>" . h((string)$v) . "</option>";
        }
        $html .= "</select>";
        return $html;
    }

    private static function buildAttributes(array $attrs): string {
        $html = '';
        foreach ($attrs as $key => $val) {
            if ($val === true) { $html .= " {$key}"; }
            elseif ($val !== false && $val !== null) { $html .= " {$key}=\"" . h((string)$val) . "\""; }
        }
        return $html;
    }
}
