# Fzr Configuration Reference (env.ini)

`env.ini` は Fzr フレームワークの動作を制御するための中心的な設定ファイルです。

## [app] - 基本設定
アプリケーションのコアな挙動を定義します。

| キー | 説明 | デフォルト値 |
| :--- | :--- | :--- |
| `app.name` | アプリケーションの名称 | `Fzr App` |
| `app.key` | アプリ固有の暗号化キー (32文字) | (自動生成) |
| `app.debug` | デバッグモード (true で詳細エラー表示) | `false` |
| `app.lang` | 言語設定 (`ja`, `en` など) | `ja` |
| `app.timezone` | タイムゾーン | `Asia/Tokyo` |
| `app.charset` | 文字コード | `UTF-8` |
| `app.force_https` | HTTPS への強制リダイレクト | `false` |
| `app.login_page` | ログイン画面のURLパス | `/login` |
| `app.delimiter` | スラッグ等で使う区切り文字 | `-` |
| `app.cors_origin` | CORS許可オリジン (`*` または特定URL) | - |

## [path] - ディレクトリパス設定
システムが使用する物理パスを上書きできます。

| キー | 説明 | デフォルト値 |
| :--- | :--- | :--- |
| `path.app` | プログラム本体のルート | `app` |
| `path.storage` | ストレージのルート | `storage` |
| `path.db` | データベースファイルの保存先 | `storage/db` |
| `path.log` | ログファイルの出力先 | `storage/log` |
| `path.temp` | 一時ファイル/キャッシュの保存先 | `storage/temp` |

## [storage] - ストレージ設定
| キー | 説明 | デフォルト値 |
| :--- | :--- | :--- |
| `storage.driver` | デフォルトの保存先 (`local`, `s3`, `gcs`) | `local` |
| `storage.public_disk` | `Storage::public()` で使用するディスク名 | `public` |
| `storage.private_disk` | `Storage::private()` で使用するディスク名 | `private` |

### ディスク個別の定義方法
`[storage.DISKNAME]` セクションで、ディスクごとのプロパティ（`driver`, `root`, `url` 等）を定義します。

## [db] - データベース設定
| キー | 説明 | デフォルト値 |
| :--- | :--- | :--- |
| `db.driver` | 使用するDB (`sqlite`, `mysql`, `pgsql`) | `mysql` |
| `db.sqlite_path` | SQLite使用時のファイルパス | `storage/db/app.db` |
| `db.host` | DBホスト | `localhost` |
| `db.database` | データベース名 | - |
| `db.username` | ユーザー名 | - |
| `db.password` | パスワード | - |

## [session] / [cookie] - 接続・状態保持
| セクション | キー | 説明 | デフォルト値 |
| :--- | :--- | :--- | :--- |
| `session.name` | セッションクッキー名 | `SID` |
| `session.save_path` | セッションファイルの保存場所 | `storage/temp/sessions` |
| `session.auth_key` | ログイン情報のハッシュ用キー | (app.keyを使用) |
| `cookie.domain` | クッキーの有効ドメイン | - |
| `cookie.secure` | HTTPSのみに制限するか | (環境に応じて自動) |
| `cookie.httponly` | JSからのアクセスを禁止するか | `true` |

## [security] - セキュリティ設定
| キー | 説明 | デフォルト値 |
| :--- | :--- | :--- |
| `security.csrf_name` | CSRFトークンのキー名 | `csrf_token` |
| `security.allow_external_redirect` | 外部URLへのリダイレクト許可 | `false` |

## [log] - ロギング
| キー | 説明 | デフォルト値 |
| :--- | :--- | :--- |
| `log.output` | 出力先 (`file`, `stderr`, `null`) | `file` |
| `log.access` | アクセスログを記録するか | `true` |
| `log.db_exe` | **SQL実行(INSERT/UPDATE等)ログ** | **`true` (常に記録)** |
| `log.db_sel` | SQL検索(SELECT)ログ | `app.debug` に連動 |
| `log.debug` | 任意のデバッグログ | `app.debug` に連動 |
| `log.info` | 一般的な情報ログ | `true` |
| `log.warning` | 警告ログ | `true` |
| `log.error` | エラー/例外ログ | `true` |

## [view] - ビュー設定
| キー | 説明 | デフォルト値 |
| :--- | :--- | :--- |
| `view.base_template` | 基本となるベーステンプレート | `@layouts/base.php` |
| `view.error_prefix` | エラービューの接頭辞 | `errors/` |

---

# 高度な設定例

### 1. Cloud Run / Cloud Logging 対応 (stderr出力)
コンテナ環境など、ログを標準エラー出力に JSON 構造化して出したい場合。

```ini
[log]
output = stderr
access = true
debug = false
```

### 2. 公開イメージを S3 で管理する場合
`Storage::public()` の保存先のみをクラウドへ飛ばす設定。

```ini
[storage]
public_disk = s3_public

[storage.s3_public]
driver = s3
bucket = my-app-assets
region = ap-northeast-1
url = https://assets.example.com
```

### 3. 一時ファイルやセッションを /tmp に逃がす場合
書き込み制限のある環境などで、OS標準のテンポラリディレクトリを使用する場合。

```ini
[path]
temp = /tmp/fzr-cache

[session]
save_path = /tmp/fzr-sessions
```
