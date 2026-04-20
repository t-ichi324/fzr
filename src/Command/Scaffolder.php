<?php

namespace Fzr\Command;

use Exception;
use PDO;

/**
 * Fzr Core Scaffolder
 *
 * プロジェクトの初期ディレクトリ構造とファイルを生成します。
 * InitCommand から利用されます。
 */
class Scaffolder
{
    /**
     * インストール済みかチェック
     */
    public static function isInstalled(string $root, string $appPath = 'app'): bool
    {
        $app = trim($appPath, '/');
        return file_exists($root . '/' . $app . '/env.ini')
            || file_exists($root . '/env.ini');
    }

    /**
     * DB接続テスト
     */
    public static function testDatabase(array $input): array
    {
        try {
            $driver = $input['db_driver'] ?? 'mysql';
            $port   = $input['db_port'] ?: ($driver === 'pgsql' ? '5432' : '3306');
            $dsn    = "{$driver}:host={$input['db_host']};port={$port};dbname={$input['db_database']}";
            if ($driver === 'mysql') $dsn .= ';charset=utf8mb4';

            $pdo = @new PDO($dsn, $input['db_username'], $input['db_password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3
            ]);
            return ['ok' => true];
        } catch (\Throwable $ex) {
            return ['ok' => false, 'error' => $ex->getMessage()];
        }
    }

