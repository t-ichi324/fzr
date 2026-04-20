<?php

namespace Fzr;

/**
 * ストレージアダプターのインターフェース
 * (Local, S3, GCS等で実装を切り替えるための規約)
 */
interface StorageAdapter
{
    public function exists(string $path): bool;
    public function get(string $path): string|false;
    public function put(string $path, string $contents): bool;
    public function delete(string $path): bool;
    public function size(string $path): int;
    public function lastModified(string $path): int;
    public function url(string $path): string;
}

/**
 * ストレージ操作Facade
 * 
 * アプリケーションからはこのクラスを経由してファイルを保存・取得する。
 * 環境変数('storage_driver')によって記録先を切り替え可能。
 */
class Storage
{
    /** @var StorageAdapter[] */
    protected static array $disks = [];

    /**
     * 指定した名称のディスク（アダプタ）を取得
     */
    public static function disk(?string $name = null): StorageAdapter
    {
        // 1. 指定なしなら env のデフォルト名 (未設定なら 'default')
        $name = $name ?: Env::get('storage.default_disk', 'default');

        if (!isset(self::$disks[$name])) {
            self::$disks[$name] = self::createDisk($name);
        }
        return self::$disks[$name];
    }

    /**
     * よく使う「公開」ディスクへのショートカット
     */
    public static function public(): StorageAdapter
    {
        // 2. 公開用として扱うディスク名をを env から取得 (未設定なら 'public')
        return self::disk(Env::get('storage.public_disk', 'public'));
    }

    /**
     * よく使う「非公開」ディスクへのショートカット
     */
    public static function private(): StorageAdapter
    {
        // 3. 非公開用として扱うディスク名をを env から取得 (未設定なら 'private')
        return self::disk(Env::get('storage.private_disk', 'private'));
    }

    /**
     * デフォルトのアダプタを互換性のために取得
     * @deprecated Use disk() instead.
     */
    public static function adapter(): StorageAdapter
    {
        return self::disk();
    }

    /**
     * 設定からアダプタを生成
     */
    protected static function createDisk(string $name): StorageAdapter
    {
        $prefix = ($name === 'default') ? 'storage' : "storage.{$name}";
        $driver = Env::get("{$prefix}.driver", Env::get('storage.driver', 'local'));

        if ($driver === 'local') {
            $defaultRoot = ($name === 'public') ? Path::storage('public') : (($name === 'private') ? Path::storage('private') : Path::storage());
            $defaultUrl = ($name === 'public') ? '/storage/public' : '/storage';
            
            return new LocalStorageAdapter(
                Env::get("{$prefix}.root", $defaultRoot),
                Env::get("{$prefix}.url", $defaultUrl)
            );
        }

        if ($driver === 's3') {
            return new S3StorageAdapter(
                Env::get("{$prefix}.bucket", Env::get('s3_bucket', '')),
                Env::get("{$prefix}.region", Env::get('s3_region', '')),
                Env::get("{$prefix}.key", Env::get('s3_key', '')),
                Env::get("{$prefix}.secret", Env::get('s3_secret', '')),
                Env::get("{$prefix}.url", Env::get('s3_url', ''))
            );
        }

        if ($driver === 'gcs') {
            return new GcsStorageAdapter(
                Env::get("{$prefix}.bucket", Env::get('gcs_bucket', '')),
                Env::get("{$prefix}.key_file", Env::get('gcs_key_file', null)),
                Env::get("{$prefix}.url", Env::get('gcs_url', ''))
            );
        }

        throw new \RuntimeException("Unsupported storage driver: {$driver} for disk '{$name}'");
    }

    /** 実行時に動的にアダプタを登録する（テスト用など） */
    public static function setDisk(string $name, StorageAdapter $adapter): void
    {
        self::$disks[$name] = $adapter;
    }

    /** ファイルが存在するか確認 */
    public static function exists(string $path): bool { return self::disk()->exists($path); }

    /** ファイル内容を取得 */
    public static function get(string $path): string|false { return self::disk()->get($path); }

    /** ファイルを保存（ディレクトリが無ければ自動生成） */
    public static function put(string $path, string $contents): bool { return self::disk()->put($path, $contents); }

    /** ファイルを削除 */
    public static function delete(string $path): bool { return self::disk()->delete($path); }

    /** ファイルサイズ（バイト）取得 */
    public static function size(string $path): int { return self::disk()->size($path); }

    /** 最終更新日時（UNIXタイムスタンプ）取得 */
    public static function lastModified(string $path): int { return self::disk()->lastModified($path); }

    /** ブラウザからアクセスするための公開URLを取得 */
    public static function url(string $path): string { return self::disk()->url($path); }
}


/**
 * ローカルファイルシステム用ストレージアダプター
 */
class LocalStorageAdapter implements StorageAdapter
{
    private string $root;
    private string $baseUrl;

