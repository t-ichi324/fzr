<?php
namespace Fzr\Db;

use Fzr\Env;
use Fzr\Logger;

/**
 * DBファサード
 * 複数接続の管理と共通 shorthand メソッド
 */
class Db {
    /** @var Connection[] */
    protected static array $connections = [];

    /** 接続登録 */
    public static function addConnection(string $key, Connection $connection): void {
        self::$connections[$key] = $connection;
    }

    /** 接続取得 */
    public static function connection(string $key = 'default'): Connection {
        if (!isset(self::$connections[$key])) {
            // Envから自動生成
            self::$connections[$key] = Connection::fromEnv($key === 'default' ? 'db' : $key);
        }
        return self::$connections[$key];
    }

    /** PDO取得 */
    public static function pdo(string $key = 'default'): \PDO {
        return self::connection($key)->getPdo();
    }

    /** テーブルクエリ開始 */
    public static function table(string $table, string $connectionKey = 'default'): Query {
        return new Query(self::connection($connectionKey), $table);
    }

    /** RAW SQL実行（SELECT） */
    public static function select(string $sql, array $params = [], string $connectionKey = 'default'): array {
        $pdo = self::pdo($connectionKey);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        Logger::db($connectionKey, 3, $sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /** RAW SQL実行（INSERT/UPDATE/DELETE） */
    public static function execute(string $sql, array $params = [], string $connectionKey = 'default'): int {
        $pdo = self::pdo($connectionKey);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        Logger::db($connectionKey, 2, $sql, $params);
        return $stmt->rowCount();
    }

    /** トランザクション */
    public static function transaction(callable $callback, string $connectionKey = 'default'): mixed {
        $pdo = self::pdo($connectionKey);
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            Logger::exception("Transaction rolled back", $ex);
            throw $ex;
        }
    }

    /** マイグレーション実行 */
    public static function migrate(?string $dir = null, string $connectionKey = 'default'): void {
        $migration = new Migration(self::connection($connectionKey));
        $migration->run($dir);
    }

    /** 全接続切断 */
    public static function disconnectAll(): void {
        foreach (self::$connections as $conn) {
            $conn->disconnect();
        }
    }
}
