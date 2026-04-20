<?php
namespace Fzr;

/**
 * 認証管理
 */
class Auth {
    private static ?object $user = null;
    private static ?array $roles = null;

    /** ログイン */
    public static function login(object $user, array $roles = [], bool $regenerate = true): void {
        $key = defined('AUTH_SESSION_KEY') ? AUTH_SESSION_KEY : 'auth_key';
        self::$user = $user;
        self::$roles = $roles;
        $data = [
            'user' => $user,
            'roles' => $roles,
        ];
        Session::set($key, $data);
        if ($regenerate) Session::regenerate();
    }

    /** ログアウト */
    public static function logout(): void {
        $key = defined('AUTH_SESSION_KEY') ? AUTH_SESSION_KEY : 'auth_key';
        self::$user = null;
        self::$roles = null;
        Session::remove($key);
        if (defined('REMEMBER_TOKEN')) Cookie::remove(REMEMBER_TOKEN);
        Session::regenerate();
    }

    /** 認証状態チェック */
    public static function check(): bool {
        if (self::$user !== null) return true;
        $key = defined('AUTH_SESSION_KEY') ? AUTH_SESSION_KEY : 'auth_key';
        $auth = Session::get($key);
        if (is_array($auth) && isset($auth['user'])) {
            self::$user = $auth['user'];
            self::$roles = $auth['roles'] ?? [];
            return true;
        }
        return false;
    }

    /** ユーザー情報取得 */
    public static function user(): ?object {
        self::check();
        return self::$user;
    }

    /** ユーザーID取得 */
    public static function userid(): string|int|null {
        $u = self::user();
        return ($u !== null && isset($u->id)) ? $u->id : null;
    }

    /** ロール取得 */
    public static function roles(): array {
        self::check();
        return self::$roles ?? [];
    }

    /** ロール保持確認 */
    public static function hasRole(string|array $roles): bool {
        $userRoles = self::roles();
        if (empty($userRoles)) return false;
        $required = is_array($roles) ? $roles : [$roles];
        foreach ($required as $r) {
            if (in_array($r, $userRoles, true)) return true;
        }
        return false;
    }

    /** ゲスト確認 */
    public static function isGuest(): bool {
        return !self::check();
    }
}
