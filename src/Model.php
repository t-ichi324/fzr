<?php
namespace Fzr;

/**
 * モデル基底クラス
 */
abstract class Model {
    protected static ?string $table = null;
    protected static ?string $primaryKey = 'id';

    /** テーブル名取得 */
    public static function tableName(): string {
        if (static::$table !== null) return static::$table;
        $class = (new \ReflectionClass(static::class))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class)) . 's';
    }

    /** プライマリキー名取得 */
    public static function primaryKeyName(): string {
        return static::$primaryKey ?? 'id';
    }

    /** モデルバリデーション情報取得（Attributeベース） */
    public static function getValidationRules(): array {
        $rules = [];
        $ref = new \ReflectionClass(static::class);
        foreach ($ref->getProperties() as $prop) {
            $propName = $prop->getName();
            $propRules = [];
            foreach ($prop->getAttributes() as $attr) {
                $instance = $attr->newInstance();
                if (method_exists($instance, 'toValidation')) {
                    $propRules = array_merge($propRules, $instance->toValidation());
                }
            }
            if (!empty($propRules)) {
                $rules[$propName] = $propRules;
            }
        }
        return $rules;
    }

    /** プロパティラベル取得 */
    public static function getLabels(): array {
        $labels = [];
        $ref = new \ReflectionClass(static::class);
        foreach ($ref->getProperties() as $prop) {
            $propName = $prop->getName();
            foreach ($prop->getAttributes(\Fzr\Attr\Field\Label::class) as $attr) {
                $labels[$propName] = $attr->newInstance()->label;
            }
        }
        return $labels;
    }
}
