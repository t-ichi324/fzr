<?php
namespace Fzr;

/**
 * セキュリティ
 */
class Security {
    /** CSRFトークン生成 */
    public static function generateCsrfToken(): string {
        $token = bin2hex(random_bytes(32));
        Session::set(defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token', $token);
        return $token;
    }

    /** CSRFトークン取得（なければ生成） */
    public static function getCsrfToken(): string {
        $key = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        if (!Session::has($key)) {
            return self::generateCsrfToken();
        }
        return Session::get($key);
    }

    /** CSRF検証 */
    public static function verifyCsrf(): void {
        $key = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        $token = Request::post($key)
            ?: Request::server('HTTP_X_CSRF_TOKEN');

        $session_token = Session::get($key);

        if (empty($token) || empty($session_token) || !hash_equals($session_token, $token)) {
            Logger::warning("CSRF validation failed", [
                'ip' => Request::ipAddress(),
                'uri' => Request::uri()
            ]);
            throw HttpException::forbidden("CSRF token mismatch.");
        }
    }

    /** CSRFトークンHTML hidden input */
    public static function csrfField(): string {
        $key = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        return '<input type="hidden" name="' . $key . '" value="' . self::getCsrfToken() . '">';
    }

    /** IP制限チェック */
    public static function checkIP(): void {
        $whitelist = Env::getArray('ip.whitelist');
        $blacklist = Env::getArray('ip.blacklist');
        $ip = Request::ipAddress();

        if (!empty($blacklist) && in_array($ip, $blacklist, true)) {
            throw HttpException::forbidden("Access denied.");
        }

        if (!empty($whitelist) && !in_array($ip, $whitelist, true)) {
            throw HttpException::forbidden("Access denied.");
        }
    }

    /** パスワードハッシュ */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /** パスワード検証 */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /** ランダムトークン生成 */
    public static function randomToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }
}
