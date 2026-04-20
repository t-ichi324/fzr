<?php
namespace Fzr\Db;

use Fzr\Logger;

/**
 * エンティティ基底（ActiveRecord風）
 */
abstract class Entity extends \Fzr\Model {
    protected static ?string $connectionKey = null;

    /** 接続取得 */
    protected static function connection(): Connection {
        $key = static::$connectionKey ?: 'default';
        return Db::connection($key);
    }

    /** クエリビルダ取得 */
    public static function query(): Query {
        return new Query(static::connection(), static::tableName());
    }

    /** 全取得 */
    public static function all(): array {
        return static::query()->getAll();
    }

    /** ID検索 */
    public static function find(int|string $id): ?object {
        return static::query()->where(static::$primaryKey, $id)->getOne();
    }

    /** WHERE検索 */
    public static function where(string|array|\Closure $field, mixed $op = null, mixed $value = null): Query {
        return static::query()->where($field, $op, $value);
    }

    /** 件数取得 */
    public static function count(): int {
        return static::query()->count();
    }

    /** INSERT */
    public static function create(array $data): int|string {
        return static::query()->insert($data);
    }

    /** UPDATE (ID指定) */
    public static function updateById(int|string $id, array $data): int {
        return static::query()->where(static::$primaryKey, $id)->update($data);
    }

    /** DELETE (ID指定) */
    public static function deleteById(int|string $id): int {
        return static::query()->where(static::$primaryKey, $id)->delete();
    }

    /** 存在確認 */
    public static function exists(int|string $id): bool {
        return static::query()->where(static::$primaryKey, $id)->exists();
    }
}
