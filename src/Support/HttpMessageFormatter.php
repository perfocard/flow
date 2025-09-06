<?php

namespace Perfocard\Flow\Support;

final class HttpMessageFormatter
{
    /**
     * $req = [
     *   'method'  => 'GET'|'POST'|...,
     *   'url'     => 'https://host/path?qs' or '/path?qs' (both forms are accepted),
     *   'headers' => ['Header: value', ...] or ['Header'=>'value', ...] or ['Header'=>['v1','v2']],
     *   'payload' => array|string|null,   // arrays will be serialized according to Content-Type
     *   'http_version' => '1.1',          // optional
     * ]
     */
    public static function buildRequest(array $req, array $opts = []): string
    {
        $opts = array_merge([
            'pretty_json' => true,
            'truncate_body_to' => 0,
            'recompute_content_length' => false, // usually don't touch for requests
        ], $opts);

        $method = strtoupper($req['method'] ?? 'GET');
        $url = (string) ($req['url'] ?? '/');
        $headers = self::normalizeHeaders($req['headers'] ?? []);
        $payload = $req['payload'] ?? null;
        $version = (string) ($req['http_version'] ?? '1.1');

        // request-target: if a full URL is provided — use only the path+query
        $requestTarget = self::toRequestTarget($url);

        // Content-Type for serializing arrays
        $contentType = self::guessContentType($headers);

        // Body
        $body = self::serializeBody($payload, $contentType, $opts['pretty_json']);
        $body = self::truncateIfNeeded($body, $opts['truncate_body_to']);

        // Content-Length (optional)
        if ($opts['recompute_content_length']) {
            if ($body !== '') {
                $headers['Content-Length'] = (string) strlen($body);
            } else {
                unset($headers['Content-Length']);
            }
        }

        // Start-line + headers + CRLF + body
        $lines = [];
        $lines[] = sprintf('HTTP/%s %s %s', $version, $method, $requestTarget); // format “HTTP/x METHOD path”; to use classic order, switch to "%s %s HTTP/%s"
        // if you prefer the classic format “METHOD /path HTTP/1.1”, use:
        // $lines[] = sprintf('%s %s HTTP/%s', $method, $requestTarget, $version);

        foreach (self::iterateHeaderLines($headers) as $hline) {
            $lines[] = $hline;
        }
        $lines[] = '';

        $head = implode("\r\n", $lines);

        return $body === '' ? $head."\r\n" : $head."\r\n".$body;
    }

    /**
     * $res = [
     *   'status'  => 200,
     *   'reason'  => 'OK' // optional
     *   'headers' => ['Header: value', ...] or ['Header'=>'value', ...] or ['Header'=>['v1','v2']],
     *   'payload' => array|string|null,
     *   'http_version' => '1.1', // optional
     * ]
     */
    public static function buildResponse(array $res, array $opts = []): string
    {
        $opts = array_merge([
            'pretty_json' => true,
            'truncate_body_to' => 0,
            'recompute_content_length' => true, // convenient to recompute for responses
        ], $opts);

        $status = (int) ($res['status'] ?? 200);
        $reason = (string) ($res['reason'] ?? self::reasonPhrase($status));
        $headers = self::normalizeHeaders($res['headers'] ?? []);
        $payload = $res['payload'] ?? null;
        $version = (string) ($res['http_version'] ?? '1.1');

        $contentType = self::guessContentType($headers);

        $body = self::serializeBody($payload, $contentType, $opts['pretty_json']);
        $body = self::truncateIfNeeded($body, $opts['truncate_body_to']);

        if ($opts['recompute_content_length']) {
            $headers['Content-Length'] = (string) strlen($body);
        } else {
            unset($headers['Content-Length']);
        }

        $lines = [];
        $lines[] = sprintf('HTTP/%s %d %s', $version, $status, $reason);
        foreach (self::iterateHeaderLines($headers) as $hline) {
            $lines[] = $hline;
        }
        $lines[] = '';

        $head = implode("\r\n", $lines);

        return $body === '' ? $head."\r\n" : $head."\r\n".$body;
    }

