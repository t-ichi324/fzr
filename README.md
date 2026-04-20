# Fzr (Feather)

軽量PHP フレームワーク。PHP 8.1+ / Composer / Cloud Run 対応。

## インストール

```bash
composer require fzr/fw
```

ローカル利用の場合:
```bash
cd your-project
composer init
composer config repositories.fzr path ../fzr
composer require fzr/fw:@dev
```

## CLI アプリケーションスキャフォールド

Fzrには、モダンなCLIベースの対話型セットアップ・エンジンが組み込まれています。
以下のコマンドを実行するだけで、プロジェクトの全ディレクトリ構造・設定ファイル・ベーステンプレートが即座に自動生成されます。

### 使い方

ターミナルで初期セットアップコマンドを実行します:
```bash
php fzr init
```

1. 実行環境プリセット (standard / gcp / aws) などを選択します。
2. アプリケーション名やデータベースなどの設定を入力（またはデフォルトを採用）します。
3. すべての初期ファイル群がデプロイされ、すぐに開発を開始できる状態になります。

### セットアップで生成されるファイル

| ファイル | 内容 |
|----------|------|
| `app/env.ini` | 設定ファイル |
| `app/bootstrap.php` | DB接続・初期化 |
| `app/controllers/IndexController.php` | 初期コントローラ |
| `app/views/index.php` | 初期ビュー |
| `app/views/@layouts/base.php` | ベーステンプレート |
| `.htaccess` | URLリライト |
| `index.php` | エントリーポイント |

## クイックスタート（手動構成）

### 最小構成

```
your-project/
├── composer.json
├── index.php
├── app/
│   ├── env.ini              (省略可: 環境変数で代替)
│   ├── bootstrap.php
│   ├── controllers/
│   │   └── IndexController.php
│   └── views/
│       └── index.php
└── vendor/
```

### index.php（エントリーポイント）

```php
<?php
require __DIR__ . '/vendor/autoload.php';
// require __DIR__ . '/vendor/ag/fzr/src/aliases.php'; // ← min-web互換が必要な場合

use Fzr\Engine;
use Fzr\Path;

define('ABSPATH', __DIR__);
define('APP_START_TIME', microtime(true));

// 初期化
Engine::init(__DIR__, 'app/env.ini');

// オートローダー登録（コントローラ/モデル等）
Engine::autoload('app/controllers', 'app/models');

// ブートストラップ
Engine::bootstrap(__DIR__ . '/app/bootstrap.php');

// エラーハンドリング
Engine::onError(function(\Throwable $ex) {
    // カスタムエラー処理
});

// ディスパッチ
Engine::dispatch();
```

### bootstrap.php

```php
<?php
use Fzr\Db\Db;
use Fzr\Db\LiteDb;
use Fzr\Env;
use Fzr\Logger;

// Cloud Runの場合はstderrロギング
if (getenv('K_SERVICE')) { // Cloud Run環境判定
    Logger::setOutput('stderr');
}

// DB設定（SQLite）
$db = LiteDb::create('app');
Db::addConnection('default', $db);
Db::migrate();
```

### コントローラ

```php
<?php
use Fzr\Controller;
use Fzr\Response;
use Fzr\Request;
use Fzr\Render;
use Fzr\Attr\Http\Csrf;
use Fzr\Attr\Http\Role;

class IndexController extends Controller {

    public function index() {
        Render::setTitle('ホーム');
        return Response::view('index');
    }

    #[Csrf]
    public function _post_save() {
        $name = Request::post('name');
        // ...
        return Response::redirect('index');
    }

    #[Role('admin')]
    public function admin() {
        return Response::view('admin');
    }
}
```

## 名前空間

