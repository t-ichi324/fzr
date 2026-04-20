<?php

namespace Fzr\Command;

use Fzr\Command;

/**
 * php fzr init
 *
 * 対話型プロンプトで設定を収集し、Scaffolder でプロジェクトを初期化します。
 * オプション: --force  既存インストールを上書きする
 */
class InitCommand extends Command
{
    public function description(): string
    {
        return 'Initialize a new Fzr project';
    }

    public function execute(): int
    {
        $force = in_array('--force', $this->args, true);

        $this->out('');
        $this->out("\033[1;36m Fzr Setup\033[0m  — Interactive Project Initializer");
        $this->out(str_repeat('─', 50));

        // ── 1. 基本設定 ──────────────────────────────────────────
        $preset = $this->askChoice('Target preset', ['standard', 'gcp', 'aws'], 'standard');

        $appName   = $this->ask('App name', 'My Project');
        $timezone  = $this->ask('Timezone', 'Asia/Tokyo');
        $lang      = $this->ask('Language (ja/en/...)', 'ja');
        $debug     = $this->askBool('Enable debug mode', true);
        $forceHttps = $this->askBool('Force HTTPS', false);

        // ── 2. パス設定 ───────────────────────────────────────────
        $this->out('');
        $this->out("\033[33mDirectory Structure\033[0m");

        $pathRoot    = $this->ask('Install root (absolute path)', getcwd());
        $pathApp     = $this->ask('App directory name', 'app');
        $pathPublic  = $this->ask('Public directory name (empty = root)', 'public');
        $pathStorage = $this->ask('Storage directory name', 'storage');
        $useComposer = $this->askBool('Generate composer.json', true);
        $useSymlink  = false;
        if ($useComposer) {
            $useSymlink = $this->askBool('Use symlink for framework? (Recommended for dev, but may fail on Windows Network drives)', false);
        }

        // ── 3. データベース設定 ───────────────────────────────────
        $this->out('');
        $this->out("\033[33mDatabase\033[0m");

        $availableDrivers = ['sqlite', 'mysql', 'pgsql'];
        if ($preset === 'gcp' || $preset === 'aws') {
            $availableDrivers = ['mysql', 'pgsql'];
            $this->out("\033[33m[INFO]\033[0m Stateless preset: SQLite is not available.");
        }
        $dbDriver = $this->askChoice('DB driver', $availableDrivers, $availableDrivers[0]);

        $dbHost = $dbPort = $dbDatabase = $dbUsername = $dbPassword = '';
        if ($dbDriver !== 'sqlite') {
            $defaultPort  = $dbDriver === 'pgsql' ? '5432' : '3306';
            $dbHost       = $this->ask('DB host', 'localhost');
            $dbPort       = $this->ask('DB port', $defaultPort);
            $dbDatabase   = $this->ask('DB name', 'fzr_app');
            $dbUsername   = $this->ask('DB username', 'root');
            $dbPassword   = $this->askSecret('DB password');
        }

        // ── 4. 多重インストール防止チェック ──────────────────────
        $params = [
            'preset'       => $preset,
            'app_name'     => $appName,
            'timezone'     => $timezone,
            'lang'         => $lang,
            'debug_mode'   => $debug,
            'force_https'  => $forceHttps,
            'path_root'    => $pathRoot,
            'path_app'     => $pathApp,
            'path_public'  => $pathPublic,
            'path_storage' => $pathStorage,
            'use_composer' => $useComposer,
            'use_symlink'  => $useSymlink,
            'db_driver'    => $dbDriver,
            'db_host'      => $dbHost,
            'db_port'      => $dbPort,
            'db_database'  => $dbDatabase,
            'db_username'  => $dbUsername,
            'db_password'  => $dbPassword,
        ];

        if (Scaffolder::isInstalled($pathRoot, $pathApp) && !$force) {
            $this->error('Already installed. Use --force to overwrite.');
            return 1;
        }

        // ── 5. DB接続テスト（sqlite以外） ─────────────────────────
        if ($dbDriver !== 'sqlite') {
            $this->out('');
            $this->out('Testing database connection...');
            $result = Scaffolder::testDatabase($params);
            if (!$result['ok']) {
                $this->error('DB connection failed: ' . ($result['error'] ?? 'unknown error'));
                return 1;
            }
            $this->out("\033[32m[OK]\033[0m Database connection successful.");
        }

        // ── 6. スキャフォルディング実行 ───────────────────────────
        $this->out('');
        $this->out('Generating project files...');
        $result = Scaffolder::scaffold($params);

        if (!$result['ok']) {
            $this->error($result['error'] ?? 'Scaffolding failed.');
            return 1;
        }

        $this->out('');
        $this->success('Project initialized successfully!');
        $this->out("  Root    : {$pathRoot}");
        $this->out("  App     : {$pathRoot}/{$pathApp}");
        if ($pathPublic !== '') {
            $this->out("  Public  : {$pathRoot}/{$pathPublic}");
        }
        $this->out("  Storage : {$pathRoot}/{$pathStorage}");
        if ($useComposer) {
            $this->out('');
            $this->out("\033[33mNext:\033[0m  cd {$pathRoot} && composer install");
        }
        $this->out('');
        return 0;
    }

    // ── プロンプトヘルパー ────────────────────────────────────────

    private function ask(string $label, string $default = ''): string
    {
        $hint = $default !== '' ? " \033[2m[{$default}]\033[0m" : '';
        echo "  {$label}{$hint}: ";
        $val = trim(fgets(STDIN));
        return $val !== '' ? $val : $default;
    }

    private function askBool(string $label, bool $default): bool
    {
        $hint = $default ? 'Y/n' : 'y/N';
        echo "  {$label} [{$hint}]: ";
        $val = strtolower(trim(fgets(STDIN)));
        if ($val === '') return $default;
        return in_array($val, ['y', 'yes', '1', 'true'], true);
    }

    /** @param string[] $choices */
    private function askChoice(string $label, array $choices, string $default): string
    {
        $list = implode('/', $choices);
        echo "  {$label} [{$list}] \033[2m[{$default}]\033[0m: ";
        $val = trim(fgets(STDIN));
        if ($val === '') return $default;
        if (in_array($val, $choices, true)) return $val;
        $this->out("\033[33m[WARN]\033[0m Invalid choice '{$val}', using default '{$default}'.");
        return $default;
    }

    private function askSecret(string $label): string
    {
        // Windows では stty が使えないため通常入力にフォールバック
        if (DIRECTORY_SEPARATOR === '\\') {
            echo "  {$label}: ";
            return trim(fgets(STDIN));
        }
        echo "  {$label}: ";
        system('stty -echo');
        $val = trim(fgets(STDIN));
        system('stty echo');
        echo PHP_EOL;
        return $val;
    }
}
