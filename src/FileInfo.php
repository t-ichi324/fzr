<?php
namespace Fzr;

/**
 * ファイル情報/操作
 */
class FileInfo {
    protected string $path;

    public function __construct(string $path) { $this->path = $path; }

    public function path(): string { return $this->path; }
    public function name(): string { return basename($this->path); }
    public function extension(): string { return pathinfo($this->path, PATHINFO_EXTENSION); }
    public function basename(): string { return pathinfo($this->path, PATHINFO_FILENAME); }
    public function directory(): string { return dirname($this->path); }
    public function exists(): bool { return file_exists($this->path); }
    public function isFile(): bool { return is_file($this->path); }
    public function isDir(): bool { return is_dir($this->path); }
    public function isReadable(): bool { return is_readable($this->path); }
    public function isWritable(): bool { return is_writable($this->path); }
    public function size(): int { return is_file($this->path) ? filesize($this->path) : 0; }
    public function mtime(): int { return is_file($this->path) ? filemtime($this->path) : 0; }

    /** 可読サイズ文字列 */
    public function readableSize(): string {
        $size = $this->size();
        if ($size === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exp = floor(log($size, 1024));
        return sprintf("%.1f %s", $size / pow(1024, $exp), $units[$exp]);
    }

    /** 内容読み取り */
    public function read(): string|false { return file_get_contents($this->path); }

    /** 内容書き込み */
    public function write(string $content, int $flags = 0): int|false { return file_put_contents($this->path, $content, $flags); }

    /** 削除 */
    public function delete(): bool {
        return is_file($this->path) ? unlink($this->path) : false;
    }

    /** コピー */
    public function copy(string $dest): bool { return copy($this->path, $dest); }

    /** 移動 */
    public function move(string $dest): bool { return rename($this->path, $dest); }
}
