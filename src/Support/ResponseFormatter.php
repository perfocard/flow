<?php

namespace Perfocard\Flow\Support;

use Illuminate\Http\Client\Response;

final class ResponseFormatter
{
    public static function build(Response $resp, ?Sanitizer $sanitizer = null, array $opts = []): string
    {
        $opts = array_merge([
            'pretty_json' => true,
            'add_content_length' => true,
            'truncate_body_to' => 0,
            'mask_char' => '*',
            'recompute_content_length' => true,
        ], $opts);

        // Status / reason
        $status = $resp->status();
        $reason = method_exists($resp, 'reason') ? (string) $resp->reason() : '';

        // Headers (Laravel provides ['Name' => ['v1','v2']])
        $headers = $resp->headers();

        // Content-Type
        $contentType = null;
        foreach ($headers as $k => $vals) {
            if (strcasecmp($k, 'Content-Type') === 0 && ! empty($vals)) {
                $contentType = (string) $vals[0];
                break;
            }
        }

        // Body: if JSON — try to get decoded array (for masking and prettify)
        $isJson = is_string($contentType) && stripos($contentType, 'application/json') !== false;
        if ($isJson) {
            $decoded = $resp->json();
            $body = (is_array($decoded) || is_object($decoded)) ? $decoded : (string) $resp->body();
        } else {
            $body = (string) $resp->body();
        }

        // Normalize and mask headers
        $headersNorm = self::normalizeHeaders($headers);
        $headersSafe = self::maskHeaders($headersNorm, $sanitizer, $opts['mask_char']);

        // Prepare body (mask JSON, prettify, truncate)
        $bodyRaw = self::prepareBody($body, $headersSafe, $sanitizer, $opts);

        if ($opts['recompute_content_length']) {
            // завжди ставимо фактичну довжину поточного bodyRaw
            $headersSafe['Content-Length'] = [(string) strlen($bodyRaw)];
        } else {
            // або прибираємо, якщо тіло переформатоване
            unset($headersSafe['Content-Length']);
        }

        // Status line + headers + \r\n + body
        $protocol = 'HTTP/1.1'; // Laravel Http Client does not return protocol version
        $reason = $reason !== '' ? $reason : self::reasonPhrase($status);

        $lines = [];
        $lines[] = "{$protocol} {$status} {$reason}";
        foreach ($headersSafe as $name => $values) {
            foreach ($values as $v) {
                $lines[] = "{$name}: {$v}";
            }
        }
        $lines[] = ''; // separator

        $head = implode("\r\n", $lines);

        return $bodyRaw === '' ? $head."\r\n" : $head."\r\n".$bodyRaw;
    }

    /* ===================== helpers ===================== */

    private static function normalizeHeaders($headers): array
    {
        // expect Laravel format: ['Name' => ['v1','v2']]
        $out = [];
        foreach ((array) $headers as $k => $v) {
            $name = self::normalizeHeaderName((string) $k);
            if (is_array($v)) {
                foreach ($v as $vv) {
                    $out[$name][] = (string) $vv;
                }
            } else {
                $out[$name][] = (string) $v;
            }
        }

        return $out;
    }

    private static function normalizeHeaderName(string $name): string
    {
        $name = trim($name);

        return implode('-', array_map(fn ($p) => $p === '' ? '' : mb_convert_case($p, MB_CASE_TITLE, 'UTF-8'), explode('-', $name)));
    }

    private static function hasHeader(array $headers, string $needle): bool
    {
        $needle = strtolower($needle);
        foreach ($headers as $k => $_) {
            if (strtolower($k) === $needle) {
                return true;
            }
        }

        return false;
    }

