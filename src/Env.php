<?php
namespace Fzr;

/**
 * 環境設定管理
 * INIファイル + 環境変数フォールバック対応
 */
class Env {
    protected static $dir = null;
    protected static $file = null;
    protected static $ini = null;

    /**
     * INIファイル設定
     */
    public static function configure(string $file): void {
        if (self::$file === $file) return;

        $real = realpath($file) ?: $file;
        self::$file = basename($real);
        self::$dir = dirname($real);
        self::$ini = null;
    }

    protected static function init_load() {
        if (self::$ini !== null) return;

        $baseIni = [];
        $extraIni = [];

        // INIファイルが設定されていて存在する場合のみ読む
        if (self::$file !== null && self::$dir !== null) {
            $baseIni = self::fromFile(self::$file);

            if (isset($baseIni['include_ini'])) {
                $includeFiles = preg_split('/[\s,;]+/', $baseIni['include_ini'], -1, PREG_SPLIT_NO_EMPTY);
                foreach ($includeFiles as $includeFile) {
                    $dat = self::fromFile(trim($includeFile));
                    foreach ($dat as $k => $v) {
                        $extraIni[strtolower($k)] = $v;
                    }
                }
            }
        }

        self::$ini = array_merge($baseIni, $extraIni);
    }

    protected static function fromFile(?string $file): array {
        $ini = [];
        if ($file === null || self::$dir === null) return [];
        $path = self::$dir . DIRECTORY_SEPARATOR . $file;
        if (file_exists($path)) {
            $dat = parse_ini_file($path, true);
            if ($dat !== false) {
                foreach ($dat as $section => $values) {
                    if (is_array($values)) {
                        foreach ($values as $k => $v) {
                            $key = strtolower($section . '.' . $k);
                            $ini[$key] = $v;
                        }
                    } else {
                        $key = strtolower($section);
                        $ini[$key] = $values;
                    }
                }
            }
        }
        return $ini;
    }

    /** 設定キー存在確認 */
    public static function has(string $key): bool {
        self::init_load();
        $k = strtolower($key);
        // INI → 環境変数の順で探す
        return isset(self::$ini[$k]) || self::getEnvVar($key) !== null;
    }

    /** 存在時コールバック実行 */
    public static function hasCallback(string $key, ?callable $has_callback, ?callable $else_callback = null): void {
        if (self::has($key) && $has_callback !== null) {
            $has_callback(self::get($key));
        } else if ($else_callback !== null) {
            $else_callback();
        }
    }

    /**
     * 設定値取得（INI → 環境変数 → デフォルトの優先順）
     */
    public static function get(string $key, string|null $defaultVal = null): ?string {
        self::init_load();
        $k = strtolower($key);
        // 1. INIファイルから
        if (isset(self::$ini[$k])) {
            return self::$ini[$k];
        }
        // 2. 環境変数から（ドット区切りをアンダースコアに変換）
        $envVal = self::getEnvVar($key);
        if ($envVal !== null) {
            return $envVal;
        }
        return $defaultVal;
    }

    /** 真偽値設定取得 */
    public static function getBool(string $key, bool $default = false): bool {
        $val = strtolower((string)self::get($key, $default ? "true" : "false"));
        return in_array($val, ['1', 'true', 'on', 'yes'], true);
    }

    /** 数値設定取得 */
    public static function getInt(string $key, int $default = 0): int {
        $val = self::get($key, null);
        return is_numeric($val) ? (int)$val : $default;
    }

    /** 配列設定取得 */
    public static function getArray(string $key, array $default = []): array {
        if (($val = self::get($key)) === null) return $default;
        $parts = preg_split('/[\s,;]+/', $val, -1, PREG_SPLIT_NO_EMPTY);
        return $parts ?: $default;
    }

    /** 設定値出力 */
    public static function echo(string $key, string|null $defaultVal = null) {
        echo htmlspecialchars(self::get($key, $defaultVal), ENT_QUOTES, defined('APP_CHARSET') ? APP_CHARSET : 'UTF-8');
    }

    /** 環境判定 */
    public static function is(string $env): bool {
        return (strtolower($env) === strtolower(self::get("env", "")));
    }

    /** 全設定取得 */
    public static function all(): array {
        self::init_load();
        return self::$ini ?? [];
    }

    /**
     * 環境変数から値を取得（Cloud Run / Docker 対応）
     * キーのドットをアンダースコアに変換し、大文字化して検索
     * 例: "db.host" → "DB_HOST"
     */
    protected static function getEnvVar(string $key): ?string {
        // そのまま検索
        $val = getenv($key);
        if ($val !== false) return $val;

        // ドット→アンダースコア、大文字化
        $envKey = strtoupper(str_replace('.', '_', $key));
        $val = getenv($envKey);
        if ($val !== false) return $val;

        // $_ENV も確認
        if (isset($_ENV[$envKey])) return $_ENV[$envKey];
        if (isset($_ENV[$key])) return $_ENV[$key];

        return null;
    }
}
