<?php

namespace Fzr;

/**
 * トレーサー（実行履歴スタック・可視化）
 */
class Tracer
{
    private static array $events = [];
    private static array $timers = [];
    private static bool $enabled = false;

    public static function init(bool $enabled): void
    {
        self::$enabled = $enabled;
        if (self::$enabled) {
            self::start('total', 'Application Total');
        }
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * イベントを記録
     */
    public static function add(string $category, string $message, ?float $time = null, ?array $data = null): void
    {
        if (!self::$enabled) return;

        self::$events[] = [
            'category' => $category,
            'message'  => $message,
            'time'     => $time ?? Context::elapsed(),
            'data'     => $data,
            'memory'   => memory_get_usage(),
        ];
    }

    /**
     * タイマー開始
     */
    public static function start(string $key, ?string $label = null): void
    {
        if (!self::$enabled) return;
        self::$timers[$key] = [
            'start' => microtime(true),
            'label' => $label ?? $key
        ];
    }

    /**
     * タイマー終了と記録
     */
    public static function stop(string $key, ?array $data = null): void
    {
        if (!self::$enabled || !isset(self::$timers[$key])) return;

        $timer = self::$timers[$key];
        $elapsed = microtime(true) - $timer['start'];
        self::add('timer', $timer['label'], $elapsed, $data);
        unset(self::$timers[$key]);
    }

    /**
     * DBクエリ記録
     */
    public static function recordQuery(string $sql, array $params, float $elapsed, string $connKey): void
    {
        self::add('db', $sql, $elapsed, [
            'connection' => $connKey,
            'params'     => $params
        ]);
    }

    /**
     * キャッシュ記録
     */
    public static function recordCache(string $op, string $key, bool $hit, ?float $elapsed = null): void
    {
        self::add('cache', "[$op] $key", $elapsed, [
            'op'  => $op,
            'key' => $key,
            'hit' => $hit
        ]);
    }

    public static function getAll(): array
    {
        return self::$events;
    }
}
