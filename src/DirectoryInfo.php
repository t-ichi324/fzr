<?php
namespace Fzr;

/**
 * ディレクトリ情報/操作
 */
class DirectoryInfo {
    protected string $path;

    public function __construct(string $path) { $this->path = rtrim($path, '/\\'); }

    public function path(): string { return $this->path; }
    public function name(): string { return basename($this->path); }
    public function exists(): bool { return is_dir($this->path); }
    public function isReadable(): bool { return is_readable($this->path); }
    public function isWritable(): bool { return is_writable($this->path); }

    /** ディレクトリ作成 */
    public function create(int $permissions = 0777): bool {
        if (is_dir($this->path)) return true;
        return mkdir($this->path, $permissions, true);
    }

    /** ファイル一覧取得 */
    public function files(?string $pattern = null): array {
        if (!is_dir($this->path)) return [];
        if ($pattern !== null) {
            $files = glob($this->path . DIRECTORY_SEPARATOR . $pattern);
        } else {
            $files = array_filter(
                array_map(fn($f) => $this->path . DIRECTORY_SEPARATOR . $f, array_diff(scandir($this->path), ['.', '..'])),
                'is_file'
            );
        }
        return array_values(array_map(fn($f) => new FileInfo($f), $files ?: []));
    }

    /** サブディレクトリ一覧取得 */
    public function dirs(): array {
        if (!is_dir($this->path)) return [];
        $dirs = array_filter(
            array_map(fn($d) => $this->path . DIRECTORY_SEPARATOR . $d, array_diff(scandir($this->path), ['.', '..'])),
            'is_dir'
        );
        return array_values(array_map(fn($d) => new DirectoryInfo($d), $dirs));
    }

    /** 再帰削除 */
    public function deleteRecursive(): bool {
        if (!$this->empty()) return false;
        return rmdir($this->path);
    }

    /** 中身を空にする（ディレクトリ本体は残す） */
    public function empty(): bool {
        if (!is_dir($this->path)) return false;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        return true;
    }
}
