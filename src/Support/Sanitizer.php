<?php

namespace Perfocard\Flow\Support;

class Sanitizer
{
    /* ===== Config methods — can be overridden in subclasses ===== */

    /** Keys to mask regardless of location (case-insensitive) */
    public function keys(): array
    {
        return [];
    }

    /** Exact dot-paths (case-insensitive per segment) */
    public function paths(): array
    {
        return [];
    }

    /** Regex patterns that match key names */
    public function regex(): array
    {
        return [];
    }

    /** Which URL query parameters to mask */
    public function urlQuery(): array
    {
        return [];
    }

    /** Prefixes to preserve (like "Bearer "), mask the rest */
    public function preservePrefix(): array
    {
        return [
            'authorization' => '/^\s*\w+\s+/', // leave "Bearer " | "Basic " etc.
        ];
    }

    /** Mask character and its length */
    public function maskChar(): string
    {
        return '*';
    }

    public function maskLen(): int
    {
        return 8;
    }

    /* ===== Public API ===== */

    /** Masks data according to configuration methods */
    public function apply(array $data): array
    {
        $keys = array_map('mb_strtolower', $this->keys());
        $paths = array_map(fn ($p) => array_map('mb_strtolower', explode('.', $p)), $this->paths());
        $regexList = $this->regex();
        $urlQueryKeys = array_map('mb_strtolower', $this->urlQuery());
        $preserveRules = [];
        foreach ($this->preservePrefix() as $k => $rx) {
            $preserveRules[mb_strtolower($k)] = $rx;
        }
        $maskChar = $this->maskChar();
        $maskLen = $this->maskLen();

        // Mask URL query parameters
        if (isset($data['url']) && is_string($data['url']) && $urlQueryKeys) {
            $data['url'] = $this->maskUrlQuery($data['url'], $urlQueryKeys, $maskChar, $maskLen);
        }

        // Recursively mask the structure
        return $this->maskArrayRecursive($data, [
            'keys' => $keys,
            'paths' => $paths,
            'regex' => $regexList,
            'preserveRules' => $preserveRules,
            'maskChar' => $maskChar,
            'maskLen' => $maskLen,
            'currentPath' => [],
        ]);
    }

    /* ===== Internal mechanics ===== */

    private function maskArrayRecursive($value, array $ctx)
    {
        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $k => $v) {
            $lowerK = is_string($k) ? mb_strtolower($k) : $k;
            $path = array_merge($ctx['currentPath'], [is_string($lowerK) ? $lowerK : (string) $lowerK]);

            $shouldMask = false;

            // a) by key
            if (is_string($lowerK) && in_array($lowerK, $ctx['keys'], true)) {
                $shouldMask = true;
            }
            // b) by regex
            if (! $shouldMask && is_string($k)) {
                foreach ($ctx['regex'] as $rx) {
                    if (@preg_match($rx, $k)) {
                        $shouldMask = true;
                        break;
                    }
                }
            }
            // c) by dot-path
            if (! $shouldMask && $this->pathMatches($path, $ctx['paths'])) {
                $shouldMask = true;
            }

            if ($shouldMask) {
                if (is_string($v) && is_string($lowerK) && isset($ctx['preserveRules'][$lowerK])) {
                    $v = $this->maskWithPreserve($v, $ctx['preserveRules'][$lowerK], $ctx['maskChar'], $ctx['maskLen']);
                } else {
                    $v = $this->maskValue($v, $ctx['maskChar'], $ctx['maskLen']);
                }
                $out[$k] = $v;

                continue;
            }

            $out[$k] = (is_array($v) || is_object($v))
                ? $this->maskArrayRecursive($v, array_merge($ctx, ['currentPath' => $path]))
                : $v;
        }

        return $out;
    }

    private function pathMatches(array $currentPath, array $paths): bool
    {
        foreach ($paths as $p) {
            if (count($p) !== count($currentPath)) {
                continue;
            }
            $ok = true;
            foreach ($p as $i => $seg) {
                if ($seg !== $currentPath[$i]) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    private function maskValue($value, string $maskChar, int $len)
    {
        if (is_string($value)) {
            return str_repeat($maskChar, max(3, $len));
        }

        if (is_numeric($value)) {
            return (int) str_repeat('9', max(1, $len));
        }

        if (is_array($value) || is_object($value)) {
            return ['__masked__' => true];
        }

        return $value;
    }

    private function maskWithPreserve(string $value, string $prefixRegex, string $maskChar, int $len): string
    {
        if (preg_match($prefixRegex, $value, $m)) {
            return $m[0].str_repeat($maskChar, max(3, $len));
        }

        return str_repeat($maskChar, max(3, $len));
    }

    private function maskUrlQuery(string $url, array $queryKeys, string $maskChar, int $len): string
    {
        $parts = parse_url($url);
        if (! isset($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $q);
        $changed = false;

        foreach ($q as $k => $v) {
            if (in_array(mb_strtolower($k), $queryKeys, true)) {
                $q[$k] = str_repeat($maskChar, max(3, $len));
                $changed = true;
            }
        }
        if (! $changed) {
            return $url;
        }

        $parts['query'] = http_build_query($q, '', '&', PHP_QUERY_RFC3986);

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $user = $parts['user'] ?? null;
        $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        $authHost = ($user ? "$user$pass" : '').($host ? $host : '');
        $authority = $authHost ? '//'.$authHost : '';

        return ($scheme ? "$scheme:" : '').$authority.$port.$path.$query.$fragment;
    }
}
