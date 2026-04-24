<?php

namespace Fzr;

/**
 * CLI コマンド基底クラス
 *
 * app/commands/ 以下のコマンドファイルで任意に extends して使う。
 * extends せず普通のスクリプトとして書いてもよい。
 *
 * 使用例:
 *   class MakeModelCommand extends Command {
 *       public function handle(): int {
 *           $force  = $this->hasFlag('-f');
 *           $result = \Fzr\Db\Db::generateModels(ABSPATH . '/app/models', $force);
 *           foreach ($result['generated'] as $item) {
 *               $this->success("[GEN] {$item['table']} -> {$item['class']}");
 *           }
 *           return 0;
 *       }
 *   }
 *   exit((new MakeModelCommand(array_slice($argv, 2)))->handle());
 */
abstract class Command
{
    protected array $argv = [];

    public function __construct(array $argv = [])
    {
        $this->argv = $argv;
    }

    abstract public function handle(): int;

    // ── 出力ヘルパー ───────────────────────────────────────────────────

    protected function line(string $msg = ''): void
    {
        echo $msg . PHP_EOL;
    }

    protected function info(string $msg): void
    {
        echo "\033[36m{$msg}\033[0m" . PHP_EOL;
    }

    protected function success(string $msg): void
    {
        echo "\033[32m{$msg}\033[0m" . PHP_EOL;
    }

    protected function warn(string $msg): void
    {
        echo "\033[33m{$msg}\033[0m" . PHP_EOL;
    }

    protected function error(string $msg): void
    {
        echo "\033[31m{$msg}\033[0m" . PHP_EOL;
    }

    // ── 引数ヘルパー ───────────────────────────────────────────────────

    /** 位置引数を取得 (0始まり) */
    protected function arg(int $n, mixed $default = null): mixed
    {
        return $this->argv[$n] ?? $default;
    }

    /** フラグの有無を確認 (-f, --verbose など) */
    protected function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->argv, true);
    }

    /** --key=value 形式のオプションを取得 */
    protected function option(string $key): ?string
    {
        foreach ($this->argv as $a) {
            if (str_starts_with($a, "--{$key}=")) {
                return substr($a, strlen($key) + 3);
            }
        }
        return null;
    }
}