全クラスは `Fzr\` 名前空間に配置:

| クラス | 用途 |
|--------|------|
| `Fzr\Engine` | エンジン/ディスパッチ |
| `Fzr\Context` | 実行コンテキスト |
| `Fzr\Request` | リクエスト処理 |
| `Fzr\Response` | レスポンス処理 |
| `Fzr\Render` | テンプレート |
| `Fzr\Route` | ルーティング |
| `Fzr\Env` | 設定（INI + 環境変数） |
| `Fzr\Logger` | ログ（file / stderr） |
| `Fzr\Auth` | 認証 |
| `Fzr\Security` | セキュリティ（CSRF/IP） |
| `Fzr\Session` | セッション |
| `Fzr\Cookie` | Cookie |
| `Fzr\Cache` | キャッシュ（ドライバ対応） |
| `Fzr\Form` | フォーム処理 |
| `Fzr\Model` | モデル基底 |
| `Fzr\Collection` | コレクション |
| `Fzr\Path` | 物理パス |
| `Fzr\Url` | URL生成 |
| `Fzr\Db\Db` | DBファサード |
| `Fzr\Db\Query` | クエリビルダ |
| `Fzr\Db\Entity` | ActiveRecord |
| `Fzr\Db\LiteDb` | SQLiteラッパー |
| `Fzr\Db\Vector` | pgvector ベクトル検索 (RAG) |

## min-web 互換モード

`aliases.php` を読み込むことで、グローバルクラス名がそのまま使える:

```php
require __DIR__ . '/vendor/fzr/fw/src/aliases.php';

// \Request でアクセス可能
Request::get('id');
Auth::check();
Db::table('users')->where('active', 1)->getAll();
```

## Cloud Run 対応

### 環境変数による設定

`env.ini` が不要。環境変数で全ての設定が可能:

| INI キー | 環境変数 |
|----------|----------|
| `db.host` | `DB_HOST` |
| `db.driver` | `DB_DRIVER` |
| `db.database` | `DB_DATABASE` |
| `db.username` | `DB_USERNAME` |
| `db.password` | `DB_PASSWORD` |
| `db.schema` | `DB_SCHEMA` |
| `debug_mode` | `DEBUG_MODE` |
| `app_name` | `APP_NAME` |
| `force_https` | `FORCE_HTTPS` |

キーのドットはアンダースコアに変換され、大文字化して検索される。

### ログ出力

```php
// stderr に構造化JSON出力（Cloud Logging自動取り込み）
Logger::setOutput('stderr');
```

出力例:
```json
{"severity":"INFO","message":"GET 200","time":"2026-04-18 19:00:00","requestId":"a1b2c3d4","userId":"-"}
```

### exit制御

```php
// Cloud Functions / テスト環境で exit を無効化
Response::setExitOnSend(false);
```

### キャッシュドライバ

```php
// Redis等の外部ドライバ設定
Cache::setDriver(new MyRedisCache());
```

ドライバは `get(string $key, int $ttl, callable $closure): mixed` メソッドを実装すればよい。

## Attributes

### HTTP Attributes

```php
use Fzr\Attr\Http\{Csrf, Api, Role, AllowCors, AllowCache, AllowIframe, IsReadOnly, IpWhitelist};

#[Api]              // APIモード（JSONレスポンス）
#[Csrf]             // CSRF検証必須
#[Role('admin')]    // ロール制限
#[AllowCors]        // CORS許可
#[AllowCache(3600)] // キャッシュ許可
#[AllowIframe]      // iframe埋め込み許可
#[IsReadOnly]       // POST/PUT/DELETE 禁止
#[IpWhitelist]      // IP制限
```

### フィールド Attributes

```php
use Fzr\Attr\Field\{Label, Required, MaxLength, MinLength, Email, Numeric, Url, Regex, In};

class User extends \Fzr\Model {
    #[Label('ユーザー名')]
    #[Required]
    #[MaxLength(50)]
    public string $name;

    #[Label('メール')]
    #[Required]
    #[Email]
    public string $email;

    #[Label('権限')]
    #[In('admin', 'user', 'guest')]
    public string $role;
}
```

## DB操作

対応ドライバ: **SQLite** / **MySQL** / **PostgreSQL**

```php
use Fzr\Db\Db;

// テーブルクエリ
$users = Db::table('users')
    ->where('active', 1)
    ->orderBy('created_at', 'DESC')
    ->getAll();

// ページネーション
$result = Db::table('posts')
    ->where('published', 1)
    ->page(Request::getInt('page', 1), 20);

echo $result->links();

