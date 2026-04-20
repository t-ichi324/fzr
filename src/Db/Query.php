<?php
namespace Fzr\Db;

use Fzr\Logger;

/**
 * クエリビルダ
 */
class Query {
    protected Connection $connection;
    protected string $table;
    protected array $select = ['*'];
    protected array $where = [];
    protected array $params = [];
    protected ?string $orderBy = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected ?string $groupBy = null;
    protected ?string $having = null;
    protected array $joins = [];
    protected bool $distinct = false;

    public function __construct(Connection $connection, string $table) {
        $this->connection = $connection;
        $this->table = $table;
    }

    /** SELECT指定 */
    public function select(string ...$columns): self {
        $this->select = $columns;
        return $this;
    }

    /** DISTINCT */
    public function distinct(): self {
        $this->distinct = true;
        return $this;
    }

    /**
     * WHERE条件追加
     * @param string|array|\Closure $field
     * @param mixed $op
     * @param mixed $value
     */
    public function where(string|array|\Closure $field, mixed $op = null, mixed $value = null): self {
        if ($field instanceof \Closure) {
            $sub = new self($this->connection, $this->table);
            $field($sub);
            if (!empty($sub->where)) {
                $this->where[] = ['type' => 'group', 'conditions' => $sub->where, 'connector' => 'AND'];
                $this->params = array_merge($this->params, $sub->params);
            }
            return $this;
        }

        if (is_array($field)) {
            foreach ($field as $k => $v) {
                $this->where($k, '=', $v);
            }
            return $this;
        }

        if ($value === null && $op !== null) {
            $value = $op;
            $op = '=';
        }

        $op = strtoupper(trim($op ?? '='));

        if ($value === null) {
            $this->where[] = ['type' => 'raw', 'sql' => ($op === '!=' || $op === '<>' ? "{$field} IS NOT NULL" : "{$field} IS NULL")];
        } else {
            $placeholder = ':w' . count($this->params);
            $this->where[] = ['type' => 'condition', 'sql' => "{$field} {$op} {$placeholder}", 'connector' => 'AND'];
            $this->params[$placeholder] = $value;
        }

        return $this;
    }

    /** OR WHERE */
    public function orWhere(string|array|\Closure $field, mixed $op = null, mixed $value = null): self {
        $prevCount = count($this->where);
        $this->where($field, $op, $value);
        if (count($this->where) > $prevCount) {
            $last = array_pop($this->where);
            $last['connector'] = 'OR';
            $this->where[] = $last;
        }
        return $this;
    }

    /** WHERE IN */
    public function whereIn(string $field, array $values): self {
        if (empty($values)) { $this->where[] = ['type' => 'raw', 'sql' => '0=1']; return $this; }
        $placeholders = [];
        foreach ($values as $i => $v) {
            $k = ':wi' . count($this->params);
            $placeholders[] = $k;
            $this->params[$k] = $v;
        }
        $this->where[] = ['type' => 'raw', 'sql' => "{$field} IN (" . implode(',', $placeholders) . ")"];
        return $this;
    }

    /** WHERE NOT IN */
    public function whereNotIn(string $field, array $values): self {
        if (empty($values)) return $this;
        $placeholders = [];
        foreach ($values as $i => $v) {
            $k = ':wni' . count($this->params);
            $placeholders[] = $k;
            $this->params[$k] = $v;
        }
        $this->where[] = ['type' => 'raw', 'sql' => "{$field} NOT IN (" . implode(',', $placeholders) . ")"];
        return $this;
    }

    /** WHERE BETWEEN */
    public function whereBetween(string $field, mixed $min, mixed $max): self {
        $k1 = ':wb' . count($this->params);
        $this->params[$k1] = $min;
        $k2 = ':wb' . count($this->params);
        $this->params[$k2] = $max;
        $this->where[] = ['type' => 'raw', 'sql' => "{$field} BETWEEN {$k1} AND {$k2}"];
        return $this;
    }

    /** WHERE LIKE */
    public function whereLike(string $field, string $pattern): self {
        $k = ':wl' . count($this->params);
        $this->params[$k] = $pattern;
        $this->where[] = ['type' => 'condition', 'sql' => "{$field} LIKE {$k}", 'connector' => 'AND'];
        return $this;
    }

    /** WHERE RAW */
    public function whereRaw(string $sql, array $bindings = []): self {
        $mapped = [];
        foreach ($bindings as $i => $v) {
            $k = ':wr' . count($this->params);
            $mapped[$k] = $v;
            $sql = preg_replace('/\?/', $k, $sql, 1);
        }
        $this->where[] = ['type' => 'raw', 'sql' => $sql];
        $this->params = array_merge($this->params, $mapped);
        return $this;
    }

    /** JOIN */
    public function join(string $table, string $on, string $type = 'INNER'): self {
        $this->joins[] = "{$type} JOIN {$table} ON {$on}";
        return $this;
    }

    /** LEFT JOIN */
    public function leftJoin(string $table, string $on): self { return $this->join($table, $on, 'LEFT'); }

    /** RIGHT JOIN */
    public function rightJoin(string $table, string $on): self { return $this->join($table, $on, 'RIGHT'); }

    /** ORDER BY */
    public function orderBy(string $column, string $direction = 'ASC'): self {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy = ($this->orderBy ? $this->orderBy . ', ' : '') . $this->quoteIdentifier($column) . " {$direction}";
        return $this;
    }

    /** GROUP BY */
    public function groupBy(string $column): self { $this->groupBy = $column; return $this; }

    /** HAVING */
    public function having(string $sql): self { $this->having = $sql; return $this; }

    /** LIMIT */
    public function limit(int $limit): self { $this->limit = $limit; return $this; }

