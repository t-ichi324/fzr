<?php
namespace Fzr;

/**
 * セッション管理
 */
class Session {
    private static bool $started = false;

    public static function start(?string $name = null): void {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        if (headers_sent()) return;

        $name = $name ?: Env::get('session.name', 'SID');
        $savePath = Env::get('session.save_path', Path::temp('sessions'));
        if (!is_dir($savePath)) @mkdir($savePath, 0777, true);
        session_save_path($savePath);

        $secure = Env::getBool('session.secure', Env::getBool('app.force_https', false) || Request::isHttps());
        $httpOnly = Env::getBool('session.httponly', true);
        $sameSite = Env::get('session.samesite', 'Lax');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => Env::getInt('session.lifetime', 0),
            'path' => '/',
            'domain' => Env::get('session.domain', ''),
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ]);
        session_start();
        self::$started = true;
    }

    public static function get(string $key, mixed $default = null): mixed {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    public static function remove(string ...$keys): void {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    public static function clear(): void {
        $_SESSION = [];
    }

    public static function destroy(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }

    public static function regenerate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function flash(string $key, mixed $value): void {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function hasFlash(string $key): bool {
        return isset($_SESSION['_flash'][$key]);
    }
}