// トランザクション
Db::transaction(function($pdo) {
    Db::table('accounts')->where('id', 1)->update(['balance' => 500]);
    Db::table('logs')->insert(['action' => 'transfer', 'amount' => 100]);
});

// RAW SQL
$rows = Db::select("SELECT * FROM users WHERE age > :age", ['age' => 18]);
```

## ベクトル検索 / RAG（PostgreSQL pgvector）

PostgreSQL + pgvector を利用して、Embedding ベースの類似検索（RAG）が可能。

### セットアップ

```php
// bootstrap.php
use Fzr\Db\Db;
use Fzr\Db\Connection;
use Fzr\Db\Vector;

// PostgreSQL接続
Db::addConnection('default', Connection::fromEnv());

// pgvector 初期化
$vec = new Vector(Db::connection());
$vec->ensureExtension(); // CREATE EXTENSION IF NOT EXISTS vector
```

### テーブル作成

```php
$vec = new Vector(Db::connection());

// 1536次元 (OpenAI text-embedding-3-small)
$vec->createTable('documents', 1536, [
    'title TEXT NOT NULL',
    'content TEXT NOT NULL',
    'source TEXT',
    'metadata JSONB',
]);
```

### データ挿入

```php
// OpenAI API等でEmbeddingを生成した後
$embedding = [0.012, -0.034, 0.056, ...]; // 1536次元のfloat配列

$id = $vec->insert('documents', $embedding, [
    'title'    => 'ドキュメントタイトル',
    'content'  => 'ドキュメント本文テキスト...',
    'source'   => 'manual_v2.pdf',
    'metadata' => ['page' => 15, 'chapter' => 3],
]);

// バルクインサート
$vec->bulkInsert('documents', [
    ['embedding' => [...], 'title' => 'Doc 1', 'content' => '...'],
    ['embedding' => [...], 'title' => 'Doc 2', 'content' => '...'],
]);
```

### 類似検索

```php
// クエリベクトルで類似検索（コサイン類似度）
$results = $vec->search('documents', $queryEmbedding, limit: 10);

foreach ($results as $row) {
    echo "{$row->title} (distance: {$row->distance})\n";
    echo $row->content . "\n\n";
}

// L2距離での検索
$results = $vec->search('documents', $queryEmbedding, 10, Vector::L2);

// WHERE条件付き検索
$results = $vec->search('documents', $queryEmbedding, 10, Vector::COSINE, [
    'source' => 'manual_v2.pdf'
]);
```

### RAGコンテキスト取得

LLM に渡すコンテキストを一発で取得:

```php
$result = $vec->getContext(
    table: 'documents',
    queryEmbedding: $queryEmbedding,
    contentColumn: 'content',
    limit: 5,
    maxDistance: 0.8
);

// $result['context']  → 関連テキストを結合した文字列
// $result['sources']  → 元の行データ配列
// $result['count']    → ヒット件数

// LLMプロンプト例
$prompt = "以下のコンテキストを参考に質問に回答してください。\n\n"
        . "コンテキスト:\n{$result['context']}\n\n"
        . "質問: {$userQuestion}";
```

### 距離関数

| 定数 | 演算子 | 用途 |
|------|--------|------|
| `Vector::COSINE` | `<=>` | コサイン距離（デフォルト、推奨） |
| `Vector::L2` | `<->` | ユークリッド距離 |
| `Vector::INNER_PRODUCT` | `<#>` | 内積 |

### インデックス管理

```php
// IVFFlat インデックス再構築（データ量増加後）
$vec->reindex('documents', lists: 200);

// HNSW インデックス（高精度、構築は遅い）
$vec->createHnswIndex('documents');
```

## グローバル関数

```php
h($str)           // HTMLエスケープ
e($str)           // HTMLエスケープ（エイリアス）
url('path')       // URL生成
env('key', 'def') // 設定値取得
csrf_field()      // CSRFフィールドHTML
csrf_token()      // CSRFトークン値
collect([...])    // Collection生成
redirect('url')   // リダイレクト応答
view('template')  // ビュー応答
dd($var)          // デバッグダンプ＋終了
```

## ライセンス

MIT