    /** OFFSET */
    public function offset(int $offset): self { $this->offset = $offset; return $this; }

    /** ページネーション */
    public function page(int $page, int $perPage = 20): Result {
        $page = max(1, $page);
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*) as cnt FROM {$this->table}" . $this->buildJoins() . $this->buildWhere();
        $stmt = $this->connection->getPdo()->prepare($countSql);
        $stmt->execute($this->params);
        $total = (int)$stmt->fetchColumn();

        $rows = $this->getAll();

        return new Result($rows, $total, $page, $perPage);
    }

    // =============================
    // 実行系
    // =============================

    /** 全行取得 */
    public function getAll(): array {
        $sql = $this->buildSelect();
        return $this->executeSelect($sql);
    }

    /** 1行取得 */
    public function getOne(): ?object {
        $this->limit = 1;
        $sql = $this->buildSelect();
        $rows = $this->executeSelect($sql);
        return $rows[0] ?? null;
    }

    /** 1値取得 */
    public function getValue(string $column): mixed {
        $this->select = [$column];
        $this->limit = 1;
        $sql = $this->buildSelect();
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($this->params);
        Logger::db($this->connection->getKey(), 3, $sql, $this->params);
        return $stmt->fetchColumn();
    }

    /** 件数取得 */
    public function count(): int {
        $sql = "SELECT COUNT(*) FROM {$this->table}" . $this->buildJoins() . $this->buildWhere();
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($this->params);
        Logger::db($this->connection->getKey(), 3, $sql, $this->params);
        return (int)$stmt->fetchColumn();
    }

    /** SUM */
    public function sum(string $column): float|int {
        return $this->getValue("SUM({$column})") ?? 0;
    }

    /** MAX */
    public function max(string $column): mixed {
        return $this->getValue("MAX({$column})");
    }

    /** MIN */
    public function min(string $column): mixed {
        return $this->getValue("MIN({$column})");
    }

    /** 存在確認 */
    public function exists(): bool { return $this->count() > 0; }

    // =============================
    // 更新系
    // =============================

    /** INSERT */
    public function insert(array $data): int|string {
        $quotedColumns = array_map(fn($c) => $this->quoteIdentifier($c), array_keys($data));
        $columns = implode(', ', $quotedColumns);
        $placeholders = [];
        $params = [];
        foreach ($data as $k => $v) {
            $ph = ':i_' . preg_replace('/[^A-Za-z0-9_]/', '_', $k);
            $placeholders[] = $ph;
            $params[$ph] = $v;
        }
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES (" . implode(', ', $placeholders) . ")";
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        Logger::db($this->connection->getKey(), 2, $sql, $params);
        return $pdo->lastInsertId();
    }

    /** UPDATE */
    public function update(array $data): int {
        $sets = [];
        $params = [];
        foreach ($data as $k => $v) {
            $ph = ':u_' . preg_replace('/[^A-Za-z0-9_]/', '_', $k);
            $sets[] = $this->quoteIdentifier($k) . " = {$ph}";
            $params[$ph] = $v;
        }
        $params = array_merge($params, $this->params);
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . $this->buildWhere();
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($params);
        Logger::db($this->connection->getKey(), 2, $sql, $params);
        return $stmt->rowCount();
    }

    /** DELETE */
    public function delete(): int {
        $sql = "DELETE FROM {$this->table}" . $this->buildWhere();
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($this->params);
        Logger::db($this->connection->getKey(), 2, $sql, $this->params);
        return $stmt->rowCount();
    }

    // =============================
    // ビルダー
    // =============================

    protected function buildSelect(): string {
        $dist = $this->distinct ? 'DISTINCT ' : '';
        $sql = "SELECT {$dist}" . implode(', ', $this->select) . " FROM {$this->table}";
        $sql .= $this->buildJoins();
        $sql .= $this->buildWhere();
        if ($this->groupBy) $sql .= " GROUP BY {$this->groupBy}";
        if ($this->having) $sql .= " HAVING {$this->having}";
        if ($this->orderBy) $sql .= " ORDER BY {$this->orderBy}";
        if ($this->limit !== null) $sql .= " LIMIT {$this->limit}";
        if ($this->offset !== null) $sql .= " OFFSET {$this->offset}";
        return $sql;
    }

    protected function buildWhere(): string {
        if (empty($this->where)) return '';
        $parts = [];
        foreach ($this->where as $i => $w) {
            $connector = ($i === 0) ? '' : (' ' . ($w['connector'] ?? 'AND') . ' ');
            if ($w['type'] === 'group') {
                $groupParts = [];
                foreach ($w['conditions'] as $j => $c) {
                    $gc = ($j === 0) ? '' : (' ' . ($c['connector'] ?? 'AND') . ' ');
                    $groupParts[] = $gc . $c['sql'];
                }
                $parts[] = $connector . '(' . implode('', $groupParts) . ')';
            } else {
                $parts[] = $connector . $w['sql'];
            }
        }
        return ' WHERE ' . implode('', $parts);
    }

    protected function buildJoins(): string {
        return empty($this->joins) ? '' : ' ' . implode(' ', $this->joins);
    }

    protected function executeSelect(string $sql): array {
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($this->params);
        Logger::db($this->connection->getKey(), 3, $sql, $this->params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * 識別子（カラム名・テーブル名）をドライバに応じてクォートする。
     * table.column 形式にも対応。
     */
    protected function quoteIdentifier(string $identifier): string {
        $driver = $this->connection->getDriver();
        $q = $driver === 'mysql' ? '`' : '"';
        // table.column 形式を分割してそれぞれクォート
        return implode('.', array_map(
            fn($part) => $q . str_replace($q, $q . $q, $part) . $q,
            explode('.', $identifier)
        ));
    }
}
