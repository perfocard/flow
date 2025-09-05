<?php

namespace Perfocard\Flow\Support;

final class CurlFormatter
{
    /**
     * Build a multiline curl command.
     * $req = [
     *   'method'  => 'GET'|'POST'|...,
     *   'url'     => 'https://example.com',
     *   'headers' => ['Authorization' => 'Bearer ••••••••', ...] or ['Header: value', ...],
     *   'payload' => array|string|null, // for GET will go into query
     * ]
     *
     * @param  string  $maskChar  Mask character (so it is not encoded in the query)
     */
    public static function build(array $req, string $maskChar = '*'): string
    {
        $method = strtoupper($req['method'] ?? 'GET');
        $url = (string) ($req['url'] ?? '');
        $headers = $req['headers'] ?? [];
        $body = $req['payload'] ?? null;

        $normalizedHeaders = self::normalizeHeaders($headers);
        $contentType = self::guessContentType($normalizedHeaders);

        // For GET move payload into query and DO NOT add --data
        if ($method === 'GET' && $body !== null) {
            $url = self::appendQueryFromPayload($url, $body, $maskChar);
            $body = null;
        }

        $parts = [];
        $parts[] = 'curl';
        $parts[] = '-sS';
        $parts[] = '-X '.escapeshellarg($method);

        foreach ($normalizedHeaders as $name => $value) {
            $parts[] = '-H '.escapeshellarg($name.': '.$value);
        }

        // Body (not added for GET)
        $parts = array_merge($parts, self::bodyFlags($body, $contentType, $method));

        // URL at the end
        $parts[] = escapeshellarg($url);

        return self::joinMultiline($parts);
    }

    /** Support ['H: v', 'X: y'] or ['H'=>'v','X'=>'y'] or even the string 'H: v' */
    private static function normalizeHeaders($headers): array
    {
        $out = [];
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (is_int($k) && is_string($v) && str_contains($v, ':')) {
                    [$name, $value] = explode(':', $v, 2);
                    $out[trim($name)] = ltrim($value);
                } elseif (is_string($k)) {
                    $out[trim($k)] = is_array($v) ? implode(', ', $v) : (string) $v;
                }
            }
        } elseif (is_string($headers) && str_contains($headers, ':')) {
            [$name, $value] = explode(':', $headers, 2);
            $out[trim($name)] = ltrim($value);
        }

        return $out;
    }

    private static function guessContentType(array $headers): ?string
    {
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'Content-Type') === 0) {
                return strtolower(trim($v));
            }
        }

        return null;
    }

    /** Appends query to URL with hybrid encoding (do not encode masks) */
    private static function appendQueryFromPayload(string $url, $payload, string $maskChar): string
    {
        if (is_array($payload) || is_object($payload)) {
            $arr = is_object($payload) ? json_decode(json_encode($payload), true) : $payload;

            $pairs = [];
            foreach ($arr as $k => $v) {
                $kEnc = rawurlencode((string) $k);
                $vStr = is_scalar($v) ? (string) $v : json_encode($v);

                if (self::isPureMask($vStr, $maskChar)) {
                    // do not encode the mask — keeps logs readable
                    $pairs[] = "{$kEnc}={$vStr}";
                } else {
                    $pairs[] = "{$kEnc}=".rawurlencode($vStr);
                }
            }

            $qs = implode('&', $pairs);
            if ($qs !== '') {
                $url .= (str_contains($url, '?') ? '&' : '?').$qs;
            }

            return $url;
        }

        if (is_string($payload) && $payload !== '') {
            // Ready query string — leave unchanged
            $qs = ltrim($payload, '?&');
            $url .= (str_contains($url, '?') ? '&' : '?').$qs;

            return $url;
        }

        return $url;
    }

    private static function isPureMask(string $v, string $maskChar): bool
    {
        $mask = preg_quote($maskChar, '/');

        return $v !== '' && preg_match('/^'.$mask.'+$/u', $v) === 1;
    }

    /** Build flags for body (except GET) */
    private static function bodyFlags($body, ?string $contentType, string $method): array
    {
        if ($method === 'GET' || $body === null) {
            return [];
        }

        // String — as is
        if (is_string($body)) {
            return ['--data '.escapeshellarg($body)];
        }

        // Array/object — behavior depends on Content-Type
        if (is_array($body) || is_object($body)) {
            $arr = is_object($body) ? json_decode(json_encode($body), true) : $body;

            if ($contentType && str_contains($contentType, 'application/json')) {
                return ['--data '.escapeshellarg(json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))];
            }

            if ($contentType && str_contains($contentType, 'multipart/form-data')) {
                return self::multipartFlags($arr);
            }

            // Default — x-www-form-urlencoded (mask already replaced earlier by sanitizer)
            $pairs = [];
            foreach ($arr as $k => $v) {
                $pairs[] = $k.'='.(is_scalar($v) ? (string) $v : json_encode($v));
            }

            return ['--data '.escapeshellarg(implode('&', $pairs))];
        }

        // numbers/booleans → string
        return ['--data '.escapeshellarg((string) $body)];
    }

    /** multipart: -F key=value; supports arrays as key[] */
    private static function multipartFlags(array $arr): array
    {
        $flags = [];
        $flatten = function ($key, $value) use (&$flatten, &$flags) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $flatten($key.'[]', $v);
                }
            } else {
                $payload = is_scalar($value) ? (string) $value : json_encode($value);
                $flags[] = '-F '.escapeshellarg($key.'='.$payload);
            }
        };
        foreach ($arr as $k => $v) {
            $flatten((string) $k, $v);
        }

        return $flags;
    }

    /** Pretty multiline joins with backslashes */
    private static function joinMultiline(array $parts): string
    {
        return implode(" \\\n  ", $parts);
    }
}
