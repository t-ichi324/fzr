<?php
namespace Fzr;

/**
 * CLI専用ディスパッチャ
 */
class CliEngine {
    /** CLIディスパッチ */
    public static function dispatch(array $argv): void {
        // コマンドディレクトリを動的にオートロードに追加
        Engine::autoload(Path::app("commands"));

        $commandName = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);

        if ($commandName === 'help') {
            self::showHelp();
            return;
        }

        $className = self::toClassCase($commandName) . 'Command';
        
        // 1. システム内蔵コマンドのチェック (\Fzr\Cli\...)
        $internalClass = "\\Fzr\\Cli\\" . $className;
        if (class_exists($internalClass)) {
            $command = new $internalClass($args);
            exit($command->execute());
        }

        // 2. ユーザー定義コマンドのチェック
        $userClass = "\\App\\Commands\\" . $className;
        if (class_exists($userClass)) {
            $command = new $userClass($args);
            if ($command instanceof Command) {
                exit($command->execute());
            }
        } elseif (class_exists($className)) {
            // パッケージ等のコマンド
            $command = new $className($args);
            if ($command instanceof Command) {
                exit($command->execute());
            }
        }
        
        echo "\033[31mError: Command '$commandName' not found.\033[0m" . PHP_EOL;
        self::showHelp();
        exit(1);
    }

    public static function showHelp(): void {
        echo "\033[33mUsage:\033[0m" . PHP_EOL;
        echo "  php index.php [command] [arguments]" . PHP_EOL . PHP_EOL;
        echo "\033[33mAvailable commands:\033[0m" . PHP_EOL;
        echo "  help           Show this help message" . PHP_EOL;
        if (class_exists("\\Fzr\\Cli\\CacheClearCommand")) {
            echo "  cache:clear    Clear the application cache" . PHP_EOL;
        }
        if (class_exists("\\Fzr\\Cli\\MigrateCommand")) {
            echo "  migrate        Execute registered database migrations" . PHP_EOL;
        }
        if (class_exists("\\Fzr\\Cli\\DbEntityCommand")) {
            echo "  db:entity      Generate/Update Entity/Model" . PHP_EOL;
        }
    }

    private static function toClassCase(string $str): string {
        return implode('', array_map('ucfirst', preg_split('/[\.\-_: 　]+/', $str, -1, PREG_SPLIT_NO_EMPTY)));
    }
}
