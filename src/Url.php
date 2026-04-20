<?php
namespace Fzr;

/**
 * URL生成
 */
class Url {
    private static ?string $root = null;

    public static function init(string $root): void { self::$root = $root; }

    public static function base(): string {
        return self::$root ?? Request::url_root();
    }

    /** URL生成（相対パスを絶対化） */
    public static function get(string $path = ''): string {
        if (strpos($path, 'http') === 0 || strpos($path, '//') === 0) return $path;
        $base = rtrim(self::base(), '/');
        if ($path === '' || $path === '/') return $base . '/';
        return $base . '/' . ltrim($path, '/');
    }

    /** @see get() */
    public static function to(string $path): string { return self::get($path); }

    /** 静的リソースURL（キャッシュバスト） */
    public static function asset(string $path, ?string $version = null): string {
        $url = self::get($path);
        $version ??= Env::get('app.version');
        return $version ? $url . '?v=' . $version : $url;
    }
}