    /* ===================== helpers ===================== */

    /** Accepts ['H: v', 'X: y'] or ['H'=>'v','X'=>'y'] or ['H'=>['v1','v2']] */
    private static function normalizeHeaders($headers): array
    {
        $out = [];
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (is_int($k) && is_string($v) && str_contains($v, ':')) {
                    [$name, $value] = explode(':', $v, 2);
                    $out[self::canon(trim($name))][] = ltrim($value);
                } elseif (is_string($k)) {
                    $name = self::canon(trim($k));
                    if (is_array($v)) {
                        foreach ($v as $vv) {
                            $out[$name][] = (string) $vv;
                        }
                    } else {
                        $out[$name][] = (string) $v;
                    }
                }
            }
        } elseif (is_string($headers) && str_contains($headers, ':')) {
            [$name, $value] = explode(':', $headers, 2);
            $out[self::canon(trim($name))][] = ltrim($value);
        }

        return $out;
    }

    private static function iterateHeaderLines(array $headers): \Generator
    {
        foreach ($headers as $name => $vals) {
            foreach ((array) $vals as $v) {
                yield "{$name}: {$v}";
            }
        }
    }

    private static function canon(string $h): string
    {
        return implode('-', array_map(
            fn ($p) => $p === '' ? '' : mb_convert_case($p, MB_CASE_TITLE, 'UTF-8'),
            explode('-', $h)
        ));
    }

    private static function guessContentType(array $headers): ?string
    {
        foreach ($headers as $k => $vals) {
            if (strcasecmp($k, 'Content-Type') === 0) {
                $v = is_array($vals) ? ($vals[0] ?? '') : $vals;

                return strtolower(trim((string) $v));
            }
        }

        return null;
    }

    private static function serializeBody($payload, ?string $contentType, bool $prettyJson): string
    {
        if ($payload === null || $payload === '') {
            return '';
        }

        // string — as is
        if (is_string($payload)) {
            return $payload;
        }

        // array/object → depending on Content-Type
        if (is_array($payload) || is_object($payload)) {
            $arr = is_object($payload) ? json_decode(json_encode($payload), true) : $payload;

            if ($contentType && str_contains($contentType, 'application/json')) {
                return json_encode(
                    $arr,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($prettyJson ? JSON_PRETTY_PRINT : 0)
                ) ?: '';
            }

            if ($contentType && str_contains($contentType, 'application/x-www-form-urlencoded')) {
                // in raw HTTP they usually put urlencoded here
                return http_build_query($arr);
            }

            // multipart/form-data — raw body contains boundary + binary parts.
            // For logs it's better to show a readable JSON description of fields (without binary content).
            if ($contentType && str_contains($contentType, 'multipart/form-data')) {
                return json_encode(
                    $arr,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($prettyJson ? JSON_PRETTY_PRINT : 0)
                ) ?: '';
            }

            // default — sensible JSON representation
            return json_encode(
                $arr,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($prettyJson ? JSON_PRETTY_PRINT : 0)
            ) ?: '';
        }

        // numbers/booleans — as string
        return (string) $payload;
    }

    private static function truncateIfNeeded(string $s, int $limit): string
    {
        return ($limit > 0 && strlen($s) > $limit)
            ? substr($s, 0, $limit)."\n…[truncated]"
            : $s;
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

    private static function toRequestTarget(string $url): string
    {
        // if already /path?qs — return as-is
        if (str_starts_with($url, '/')) {
            return $url;
        }
        // otherwise parse the full URL
        $p = parse_url($url);
        $pt = $p['path'] ?? '/';
        if (! empty($p['query'])) {
            $pt .= '?'.$p['query'];
        }

        return $pt;
    }
}
