<?php

namespace Azcaptcha\Support;

/** Proxy and image parameter normalisation shared by the client. */
final class Params
{
    public const DEFAULT_TIMEOUTS = [
        'base64' => 120, 'grid' => 120,
        'userrecaptcha' => 300, 'recaptcha_v3' => 300,
        'hcaptcha' => 120, 'hcaptcha_enterprise' => 120,
        'turnstile' => 180,
        'funcaptcha' => 300, 'geetest' => 300, 'geetest_v4' => 300,
        'mtcaptcha' => 300, 'friendlycaptcha' => 300, 'altcha' => 300,
        'cf_clearance' => 300, 'aws_waf' => 300, 'filecrypt' => 300,
    ];

    private const SCHEMES = [
        'http' => 'HTTP', 'https' => 'HTTP',
        'socks4' => 'SOCKS4', 'socks5' => 'SOCKS5',
    ];

    /**
     * Accepts scheme://user:pass@host:port, host:port:user:pass and
     * user:pass@host:port. The scheme is never sent in `proxy`.
     *
     * @return array{proxy: string, proxytype: string}
     */
    public static function normalizeProxy(string $proxy): array
    {
        $raw = trim($proxy);
        $type = 'HTTP';
        if (strpos($raw, '://') !== false) {
            [$scheme, $raw] = explode('://', $raw, 2);
            $type = self::SCHEMES[strtolower($scheme)] ?? 'HTTP';
        }
        $parts = explode(':', $raw);
        if (count($parts) === 4 && ctype_digit($parts[1])) {
            [$host, $port, $user, $pass] = $parts;
            $raw = "$user:$pass@$host:$port";
        } else {
            $hostPort = strpos($raw, '@') !== false ? substr($raw, strrpos($raw, '@') + 1) : $raw;
            $port = strpos($hostPort, ':') !== false ? substr($hostPort, strrpos($hostPort, ':') + 1) : '';
            if (!ctype_digit($port)) {
                throw new \InvalidArgumentException('proxy must include a numeric port');
            }
        }

        return ['proxy' => $raw, 'proxytype' => $type];
    }

    /** Resolves a file path, data: URI, raw bytes or base64 string to bare base64. */
    public static function imageBody(string $image): string
    {
        if (strncmp($image, 'data:', 5) === 0 && strpos($image, ',') !== false) {
            return substr($image, strpos($image, ',') + 1);
        }
        if (strlen($image) < 4096 && strpos($image, "\0") === false && is_file($image)) {
            return base64_encode((string) file_get_contents($image));
        }
        if (preg_match('/^[A-Za-z0-9+\/=\s]+$/', $image) !== 1) {
            return base64_encode($image);
        }

        return preg_replace('/\s+/', '', $image);
    }

    /** Drops null values so the API sees only provided fields. */
    public static function clean(array $params): array
    {
        return array_filter($params, static fn ($value): bool => $value !== null);
    }
}
