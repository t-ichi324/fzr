<?php
namespace Fzr;

/**
 * HTTP Client — robust cURL wrapper for API integrations and AI provider usage.
 *
 * Use to send HTTP requests to external services (e.g., OpenAI, Gemini, REST APIs).
 * Typical uses: Calling LLM APIs, fetching remote data, pushing webhooks.
 *
 * - Supports both static calls (`HttpClient::get()`) and fluent chaining (`HttpClient::withToken()->post()`).
 * - Automatically handles JSON serialization and provides dot-notation for parsing results.
 * - Deeply integrated with `Logger` and `Tracer` for observability.
 *
 * @method static HttpResponse get(string $url, array|string|null $query = null)
 * @method static HttpResponse post(string $url, mixed $data = null)
 * @method static HttpResponse put(string $url, mixed $data = null)
 * @method static HttpResponse patch(string $url, mixed $data = null)
 * @method static HttpResponse delete(string $url, mixed $data = null)
 * @method static HttpClient withToken(string $token, string $type = 'Bearer')
 * @method static HttpClient withHeaders(array $headers)
 * @method static HttpClient timeout(int $seconds)
 * @method static HttpClient withoutVerifying()
 * 
 * @method HttpResponse get(string $url, array|string|null $query = null)
 * @method HttpResponse post(string $url, mixed $data = null)
 * @method HttpResponse put(string $url, mixed $data = null)
 * @method HttpResponse patch(string $url, mixed $data = null)
 * @method HttpResponse delete(string $url, mixed $data = null)
 * @method self withToken(string $token, string $type = 'Bearer')
 * @method self withHeaders(array $headers)
 * @method self timeout(int $seconds)
 * @method self withoutVerifying()
 */
class HttpClient
{
    protected array $options = [
        'headers' => [],
        'timeout' => 30,
        'verify' => true,
    ];

    /** ── Magic Methods for Facade & Chaining ─────────────────────────── */

    public static function __callStatic(string $method, array $args)
    {
        return (new self())->$method(...$args);
    }

    public function __call(string $method, array $args)
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$args);
        }
        throw new \BadMethodCallException("Method HttpClient::{$method} does not exist.");
    }

    /** ── Core Implementation (Protected) ──────────────────────────────── */

    protected function withToken(string $token, string $type = 'Bearer'): self
    {
        $this->options['headers']['Authorization'] = trim("$type $token");
        return $this;
    }

    protected function withHeaders(array $headers): self
    {
        foreach ($headers as $k => $v) {
            $this->options['headers'][$k] = $v;
        }
        return $this;
    }

    protected function timeout(int $seconds): self
    {
        $this->options['timeout'] = $seconds;
        return $this;
    }

    protected function withoutVerifying(): self
    {
        $this->options['verify'] = false;
        return $this;
    }

    protected function get(string $url, array|string|null $query = null): HttpResponse
    {
        return $this->send('GET', $url, ['query' => $query]);
    }

    protected function post(string $url, mixed $data = null): HttpResponse
    {
        return $this->send('POST', $url, ['json' => $data]);
    }

    protected function put(string $url, mixed $data = null): HttpResponse
    {
        return $this->send('PUT', $url, ['json' => $data]);
    }

    protected function patch(string $url, mixed $data = null): HttpResponse
    {
        return $this->send('PATCH', $url, ['json' => $data]);
    }

    protected function delete(string $url, mixed $data = null): HttpResponse
    {
        return $this->send('DELETE', $url, ['json' => $data]);
    }

    /**
     * Executes the cURL request.
     */
    public function send(string $method, string $url, array $requestOptions = []): HttpResponse
    {
        $ch = curl_init();
        $method = strtoupper($method);

        if (!empty($requestOptions['query'])) {
            $queryStr = is_array($requestOptions['query']) ? http_build_query($requestOptions['query']) : $requestOptions['query'];
            $url .= (str_contains($url, '?') ? '&' : '?') . $queryStr;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->options['timeout']);

        if (!$this->options['verify']) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $headers = $this->options['headers'];

        if (isset($requestOptions['json']) && $requestOptions['json'] !== null) {
            $payload = json_encode($requestOptions['json'], JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $headers['Content-Type'] = 'application/json';
        } elseif (isset($requestOptions['form_params'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestOptions['form_params']));
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        } elseif (isset($requestOptions['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestOptions['body']);
        }

        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "$k: $v";
        }
        if (!empty($curlHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }

        $start = microtime(true);
        $response = curl_exec($ch);
        $elapsed = microtime(true) - $start;

        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        curl_close($ch);

        if ($error) {
            Logger::error("HTTP {$method} {$url} failed: {$error}");
            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        $responseHeader = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        $parsedHeaders = [];
        foreach (explode("\r\n", $responseHeader) as $i => $line) {
            if ($i === 0 || empty(trim($line))) continue;
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $parsedHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        $logMsg = sprintf("%s %s [%d] %.2fms", $method, $url, $statusCode, $elapsed * 1000);
        Logger::writeDetail('http', $logMsg);

        if (class_exists(__NAMESPACE__ . '\\Tracer', false) && Tracer::isEnabled()) {
            Tracer::add('http', $logMsg, $elapsed, [
                'method' => $method,
                'url' => $url,
                'status' => $statusCode,
                'response' => mb_substr($responseBody, 0, 500) . (mb_strlen($responseBody) > 500 ? '...' : '')
            ]);
        }

        return new HttpResponse($responseBody, $statusCode, $parsedHeaders);
    }
}

/**
 * HTTP Response Wrapper
 */
class HttpResponse
{
    protected string $body;
    protected int $statusCode;
    protected array $headers;

    public function __construct(string $body, int $statusCode, array $headers)
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function body(): string { return $this->body; }
    public function status(): int { return $this->statusCode; }
    public function ok(): bool { return $this->statusCode >= 200 && $this->statusCode < 300; }
    public function failed(): bool { return $this->statusCode >= 400; }
    public function header(string $key): ?string { return $this->headers[strtolower($key)] ?? null; }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        $decoded = json_decode($this->body, true);
        if ($decoded === null) return $default;
        if ($key === null) return $decoded;

        $keys = explode('.', $key);
        $value = $decoded;
        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        return $value;
    }
}