    /**
     * インストール実行
     */
    public static function scaffold(array $d): array
    {
        $root = rtrim($d['path_root'] ?? '', '/\\');
        if (!$root) return ['ok' => false, 'error' => 'ルートパスが未設定です。'];

        $appPath = trim($d['path_app'] ?? 'app', '/');
        $pubPath = trim($d['path_public'] ?? 'public', '/');
        $stoPath = trim($d['path_storage'] ?? 'storage', '/');

        try {
            // 1. ディレクトリ作成
            if (!is_dir($root) && !@mkdir($root, 0777, true)) {
                throw new Exception("ルートディレクトリを作成できません: {$root}");
            }

            $appAbs = $root . '/' . $appPath;
            $pubAbs = ($pubPath !== '') ? $root . '/' . $pubPath : $root;
            $stoAbs = $root . '/' . $stoPath;

            $dirs = [
                $appAbs,
                "$appAbs/controllers",
                "$appAbs/views",
                "$appAbs/views/@layouts",
                "$appAbs/models",
                $stoAbs,
                "$stoAbs/db",
                "$stoAbs/db/migrations",
                "$stoAbs/log",
                "$stoAbs/temp",
                "$stoAbs/public",
                "$stoAbs/private",
            ];
            if ($pubAbs !== $root) {
                $dirs[] = $pubAbs;
            }

            foreach ($dirs as $dir) {
                if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
                    throw new Exception("ディレクトリ生成に失敗しました: {$dir}");
                }
            }

            // 2. ファイル書き出し
            self::writeEnvIni($appAbs, $appPath, $pubPath, $stoPath, $d);
            self::writeBootstrap($appAbs, $d);
            self::writeIndexController($appAbs);
            self::writeBaseView($appAbs, $d);
            self::writeIndexView($appAbs);
            self::writeHtaccess($pubAbs);
            self::writePublicIndexPhp($pubAbs, $pubPath, $d);
            self::writeStorageGitkeep($stoAbs);

            if (!empty($d['use_composer'])) {
                self::writeComposerJson($root, $appPath, $d['use_symlink'] ?? false);
            }

            return ['ok' => true];
        } catch (\Throwable $ex) {
            return ['ok' => false, 'error' => $ex->getMessage()];
        }
    }

    private static function writeEnvIni(string $appAbs, string $appPath, string $pubPath, string $stoPath, array $d): void
    {
        $key    = bin2hex(random_bytes(16));
        $preset = $d['preset'] ?? 'standard';
        $isGcp  = $preset === 'gcp';
        $isAws  = $preset === 'aws';

        $ini  = "; Fzr Configuration\n; Preset: {$preset}\n; Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $ini .= "[app]\nname = \"{$d['app_name']}\"\nkey = \"{$key}\"\ntimezone = {$d['timezone']}\nlang = {$d['lang']}\ncharset = UTF-8\ndebug = " . ($d['debug_mode'] ? 'true' : 'false') . "\nforce_https = " . ($d['force_https'] ? 'true' : 'false') . "\n\n";
        $ini .= "[path]\napp = {$appPath}\nstorage = {$stoPath}\ndb = {$stoPath}/db\nlog = {$stoPath}/log\ntemp = {$stoPath}/temp\n\n";

        $ini .= "[db]\n";
        $driver = $d['db_driver'] ?? 'sqlite';
        if ($isGcp && $driver === 'sqlite') $driver = 'mysql';
        $ini .= "driver = {$driver}\n";
        if ($driver === 'sqlite') {
            $ini .= "sqlite_path = {$stoPath}/db/app.db\n";
        } else {
            $port = $d['db_port'] ?: ($driver === 'pgsql' ? '5432' : '3306');
            $ini .= "host = {$d['db_host']}\nport = {$port}\ndatabase = {$d['db_database']}\nusername = {$d['db_username']}\npassword = \"{$d['db_password']}\"\n";
            if ($driver === 'pgsql' && !empty($d['db_schema'])) $ini .= "schema = {$d['db_schema']}\n";
        }

        $ini .= "\n[storage]\ndriver = local\npublic_disk = public\nprivate_disk = private\n";
        $ini .= "\n[storage.public]\ndriver = local\nroot = {$stoPath}/public\nurl = /storage/public\n";
        $ini .= "\n[storage.private]\ndriver = local\nroot = {$stoPath}/private\n";

        $ini .= "\n[log]\naccess = " . ($isGcp || $isAws ? 'false' : 'true') . "\ndebug = " . ($d['debug_mode'] ? 'true' : 'false') . "\ndb_sel = " . ($d['debug_mode'] ? 'true' : 'false') . "\ndb_exe = true\n";

        file_put_contents($appAbs . '/env.ini', $ini);
    }

    private static function writeBootstrap(string $appAbs, array $d): void
    {
        $php  = "<?php\n/**\n * Bootstrap - Generated by Fzr Setup\n */\n\n";
        $php .= "use Fzr\\Db\\Db;\n";

        $preset = $d['preset'] ?? 'standard';
        $driver = $d['db_driver'] ?? 'sqlite';
        if ($preset === 'gcp' && $driver === 'sqlite') $driver = 'mysql';

        if ($driver === 'sqlite') {
            $php .= "use Fzr\\Db\\LiteDb;\n\n";
            $php .= "\$db = LiteDb::create('app');\n";
            $php .= "Db::addConnection('default', \$db);\n";
        } else {
            $php .= "use Fzr\\Db\\Connection;\n\n";
            $php .= "Db::addConnection('default', Connection::fromEnv());\n";
        }
        $php .= "Db::migrate();\n";

        if ($preset === 'gcp' || $preset === 'aws') {
            $php .= "\nif (getenv('K_SERVICE') || getenv('AWS_EXECUTION_ENV')) {\n    \\Fzr\\Logger::setOutput('stderr');\n}\n";
        }

        file_put_contents($appAbs . '/bootstrap.php', $php);
    }

    private static function writeIndexController(string $appAbs): void
    {
        $php = "<?php\nuse Fzr\\Controller;\nuse Fzr\\Response;\nuse Fzr\\Render;\n\nclass IndexController extends Controller {\n    public function index() {\n        Render::setTitle('Home');\n        return Response::view('index');\n    }\n}\n";
        file_put_contents($appAbs . '/controllers/IndexController.php', $php);
    }

    private static function writeBaseView(string $appAbs, array $d): void
    {
        $appName = htmlspecialchars($d['app_name'], ENT_QUOTES, 'UTF-8');
        $charset = $d['charset'] ?? 'UTF-8';
        $lang    = $d['lang'] ?? 'ja';
        $html = "<!DOCTYPE html>\n<html lang=\"<?= \\Fzr\\Env::get('app.lang', '{$lang}') ?>\">\n<head>\n    <meta charset=\"<?= \\Fzr\\Env::get('app.charset', '{$charset}') ?>\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title><?= \\Fzr\\Render::getTitle() ?> - <?= \\Fzr\\Env::get('app.name', '{$appName}') ?></title>\n    <style>\n        * { margin: 0; padding: 0; box-sizing: border-box; }\n        body { font-family: 'Segoe UI', system-ui, sans-serif; color: #333; background: #f5f5f7; }\n        .container { max-width: 960px; margin: 0 auto; padding: 20px; }\n        header { background: #1a1a2e; color: #fff; padding: 16px 0; }\n        header .container { display: flex; justify-content: space-between; align-items: center; }\n        header h1 { font-size: 1.2rem; font-weight: 600; }\n        main { min-height: 60vh; padding: 40px 0; }\n        footer { text-align: center; padding: 20px; color: #888; font-size: 0.85rem; }\n    </style>\n</head>\n<body>\n    <header><div class=\"container\"><a href=\"<?= \\Fzr\\Url::get('/') ?>\"><h1><?= \\Fzr\\Env::get('app.name', '{$appName}') ?></h1></a></div></header>\n    <main><div class=\"container\"><?= \\Fzr\\Render::getContent() ?></div></main>\n    <footer><div class=\"container\">Powered by Fzr</div></footer>\n</body>\n</html>\n";
        file_put_contents($appAbs . '/views/@layouts/base.php', $html);
    }

    private static function writeIndexView(string $appAbs): void
    {
        file_put_contents($appAbs . '/views/index.php', "<h1>Welcome to Fzr</h1>\n<p>セットアップが完了しました。</p>\n");
    }

    private static function writeHtaccess(string $pubAbs): void
    {
        $ht = "<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteBase /\n    RewriteCond %{REQUEST_FILENAME} !-f\n    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteRule ^(.*)$ index.php [QSA,L]\n</IfModule>\n<IfModule mod_headers.c>\n    Header always set X-Content-Type-Options \"nosniff\"\n    Header always set X-Frame-Options \"SAMEORIGIN\"\n</IfModule>\n";
        file_put_contents($pubAbs . '/.htaccess', $ht);
    }

    private static function writePublicIndexPhp(string $pubAbs, string $pubPath, array $d): void
    {
        $appPath = trim($d['path_app'] ?? 'app', '/');
        // src/Command/Scaffolder.php → src/ → project root は dirname(dirname(dirname(__FILE__)))
        // loader.php はプロジェクトルート直下に配置される想定
        $fzrLoader = var_export(dirname(dirname(dirname(__FILE__))) . '/loader.php', true);

        if ($pubPath === '' || $pubPath === '.') {
            $upExpr = '__DIR__';
        } else {
            $levels = count(explode('/', $pubPath));
            $upExpr = $levels > 1 ? "dirname(__DIR__, $levels)" : "dirname(__DIR__)";
        }

        $php  = "<?php\nif (!defined('ABSPATH')) define('ABSPATH', {$upExpr});\nif (!defined('APP_START_TIME')) define('APP_START_TIME', microtime(true));\n\n";
        $php .= "if (file_exists(ABSPATH . '/vendor/autoload.php')) {\n    require ABSPATH . '/vendor/autoload.php';\n} else {\n    require_once {$fzrLoader};\n}\n\n";
        $php .= "use Fzr\\Engine;\n\nEngine::init(ABSPATH);\nEngine::autoload('{$appPath}/controllers', '{$appPath}/models');\nEngine::bootstrap(ABSPATH . '/{$appPath}/bootstrap.php');\n\n";
        $php .= "Engine::onError(function(\\Throwable \$ex) {\n    if (\\Fzr\\Context::isDebug()) {\n        echo '<pre>' . htmlspecialchars((string) \$ex) . '</pre>';\n    }\n});\n\nEngine::dispatch();\n";

        file_put_contents($pubAbs . '/index.php', $php);
    }

    private static function writeStorageGitkeep(string $stoAbs): void
    {
        foreach (['db', 'log', 'temp', 'public', 'private'] as $subdir) {
            $dir = $stoAbs . '/' . $subdir;
            if (is_dir($dir)) @file_put_contents($dir . '/.gitkeep', '');
        }
        if (is_dir($stoAbs . '/private')) file_put_contents($stoAbs . '/private/.htaccess', "Deny from all\n");
    }

    private static function writeComposerJson(string $root, string $appPath, bool $useSymlink): void
    {
        // src/Command/Scaffolder.php → プロジェクトルート は dirname(dirname(dirname(__FILE__)))
        $fzrCorePath = dirname(dirname(dirname(__FILE__)));
        $json = [
            "name"         => "fzr/project",
            "type"         => "project",
            "repositories" => [
                ["type" => "path", "url" => $fzrCorePath, "options" => ["symlink" => $useSymlink]]
            ],
            "require"      => [
                "php"    => ">=8.1",
                "fzr/fw" => "*"
            ],
            "minimum-stability" => "dev",
            "prefer-stable"     => true,
            "autoload"     => [
                "psr-4" => ["App\\" => $appPath . "/"]
            ]
        ];
        file_put_contents($root . '/composer.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
