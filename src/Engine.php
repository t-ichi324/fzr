<?php

namespace Fzr;

use ReflectionMethod, ReflectionClass, Throwable;

/**
 * アプリケーション実行状態
 */
class Context
{
    const MODE_WEB = 'web';
    const MODE_API = 'api';
    const MODE_CLI = 'cli';
    private static string $mode = self::MODE_WEB;
    private static string $requestId = '';
    private static float $startTime = 0;
    private static bool $debug = false;

    public static function init(bool $is_debug): void
    {
        self::$debug = $is_debug;
        if (self::$startTime > 0) return;
        self::$startTime = defined('APP_START_TIME') ? APP_START_TIME : microtime(true);
        self::$requestId = substr(bin2hex(random_bytes(4)), 0, 8);
    }

    public static function setMode(string $mode): void
    {
        self::$mode = $mode;
    }
    public static function modeToWeb(): void
    {
        self::$mode = self::MODE_WEB;
    }
    public static function modeToApi(): void
    {
        self::$mode = self::MODE_API;
    }
    public static function modeToCli(): void
    {
        self::$mode = self::MODE_CLI;
    }
    public static function mode(): string
    {
        return self::$mode;
    }
    public static function isApi(): bool
    {
        return self::$mode === self::MODE_API;
    }
    public static function isWeb(): bool
    {
        return self::$mode === self::MODE_WEB;
    }
    public static function isCli(): bool
    {
        return self::$mode === self::MODE_CLI;
    }
    public static function isDebug(): bool
    {
        return self::$debug;
    }
    public static function requestId(): string
    {
        return self::$requestId;
    }
    public static function startTime(): float
    {
        return self::$startTime;
    }
    public static function elapsed(): float
    {
        return microtime(true) - self::$startTime;
    }
}

/**
 * オートローダー
 */
class Loader
{
    protected static array $baseDirs = [];
    protected static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) return;
        spl_autoload_register([self::class, 'autoload']);
        self::$registered = true;
    }

    public static function add(string|array $dirs): void
    {
        self::register();
        foreach ((is_array($dirs) ? $dirs : [$dirs]) as $dir) {
            if (!in_array($p = Path::get($dir), self::$baseDirs)) {
                self::$baseDirs[] = $p;
            }
        }
    }

    public static function autoload(string $className): void
    {
        self::load($className);
    }

    public static function load(string $name): bool
    {
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php';
        // Fzr名前空間のプレフィックスを除去したパス（手動読み込み用フォールバック）
        $fzrPath = str_starts_with($name, 'Fzr\\') ? str_replace('\\', DIRECTORY_SEPARATOR, substr($name, 4)) . '.php' : null;

        foreach (self::$baseDirs as $base) {
            $baseDir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR;
            // 1. 名前空間通りのパスを試行
            if (is_readable($full = $baseDir . $path)) {
                require_once $full;
                Logger::debug("[Loader] Loaded: $name from $full");
                return true;
            }
            // 2. Fzrプレフィックスを除去したパスを試行（src直下にある場合など）
            if ($fzrPath && is_readable($full = $baseDir . $fzrPath)) {
                require_once $full;
                Logger::debug("[Loader] Loaded: $name from $full");
                return true;
            }
        }
        return false;
    }
}

/**
 * フレームワークエンジン
 */
class Engine
{
    private static $_initialized = false;
    private static array $_shutdownHandlers = [];
    private static ?\Closure $_successHandler = null;

