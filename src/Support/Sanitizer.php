<?php

namespace Perfocard\Flow\Support;

class Sanitizer
{
    /* ===== Config methods — can be overridden in subclasses ===== */

    /**
     * Keys to mask regardless of location (case-insensitive).
     *
     * @return string[]
     */
    public function keys(): array
    {
        return [];
    }

    /**
     * Exact dot-paths to mask. Comparison is case-insensitive per segment.
     *
     * Example: 'payload.user.password' or 'headers.authorization'
     *
     * @return string[]
     */
    public function paths(): array
    {
        return [];
    }

    /**
     * Regex patterns (PCRE) that match key names which should be masked.
     * Patterns should include delimiters and modifiers, e.g. '/secret/i'.
     *
     * @return string[]
     */
    public function regex(): array
    {
        return [];
    }

    /**
     * URL query parameter names that must be masked when present in URLs.
     * Comparison is case-insensitive.
     *
     * @return string[]
     */
    public function urlQuery(): array
    {
        return [];
    }

    /**
     * Prefixes to preserve (like "Bearer "), mask the rest.
     * Return an associative array of header-name => prefix-regex.
     *
     * @return array<string,string>
     */
    public function preservePrefix(): array
    {
        return [
            'authorization' => '/^\s*\w+\s+/', // leave "Bearer " | "Basic " etc.
        ];
    }

    /**
     * Mask character.
     */
    public function maskChar(): string
    {
        return '*';
    }

    /**
     * Default mask length.
     */
    public function maskLen(): int
    {
        return 8;
    }

    /**
     * Optional pattern describing which URL path segments should be masked.
     * Use '{mask}' in pattern to indicate segments that must be masked.
     * Example: '/users/{mask}/orders/{mask}'. Return null to disable.
     */
    public function urlPathPattern(): ?string
    {
        return null;
    }

    /* ===== Public API ===== */

    /**
     * Masks data according to configuration methods.
     */
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

        if (isset($data['url']) && is_string($data['url'])) {
            if ($this->urlPathPattern()) {
                // Mask URL path segments according to the configured pattern
                $data['url'] = $this->maskUrlPathByPattern(
                    url: $data['url'],
                    pattern: $this->urlPathPattern(),
                    maskChar: $maskChar,
                    maskLen: $maskLen,
                );
            }

            if ($urlQueryKeys) {
                // Mask URL query parameters
                $data['url'] = $this->maskUrlQuery($data['url'], $urlQueryKeys, $maskChar, $maskLen);
            }
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

    /**
     * Recursively walk and mask array/object structures.
     *
     * @param  mixed  $value
     * @return mixed
     */
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

    /**
     * Check whether the current path matches any configured dot-path.
     *
     * @param  string[]  $currentPath
     * @param  array<int, string[]>  $paths
     */
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

    /**
     * Mask a value according to its type.
     *
     * @param  mixed  $value
     * @return mixed
     */
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

    /**
     * Mask a value but preserve a leading prefix matched by the provided regex.
     */
    private function maskWithPreserve(string $value, string $prefixRegex, string $maskChar, int $len): string
    {
        if (preg_match($prefixRegex, $value, $m)) {
            return $m[0].str_repeat($maskChar, max(3, $len));
        }

        return str_repeat($maskChar, max(3, $len));
    }

    /**
     * Mask URL query parameters specified in $queryKeys.
     *
     * @param  string[]  $queryKeys
     */
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

    /**
     * Mask URL path segments according to a template pattern. Segments marked
     * as '{mask}' in the pattern will be replaced with the configured mask
     * token. Only non-mask segments are URL-encoded — mask tokens are left intact.
     */
    private function maskUrlPathByPattern(string $url, string $pattern, string $maskChar, int $maskLen): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';

        $pSegs = $path === '' ? [] : array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
        $tplSegs = array_values(array_filter(explode('/', trim($pattern, '/')), fn ($s) => $s !== ''));

        $maskToken = str_repeat($maskChar, max(3, $maskLen));

        $outSegs = $pSegs;
        $n = min(count($pSegs), count($tplSegs));
        for ($i = 0; $i < $n; $i++) {
            if ($tplSegs[$i] === '{mask}') {
                $outSegs[$i] = $maskToken;
            }
        }

        // IMPORTANT: encode only non-masked segments
        $encodedSegs = array_map(function ($seg) use ($maskToken) {
            // normalize existing encoding, but keep mask token intact
            if ($seg === $maskToken) {
                return $maskToken;
            }

            return rawurlencode(rawurldecode($seg));
        }, $outSegs);

        $parts['path'] = '/'.implode('/', $encodedSegs);

        // rebuild URL
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $user = $parts['user'] ?? null;
        $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
        $authHost = ($user ? "$user$pass" : '').($host ? $host : '');
        $authority = $authHost ? '//'.$authHost : '';

        return ($scheme ? "$scheme:" : '').$authority.$port.$parts['path'].$query.$fragment;
    }
}