    private static function maskHeaders(array $headers, ?Sanitizer $sanitizer, string $maskChar): array
    {
        foreach ($headers as $name => &$values) {
            $lname = strtolower($name);
            foreach ($values as &$v) {
                // Authorization/Proxy-Authorization — preserve scheme (Bearer/Basic), mask the rest
                if (in_array($lname, ['authorization', 'proxy-authorization'], true)) {
                    if (preg_match('/^\s*\w+\s+/u', $v, $m)) {
                        $v = $m[0].str_repeat($maskChar, 8);
                    } else {
                        $v = str_repeat($maskChar, 8);
                    }

                    continue;
                }
                // Keys/tokens
                if (in_array($lname, ['x-api-key', 'x-api-token', 'api-key', 'x-auth-token'], true)) {
                    $v = str_repeat($maskChar, 8);

                    continue;
                }
                // Cookie/Set-Cookie — mask the value but keep attributes
                if (in_array($lname, ['cookie', 'set-cookie'], true)) {
                    $parts = array_map('trim', explode(';', $v));
                    foreach ($parts as &$p) {
                        if (str_contains($p, '=')) {
                            [$ck, $cv] = explode('=', $p, 2);
                            if (! preg_match('/^(path|httponly|secure|samesite|domain|expires|max-age)$/i', trim($ck))) {
                                $p = $ck.'='.str_repeat($maskChar, 8);
                            }
                        }
                    }
                    $v = implode('; ', $parts);

                    continue;
                }
                // If a Sanitizer is present — let it try
                if ($sanitizer) {
                    $masked = $sanitizer->apply([strtolower($name) => $v]);
                    $v = $masked[strtolower($name)] ?? $v;
                } elseif (strlen($v) > 64 && preg_match('/^[A-Za-z0-9\-\._~\+\/]+=*$/', $v)) {
                    // conservative default masking for long tokens
                    $v = substr($v, 0, 6).str_repeat($maskChar, 8);
                }
            }
            unset($v);
        }
        unset($values);

        return $headers;
    }

    private static function prepareBody($body, array $headers, ?Sanitizer $sanitizer, array $opts): string
    {
        if ($body === null) {
            return '';
        }

        // Array/object → JSON with optional masking and prettify
        if (is_array($body) || is_object($body)) {
            $arr = is_object($body) ? json_decode(json_encode($body), true) : $body;
            if ($sanitizer) {
                $masked = $sanitizer->apply(['payload' => $arr]);
                $arr = $masked['payload'] ?? $arr;
            }
            $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($opts['pretty_json'] ? JSON_PRETTY_PRINT : 0);
            $out = json_encode($arr, $flags) ?: '';

            return self::truncateIfNeeded($out, $opts['truncate_body_to']);
        }

        // String: if it's JSON — try to partially mask
        $contentType = self::firstHeaderValue($headers, 'Content-Type') ?? '';
        $isJson = stripos($contentType, 'application/json') !== false;

        $s = (string) $body;

        if ($isJson) {
            $decoded = json_decode($s, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if ($sanitizer) {
                    $masked = $sanitizer->apply(['payload' => $decoded]);
                    $decoded = $masked['payload'] ?? $decoded;
                }
                $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($opts['pretty_json'] ? JSON_PRETTY_PRINT : 0);
                $s = json_encode($decoded, $flags) ?: $s;
            }
        }

        return self::truncateIfNeeded($s, $opts['truncate_body_to']);
    }

    private static function firstHeaderValue(array $headers, string $name): ?string
    {
        foreach ($headers as $k => $values) {
            if (strcasecmp($k, $name) === 0 && ! empty($values)) {
                return (string) $values[0];
            }
        }

        return null;
    }

    private static function truncateIfNeeded(string $s, int $limit): string
    {
        if ($limit > 0 && strlen($s) > $limit) {
            return substr($s, 0, $limit)."\n…[truncated]";
        }

        return $s;
    }

    private static function reasonPhrase(int $status): string
    {
        return [
            100 => 'Continue', 101 => 'Switching Protocols', 102 => 'Processing',
            200 => 'OK', 201 => 'Created', 202 => 'Accepted', 204 => 'No Content',
            206 => 'Partial Content', 301 => 'Moved Permanently', 302 => 'Found',
            304 => 'Not Modified', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
            400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
            409 => 'Conflict', 422 => 'Unprocessable Entity', 429 => 'Too Many Requests',
            500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable',
        ][$status] ?? '';
    }
}
