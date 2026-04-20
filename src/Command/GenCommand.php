<?php

namespace Fzr\Command;

use Fzr\Command;
use Fzr\Engine;
use Fzr\Db\Db;
use PDO;

/**
 * php fzr make:model [table_name ...] [-f]
 *
 * DBのテーブル構造を読み取り、Attribute付きのEntityクラスファイルを自動生成します。
 * オプション: -f  既存ファイルを上書きする
 */
class GenCommand extends Command
{
    public function description(): string
    {
        return 'Generate Entity/Model classes from database tables';
    }

    public function execute(): int
    {
        $force   = false;
        $targets = [];

        foreach ($this->args as $arg) {
            if ($arg === '-f') {
                $force = true;
            } else {
                $targets[] = $arg;
            }
        }

        // ── 1. Fzr 初期化（env.ini 読み込み） ─────────────────────
        $root = getcwd();
        $autoload = $root . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require $autoload;
        }

        if (!file_exists($root . '/app/env.ini')) {
            $this->error('app/env.ini not found. Run "php fzr init" first.');
            return 1;
        }

        define('ABSPATH', $root);
        Engine::init($root);

        // ── 2. テーブル一覧取得 ───────────────────────────────────
        $conn   = Db::pdo();
        $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

        if (empty($targets)) {
            $targets = $this->getTables($conn, $driver);
        }

        if (empty($targets)) {
            $this->out('No tables found in database.');
            return 0;
        }

        // ── 3. ファイル生成 ────────────────────────────────────────
        $outDir = $root . '/app/models';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }

        foreach ($targets as $table) {
            if ($table === 'migrations') continue;

            $className = $this->snakeToCamel(preg_replace('/s$/', '', $table));
            if ($className === '') $className = $this->snakeToCamel($table);

            $filePath = $outDir . '/' . $className . '.php';

            if (file_exists($filePath) && !$force) {
                $this->out("  [SKIP] $table -> $className (File already exists. Use -f to overwrite)");
                continue;
            }

            $this->out("  [GEN]  $table -> $className ...");
            $columns = $this->getColumns($conn, $driver, $table);
            $code    = $this->generateCode($className, $table, $columns);

            file_put_contents($filePath, $code);
        }

        $this->out('');
        $this->success('Done. Generated files in app/models/');
        return 0;
    }

    private function getTables(PDO $pdo, string $driver): array
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'");
        } else {
            $stmt = $pdo->query("SHOW TABLES");
        }
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getColumns(PDO $pdo, string $driver, string $table): array
    {
        $columns = [];
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info(`$table`)");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $columns[] = [
                    'name'    => $row['name'],
                    'type'    => strtolower($row['type']),
                    'notnull' => (bool)$row['notnull'],
                    'pk'      => (bool)$row['pk'],
                    'comment' => '',
                    'length'  => null,
                ];
            }
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->prepare("
                SELECT
                    column_name, data_type, is_nullable, column_default, character_maximum_length,
                    (SELECT description FROM pg_description WHERE objoid = :table::regclass AND objsubid = ordinal_position) as comment
                FROM information_schema.columns
                WHERE table_name = :table_name
                ORDER BY ordinal_position
            ");
            $stmt->execute(['table' => $table, 'table_name' => $table]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $columns[] = [
                    'name'    => $row['column_name'],
                    'type'    => strtolower($row['data_type']),
                    'notnull' => $row['is_nullable'] === 'NO',
                    'pk'      => strpos($row['column_default'] ?? '', 'nextval') !== false,
                    'comment' => $row['comment'] ?? '',
                    'length'  => $row['character_maximum_length'],
                ];
            }
        } else {
            $stmt = $pdo->query("SHOW FULL COLUMNS FROM `$table`");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                preg_match('/\((\d+)\)/', $row['Type'], $matches);
                $columns[] = [
                    'name'    => $row['Field'],
                    'type'    => strtolower(preg_replace('/\(.*\)/', '', $row['Type'])),
                    'notnull' => $row['Null'] === 'NO',
                    'pk'      => $row['Key'] === 'PRI',
                    'comment' => $row['Comment'] ?? '',
                    'length'  => isset($matches[1]) ? (int)$matches[1] : null,
                ];
            }
        }
        return $columns;
    }

    private function generateCode(string $className, string $table, array $columns): string
    {
        $props = "";
        foreach ($columns as $col) {
            $name  = $col['name'];
            $type  = $this->mapType($col['type']);
            $attrs = [];

            $label = $col['comment'] ?: $name;
            $attrs[] = "#[Label('$label')]";

            if ($col['notnull'] && !$col['pk']) {
                $attrs[] = "#[Required]";
            }
            if ($col['length'] && $type === 'string') {
                $attrs[] = "#[MaxLength({$col['length']})]";
            }
            if (strpos($col['type'], 'int') !== false) {
                $attrs[] = "#[Numeric]";
            }
            if ($name === 'email') {
                $attrs[] = "#[Email]";
            }

            $attrStr = implode("\n    ", $attrs);
            $props .= "    $attrStr\n    public $type \${$name};\n\n";
        }

        return <<<PHP
<?php
namespace App\Model;

use Fzr\Db\Entity;
use Fzr\Attr\Field\{Label, Required, MaxLength, Numeric, Email};

/**
 * $className エンティティ
 */
class $className extends Entity {
    protected static ?string \$table = '$table';

$props}
PHP;
    }

    private function mapType(string $dbType): string
    {
        if (strpos($dbType, 'int') !== false) return 'int';
        if (strpos($dbType, 'bool') !== false || $dbType === 'bit') return 'bool';
        if (strpos($dbType, 'float') !== false || strpos($dbType, 'double') !== false || strpos($dbType, 'decimal') !== false) return 'float';
        return 'string';
    }

    private function snakeToCamel(string $str): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $str)));
    }
}
