<?php
namespace Fzr;

/**
 * ファイルベースのキャッシュクラス（ドライバ差し替え対応）
 * デフォルト: ファイルキャッシュ（json_encode使用）
 */
class Cache {
    private static string $prefix = 'cache';
    private static array $__memory = [];

    /**
     * ドライバ（外部キャッシュストレージ）
     * set すると get/put がドライバに委譲される
     * @var object|null { get(key, ttl, closure), clear(key) }
     */
    private static ?object $driver = null;

    /** 外部キャッシュドライバ設定 */
    public static function setDriver(object $driver): void {
        self::$driver = $driver;
    }

    /**
     * キャッシュ取得
     */
    public static function get(string $key, int $ttl, callable $closure): mixed {
        if (isset(self::$__memory[$key])) {
            return self::$__memory[$key];
        }

        // ドライバがあればそちらに委譲
        if (self::$driver !== null && method_exists(self::$driver, 'get')) {
            $value = self::$driver->get($key, $ttl, $closure);
            self::$__memory[$key] = $value;
            return $value;
        }

        // ファイルキャッシュ（json使用）
        $path = self::getPath($key);
        if (file_exists($path) && (time() - filemtime($path)) < $ttl) {
            $content = @file_get_contents($path);
            if ($content !== false) {
                $value = json_decode($content, true);
                if ($value !== null || $content === 'null') {
                    self::$__memory[$key] = $value;
                    return $value;
                }
            }
        }

        $value = $closure();
        $result = @file_put_contents($path, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        if ($result !== false) {
            @touch($path, time());
        }
        self::$__memory[$key] = $value;
        return $value;
    }

    private static function getPath(string $key): string {
        $dir = Path::temp(self::$prefix);
        if (!is_dir($dir)) {
            @mkdir($dir, 0766, true);
        }
        return $dir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }
}
