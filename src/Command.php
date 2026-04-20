<?php
namespace Fzr;

/**
 * CLIコマンドベースクラス
 */
abstract class Command {
    protected array $args = [];

    public function __construct(array $args = []) {
        $this->args = $args;
    }

    /** コマンド実行 */
    abstract public function execute(): int;

    /** コマンド説明 */
    public function description(): string {
        return "";
    }

    /** 標準出力 */
    protected function out(string $msg): void {
        echo $msg . PHP_EOL;
    }

    /** 成功出力 */
    protected function success(string $msg): void {
        echo "\033[32mSUCCESS: $msg\033[0m" . PHP_EOL;
    }

    /** エラー出力 */
    protected function error(string $msg): void {
        echo "\033[31mERROR: $msg\033[0m" . PHP_EOL;
    }
}