    public function __construct(?string $root = null, string $baseUrl = '/storage')
    {
        $this->root = rtrim($root ?? Path::storage(), '/\\');
        $this->baseUrl = rtrim($baseUrl, '/');
        if (!is_dir($this->root)) {
            @mkdir($this->root, 0777, true);
        }
    }

    private function full(string $path): string
    {
        return $this->root . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    public function exists(string $path): bool
    {
        return file_exists($this->full($path));
    }

    public function get(string $path): string|false
    {
        return @file_get_contents($this->full($path));
    }

    public function put(string $path, string $contents): bool
    {
        $full = $this->full($path);
        $dir = dirname($full);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        return @file_put_contents($full, $contents) !== false;
    }

    public function delete(string $path): bool
    {
        $full = $this->full($path);
        if (is_file($full)) return @unlink($full);
        return false;
    }

    public function size(string $path): int
    {
        $full = $this->full($path);
        return is_file($full) ? filesize($full) : 0;
    }

    public function lastModified(string $path): int
    {
        $full = $this->full($path);
        return is_file($full) ? filemtime($full) : 0;
    }

    public function url(string $path): string
    {
        return url($this->baseUrl . '/' . ltrim($path, '/'));
    }
}

/**
 * AWS S3用ストレージアダプター
 * ※利用には composer require aws/aws-sdk-php が必要です
 */
class S3StorageAdapter implements StorageAdapter
{
    private $client;
    private string $bucket;
    private string $baseUrl;

    public function __construct(string $bucket, string $region = '', string $key = '', string $secret = '', string $baseUrl = '')
    {
        $this->bucket = $bucket;
        $this->baseUrl = rtrim($baseUrl, '/');
        $config = ['version' => 'latest'];
        if ($region) $config['region'] = $region;
        if ($key && $secret) $config['credentials'] = ['key' => $key, 'secret' => $secret];
        $class = '\\Aws\\S3\\S3Client';
        if (!class_exists($class)) {
            throw new \RuntimeException("AWS SDK not found. Please install it via: composer require aws/aws-sdk-php");
        }
        $this->client = new $class($config);
    }

    public function exists(string $path): bool
    {
        return $this->client->doesObjectExist($this->bucket, $path);
    }

    public function get(string $path): string|false
    {
        try {
            $res = $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $path]);
            return (string)$res['Body'];
        } catch (\Exception $e) {
            return false;
        }
    }

    public function put(string $path, string $contents): bool
    {
        try {
            $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $path, 'Body' => $contents]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function delete(string $path): bool
    {
        try {
            $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $path]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function size(string $path): int
    {
        try {
            $res = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $path]);
            return (int)$res['ContentLength'];
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function lastModified(string $path): int
    {
        try {
            $res = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $path]);
            return $res['LastModified']->getTimestamp();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function url(string $path): string
    {
        return $this->baseUrl ? $this->baseUrl . '/' . ltrim($path, '/') : $this->client->getObjectUrl($this->bucket, $path);
    }
}

/**
 * Google Cloud Storage (GCS) 用ストレージアダプター
 * ※利用には composer require google/cloud-storage が必要です
 */
class GcsStorageAdapter implements StorageAdapter
{
    private $bucket;
    private string $baseUrl;

    public function __construct(string $bucketName, ?string $keyFilePath = null, string $baseUrl = '')
    {
        $config = [];
        if ($keyFilePath) $config['keyFilePath'] = $keyFilePath;
        $class = '\\Google\\Cloud\\Storage\\StorageClient';
        if (!class_exists($class)) {
            throw new \RuntimeException("Google Cloud Storage SDK not found. Please install it via: composer require google/cloud-storage");
        }
        $storage = new $class($config);
        $this->bucket = $storage->bucket($bucketName);
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function exists(string $path): bool
    {
        return $this->bucket->object($path)->exists();
    }

    public function get(string $path): string|false
    {
        $obj = $this->bucket->object($path);
        return $obj->exists() ? $obj->downloadAsString() : false;
    }

    public function put(string $path, string $contents): bool
    {
        $this->bucket->upload($contents, ['name' => $path]);
        return true;
    }

    public function delete(string $path): bool
    {
        $obj = $this->bucket->object($path);
        if ($obj->exists()) {
            $obj->delete();
            return true;
        }
        return false;
    }

    public function size(string $path): int
    {
        $obj = $this->bucket->object($path);
        return $obj->exists() ? (int)$obj->info()['size'] : 0;
    }

    public function lastModified(string $path): int
    {
        $obj = $this->bucket->object($path);
        return $obj->exists() ? strtotime($obj->info()['updated']) : 0;
    }

    public function url(string $path): string
    {
        return $this->baseUrl ? $this->baseUrl . '/' . ltrim($path, '/') : "https://storage.googleapis.com/{$this->bucket->name()}/" . ltrim($path, '/');
    }
}
