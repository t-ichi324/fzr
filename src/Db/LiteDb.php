<?php
namespace Fzr\Db;

use Fzr\Path;

/**
 * SQLiteラッパー
 */
class LiteDb extends Connection {
    public function __construct(string $key, ?string $path = null) {
        parent::__construct($key, [
            'driver' => 'sqlite',
            'sqlitePath' => $path,
        ]);
    }

    /**
     * app/db ディレクトリにSQLiteファイルを作成/接続
     */
    public static function create(string $name): self {
        $path = Path::db($name . '.db');
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        return new self($name, $path);
    }
}