    private static array $onBeforeAction = [];
    private static $onError = null;
    private static array $routes = [];
    private static ?self $instance = null;

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /** エンジン初期化 */
    public static function init(?string $rootPath = null, ?string $envFile = null): void
    {
        if (self::$_initialized === false) {

            if (!defined('ABSPATH')) {
                define('ABSPATH', $rootPath ?? realpath(__DIR__ . '/..'));
            }

            if ($envFile === null) {
                // デフォルトのenv.iniを探す（存在しなければ環境変数のみで動作）
                $defaultIni = (defined('ABSPATH') ? ABSPATH : __DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . Config::DEFAULT_INI;
                if (file_exists($defaultIni)) {
                    $envFile = $defaultIni;
                }
            } else {
                if (!file_exists($envFile)) {
                    $envFile = (defined('ABSPATH') ? ABSPATH : __DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $envFile;
                }
            }

            // INIファイルがあれば設定、なければ環境変数のみで動作
            if ($envFile !== null && file_exists($envFile)) {
                Env::configure($envFile);
            }

            if (!defined('APP_START_TIME')) {
                define('APP_START_TIME', microtime(true));
            }

            $is_debug = Env::getBool('app.debug', false);
            Context::init($is_debug);
            if (php_sapi_name() === 'cli') {
                Context::setMode(Context::MODE_CLI);
            }

            if ($is_debug) {
                error_reporting(E_ALL);
                ini_set('display_errors', '1');
                ini_set('display_startup_errors', '1');
            } else {
                error_reporting(0);
                ini_set('display_errors', '0');
            }

            ini_set('log_errors', '1');
            if (php_sapi_name() !== 'cli') {
                Session::start(Env::get('session.name', "SID"));
                // HTTPS強制リダイレクト
                if (Env::getBool('app.force_https', false) && !Request::isHttps()) {
                    header('Location: https://' . Request::server('HTTP_HOST') . Request::server('REQUEST_URI'), true, 301);
                    if (Response::isExitOnSend()) exit;
                    return;
                }

                // デフォルトのセキュリティヘッダ
                Response::setHeader('X-Frame-Options', 'SAMEORIGIN');
                Response::setHeader('X-Content-Type-Options', 'nosniff');
                Response::setHeader('X-XSS-Protection', '1; mode=block');
                Response::setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
            }

            define('APP_CHARSET', Env::get('app.charset', 'UTF-8'));
            define('APP_LANG', Env::get('app.lang', 'ja'));
            define('APP_NAME', Env::get('app.name', 'MyApp'));
            define('LOGIN_PAGE', Env::get('app.login_page', 'login'));
            define('DELIMITER', Env::get('app.delimiter', '-'));
            define('AUTH_SESSION_KEY', Env::get('session.auth_key', 'auth_key@' . Env::get('app.key', md5(__DIR__))));
            define("REMEMBER_TOKEN", Env::get("session.remember_token", "rem"));
            define("CSRF_TOKEN_NAME", Env::get('security.csrf_name', "csrf_token"));
            date_default_timezone_set(Env::get('app.timezone', 'UTC'));
            define('VIEW_TEMPLATE_BASE', Env::get("view.base_template", "@layouts/base.php"));

            if (function_exists('mb_internal_encoding')) {
                mb_internal_encoding(APP_CHARSET);
            }
            if (function_exists('mb_http_output')) {
                mb_http_output(APP_CHARSET);
            }
            register_shutdown_function([self::class, '__handleShutdown']);
        }
        self::$_initialized = true;
    }

    public static function __handleShutdown(): void
    {
        $app = self::getInstance();
        foreach (self::$_shutdownHandlers as $handler) {
            try {
                $handler($app);
            } catch (\Throwable) {
            }
        }
        if (Env::getBool('log.access', true)) {
            Logger::access();
        }
    }

    public static function onShutdown(callable $callback): void
    {
        self::$_shutdownHandlers[] = $callback;
    }

    /** 致命的エラー出力 */
    public static function criticalError(string $title, string $message): void
    {
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            echo json_encode(['error' => $title, 'message' => strip_tags($message)]);
            exit;
        }
        http_response_code(500);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo "<!DOCTYPE html><html><head><title>Critical Error</title></head><body><h1>{$safeTitle}</h1><p>{$safeMessage}</p></body></html>";
        exit;
    }

    public static function autoload(...$dirs): void
    {
        Loader::add($dirs);
    }

    public static function bootstrap(...$files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    public static function onSuccess(callable $callback): void
    {
        self::$_successHandler = $callback;
    }
    public static function onError(?callable $callback): void
    {
        self::$onError = $callback;
    }
    public static function onFinally(callable $callback): void
    {
        self::onShutdown($callback);
    }
    public static function onBeforeAction(callable $callback): void
    {
        self::$onBeforeAction[] = $callback;
    }

    /** ディスパッチ */
    public static function dispatch(?callable $callback = null): void
    {
        if (Context::isCli()) {
            if (file_exists($cli_core = Path::app(Config::CLI_FILE))) {
                require_once $cli_core;
            }
            if (class_exists(\Fzr\CliEngine::class)) {
                \Fzr\CliEngine::dispatch($GLOBALS['argv'] ?? []);
            }
            return;
        }

        $cb = $callback ?? self::$_successHandler;
        $app = self::$instance = new self();
        try {
            if (Request::isApiRoute()) Context::setMode(Context::MODE_API);
            $app->run();
        } catch (Throwable $ex) {
            Logger::exception("Dispatch error", $ex);
            if (is_callable(self::$onError)) call_user_func(self::$onError, $ex, $app);
            else $app->error(500, (Context::isDebug() ? "Error: " . $ex->getMessage() : "Error: Internal error has occurred."));
        }
        if (is_callable($cb)) $cb($app);
    }

    /** ルーティング実行 */
    private function run(): void
    {
        if (!empty(self::$routes) && ($matched = self::matchRoute())) {
            $this->dispatchMatched($matched['class'], $matched['method'], $matched['params']);
            return;
        }
        $pathParts = Request::routeParts();
        $max = count($pathParts);
        $found = false;
        $routeAction = '';
        if ($max === 0) {
            $class = Config::CTRL_PFX . 'Index' . Config::CTRL_SFX;
            $path = Path::ctrl($class . Config::CTRL_EXT);
            $routeAction = 'index';
            $params = [];
            $found = file_exists($path);
        } else {
            for ($i = $max; $i > 0; $i--) {
                $ctrlParts = array_slice($pathParts, 0, $i);
                $dir = implode(DIRECTORY_SEPARATOR, array_slice($ctrlParts, 0, -1));
                $ctrlName = $this->toClassCase($ctrlParts[$i - 1]);
                $class = Config::CTRL_PFX . $ctrlName . Config::CTRL_SFX;
                $path = Path::ctrl($dir, $class . Config::CTRL_EXT);
                if (file_exists($path)) {
                    $found = true;
                    $methodAndParams = array_slice($pathParts, $i);
                    $rawAction = $methodAndParams[0] ?? 'index';
                    $routeAction = $this->toMethodCase($rawAction);
                    $params = array_slice($methodAndParams, 1);
                    break;
                }
            }
            if (!$found) {
                $class = Config::CTRL_PFX . 'Index' . Config::CTRL_SFX;
                $path = Path::ctrl($class . Config::CTRL_EXT);
                if (file_exists($path)) {
                    $found = true;
                    $rawAction = $pathParts[0];
                    $routeAction = $this->toMethodCase($rawAction);
                    $params = array_slice($pathParts, 1);
                }
            }
        }
        if ($found) {
            include_once $path;
            if (!class_exists($class) || !(($controller = new $class()) instanceof Controller)) {
                $this->error(404);
                return;
            }
            $method = strtolower(Request::method());
            $isAjax = Request::isAjax();
            $tryList = [];
            if ($isAjax) {
                $tryList[] = "_ajax_{$method}_{$routeAction}";
                $tryList[] = "_ajax_{$routeAction}";
            }
            $tryList[] = "_{$method}_{$routeAction}";
            $tryList[] = $routeAction;
            if (isset($rawAction) && $rawAction !== $routeAction) {
                $tryList[] = "_{$method}_{$rawAction}";
                $tryList[] = $rawAction;
            }
            foreach ($tryList as $dispatchMethod) {
                if (is_callable([$controller, $dispatchMethod])) {
                    try {
                        if (!$this->isRoutable($controller, $routeAction, $dispatchMethod)) {
                            $this->error(404);
                            return;
                        }
                        if (!$this->verifyAccess($controller, $routeAction)) return;
                        if (!$this->invokeBeforeAction($class, $routeAction, $dispatchMethod)) return;
                        $ret = $this->inv_inner($controller, $routeAction, $dispatchMethod, $params);
                        if (is_array($ret)) Response::handle($ret);
                        elseif (is_string($ret)) Response::handle(Response::view($ret));
                    } catch (HttpException $ex) {
                        if ($ex->getCode() === 401 && Context::isWeb()) {
                            Response::handle(Response::redirect(LOGIN_PAGE));
                            return;
                        }
                        $this->error($ex);
                    }
                    return;
                }
            }
            if (method_exists($controller, '__id')) {
                $dispatchMethod = '__id';
                $params = array_merge([$routeAction], $params);
                try {
                    if (!$this->verifyAccess($controller, $routeAction)) return;
                    if (!$this->invokeBeforeAction($class, $routeAction, $dispatchMethod)) return;
                    $ret = $this->inv_inner($controller, $routeAction, $dispatchMethod, $params);
                    if (is_array($ret)) Response::handle($ret);
                    elseif (is_string($ret)) Response::handle(Response::view($ret));
                    return;
                } catch (HttpException $ex) {
                    $this->error($ex);
                    return;
                }
            }
            $this->error(404);
            return;
        }
        $this->error(404);
    }

    private function invokeBeforeAction(string $className, string $routeAction, string $dispatchMethod): bool
    {
        foreach (self::$onBeforeAction as $cb) {
            if (call_user_func($cb, $className, $routeAction, $dispatchMethod) === false) return false;
        }
        return true;
    }

    private function toClassCase(string $str): string
    {
        return implode('', array_map('ucfirst', preg_split('/[\.\-_ 　]+/', $str, -1, PREG_SPLIT_NO_EMPTY)));
    }

    private function toMethodCase(string $str): string
    {
        $parts = preg_split('/[\.\-_ 　]+/', $str, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) return $str;
        $res = array_map('ucfirst', $parts);
        $res[0] = lcfirst($res[0]);
        return implode('', $res);
    }

    private function error(int|HttpException $code_or_ex, $error = null)
    {
        if ($code_or_ex instanceof HttpException) {
            $code = $code_or_ex->getHttpCode();
            $error = $error ?? $code_or_ex->getMessage();
        } else {
            $code = $code_or_ex;
            $error = $error ?? HttpException::getErrorTitle($code);
        }

        if ($code >= 400 && $code !== 404) {
            if ($code >= 500) Logger::error("HTTP $code: $error");
            else Logger::warning("HTTP $code: $error");
        }

        Breadcrumb::clear();
        Response::setStatusCode($code);
        $defaultTitle = $code . " " . HttpException::getErrorTitle($code);
        Render::setData("error", $error);
        Auth::check();
        if (Context::isApi()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(["status" => "error", "code" => $code, "title" => $defaultTitle, "message" => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (Render::isPartial()) {
            Render::setTitle($defaultTitle);
            $content = "<h1>{$defaultTitle}</h1>";
            if ($error) $content .= "<p>" . h($error) . "</p>";
            echo $content;
        } else {
            Render::setTitle($defaultTitle);
            $err_view = Env::get("view.error_prefix", Config::ERR_VIEW_PFX) . $code . Env::get("view.error_suffix", Config::ERR_VIEW_SFX);
            if (Render::hasTemplate($err_view)) {
                Render::setContent(Render::getTemplate($err_view));
            } else {
                $content = "<h1>{$defaultTitle}</h1>";
                if ($error) $content .= "<p>" . h($error) . "</p>";
                Render::setContent($content);
            }
            $vfile = (defined('VIEW_TEMPLATE_BASE') && VIEW_TEMPLATE_BASE) ? Path::view(VIEW_TEMPLATE_BASE) : '';
            if (file_exists($vfile)) include $vfile;
            else echo Render::getContent();
        }
    }

    protected function inv_inner(Controller $controller, string $routeAction, string $dispatchMethod, array $params = [])
    {
        $class = get_class($controller);
        try {
            Logger::debug("Dispatching: $class::$dispatchMethod()");
            if ($this->isMethodOverridden($controller, '__before')) {
                if (($ret_bef = $controller->__before($routeAction, $dispatchMethod)) !== null) return $ret_bef;
            }
            $refMethod = new ReflectionMethod($controller, $dispatchMethod);
            $methodParams = $refMethod->getParameters();
            $args = [];
            foreach ($methodParams as $i => $param) {
                if (array_key_exists($i, $params)) $args[] = $params[$i];
                elseif ($param->isDefaultValueAvailable()) $args[] = $param->getDefaultValue();
                else $args[] = null;
            }
            $ret = $refMethod->invokeArgs($controller, $args);
            if ($this->isMethodOverridden($controller, '__after')) {
                if (($ret_aft = $controller->__after($routeAction, $dispatchMethod)) !== null) $ret = $ret_aft;
            }
            return $ret;
        } catch (Throwable $ex) {
            Logger::exception("Exception in $class::$dispatchMethod()", $ex);
            throw $ex;
        } finally {
            try {
                if ($this->isMethodOverridden($controller, '__finally')) if (($ret_fin = $controller->__finally($routeAction, $dispatchMethod)) !== null) $ret = $ret_fin;
            } catch (Throwable $ex) {
                Logger::exception("Finally-block failed in $class::$dispatchMethod()", $ex);
            }
        }
    }

    protected function isMethodOverridden($controller, $method): bool
    {
        $refClass = new ReflectionClass($controller);
        return $refClass->hasMethod($method) && $refClass->getMethod($method)->getDeclaringClass()->getName() !== Controller::class;
    }

    protected function isRoutable($controller, string $routeAction, string $dispatchMethod): bool
    {
        if (strpos($routeAction, '_') === 0 || strpos($routeAction, '__') === 0) return false;
        if (!empty($deny = $controller->__getProp("denyMethods")) && (in_array($routeAction, $deny, true) || in_array($dispatchMethod, $deny, true))) return false;
        return method_exists($controller, $dispatchMethod) && (new ReflectionMethod($controller, $dispatchMethod))->isPublic();
    }

    protected function verifyAccess($controller, string $action): bool
    {
        $refClass = new ReflectionClass($controller);
        $refMethod = $refClass->hasMethod($action) ? $refClass->getMethod($action) : null;

        if ($this->getAttr($refClass, $refMethod, \Fzr\Attr\Http\Api::class)) {
            Context::setMode(Context::MODE_API);
        }
        if ($this->getAttr($refClass, $refMethod, \Fzr\Attr\Http\IpWhitelist::class)) {
            Security::checkIP();
        }
        if ($this->getAttr($refClass, $refMethod, \Fzr\Attr\Http\AllowCors::class) || Env::get('app.cors_origin')) {
            $origin = Env::get('app.cors_origin', '*');
            Response::setHeader('Access-Control-Allow-Origin', $origin);
            Response::setHeader('Access-Control-Allow-Methods', Env::get('app.cors_methods', 'GET, POST, OPTIONS, PUT, DELETE'));
            Response::setHeader('Access-Control-Allow-Headers', Env::get('app.cors_headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN'));
            Response::setHeader('Access-Control-Allow-Credentials', 'true');
            if (Request::isMethod('OPTIONS')) {
                Response::setStatusCode(200);
                Response::sendHeaders();
                return false;
            }
        }
        if ($this->getAttr($refClass, $refMethod, \Fzr\Attr\Http\IsReadOnly::class)) {
            if (Request::isPost() || Request::isMethod('DELETE') || Request::isMethod('PUT')) {
                throw HttpException::forbidden("This action is read-only.");
            }
        }
        if ($this->getAttr($refClass, $refMethod, \Fzr\Attr\Http\Csrf::class)) {
            if (Request::isPost() || Request::isMethod('DELETE') || Request::isMethod('PUT')) {
                Security::verifyCsrf();
            }
        }
        $roleAttr = $this->getAttr($refClass, $refMethod, \Fzr\Attr\Http\Role::class);
        if ($roleAttr || $controller instanceof AuthController) {
            if (!Auth::check()) throw HttpException::unauthorized();
            $roles = [];
            if ($roleAttr) {
                $roles = $roleAttr->roles;
            } elseif ($controller instanceof AuthController) {
                $roles = $controller->__authRoles($action);
            }
            if (!empty($roles) && !Auth::hasRole($roles)) {
                throw HttpException::forbidden();
            }
        }
        if ($cache = $this->getAttr($refClass, $refMethod, \Fzr\Attr\Http\AllowCache::class)) {
            Response::setHeader('Cache-Control', "public, max-age={$cache->maxAge}");
        }
        if ($this->getAttr($refClass, $refMethod, \Fzr\Attr\Http\AllowIframe::class)) {
            Response::setHeader('X-Frame-Options', 'ALLOWALL');
            Response::setHeader('Content-Security-Policy', "frame-ancestors *");
        }
        return true;
    }

    private function getAttr(ReflectionClass $refClass, ?ReflectionMethod $refMethod, string $className): ?object
    {
        if ($refMethod) {
            $attrs = $refMethod->getAttributes($className);
            if (!empty($attrs)) return $attrs[0]->newInstance();
        }
        $attrs = $refClass->getAttributes($className);
        return !empty($attrs) ? $attrs[0]->newInstance() : null;
    }

    /** ルート明示登録 */
    public static function addRoute(string $httpMethod, string $pattern, string $handler): void
    {
        self::$routes[] = ['httpMethod' => strtoupper($httpMethod), 'pattern' => '/' . trim($pattern, '/'), 'handler' => $handler];
    }

    private static function matchRoute(): ?array
    {
        $requestPath = '/' . implode('/', Request::routeParts());
        $requestMethod = strtoupper(Request::method());
        foreach (self::$routes as $route) {
            if ($route['httpMethod'] !== 'ANY' && $route['httpMethod'] !== $requestMethod) continue;
            $pattern = $route['pattern'];
            if (strpos($pattern, '{') === false) {
                if ($pattern === $requestPath) return self::parseHandler($route['handler'], []);
                continue;
            }
            if (preg_match('#^' . preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^\/]+)', $pattern) . '$#', $requestPath, $matches)) {
                return self::parseHandler($route['handler'], array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)));
            }
        }
        return null;
    }

    private static function parseHandler(string $handler, array $params): array
    {
        $parts = explode('@', $handler, 2);
        return ['class' => $parts[0], 'method' => $parts[1] ?? 'index', 'params' => $params];
    }

    private function dispatchMatched(string $className, string $methodName, array $params): void
    {
        if (!file_exists($path = Path::ctrl($className . Config::CTRL_EXT))) {
            $this->error(404);
            return;
        }
        include_once $path;
        if (!class_exists($className) || !(($controller = new $className()) instanceof Controller)) {
            $this->error(404);
            return;
        }
        try {
            if (!$this->verifyAccess($controller, $methodName)) return;
            if (!$this->invokeBeforeAction($className, $methodName, $methodName)) return;
            $ret = $this->inv_inner($controller, $methodName, $methodName, $params);
            if (is_array($ret)) Response::handle($ret);
            elseif (is_string($ret)) Response::handle(Response::view($ret));
        } catch (HttpException $ex) {
            if ($ex->getCode() === 401 && Context::isWeb()) {
                Response::handle(Response::redirect(LOGIN_PAGE));
                return;
            }
            $this->error($ex);
        }
    }
}
