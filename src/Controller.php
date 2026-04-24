<?php
namespace Fzr;

/**
 * コントローラ基底クラス
 */
abstract class Controller {
    /** デフォルトアクション */
    public function index() {
        return Response::error(404);
    }

    /** アクション前フック */
    public function __before(string $routeAction, string $dispatchMethod) {
    }

    /** アクション後フック */
    public function __after(string $routeAction, string $dispatchMethod) {
    }

    /** アクション最終処理フック */
    public function __finally(string $routeAction, string $dispatchMethod) {
    }

    /** プロパティ安全取得 */
    public function __getProp($key, $default = null) {
        return property_exists($this, $key) ? $this->$key : $default;
    }
}
