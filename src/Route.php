<?php
namespace Fzr;

/**
 * ルーティングヘルパー
 */
class Route
{
    public static function get(string $pattern, string $handler): void
    {
        Engine::addRoute('GET', $pattern, $handler);
    }

    public static function post(string $pattern, string $handler): void
    {
        Engine::addRoute('POST', $pattern, $handler);
    }

    public static function put(string $pattern, string $handler): void
    {
        Engine::addRoute('PUT', $pattern, $handler);
    }

    public static function delete(string $pattern, string $handler): void
    {
        Engine::addRoute('DELETE', $pattern, $handler);
    }

    public static function any(string $pattern, string $handler): void
    {
        Engine::addRoute('ANY', $pattern, $handler);
    }

    public static function match(array $methods, string $pattern, string $handler): void
    {
        foreach ($methods as $method) {
            Engine::addRoute(strtoupper($method), $pattern, $handler);
        }
    }
}
