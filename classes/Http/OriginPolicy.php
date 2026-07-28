<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

use Psr\Http\Message\ServerRequestInterface;

final class OriginPolicy
{
    public function __construct()
    {
    }

    /** @param list<string> $allowedOrigins */
    public function evaluate(ServerRequestInterface $request, array $allowedOrigins): ?string
    {
        $values = $request->getHeader('Origin');
        if (count($values) !== 1 || $values[0] === '') return 'ORIGIN_MISSING';
        if (str_contains($values[0], ',')) return 'ORIGIN_INVALID';
        $origin = $this->canonicalOrigin($values[0]);
        if ($origin === null) return 'ORIGIN_INVALID';
        $uri = $request->getUri();
        $direct = $this->canonicalParts($uri->getScheme(), $uri->getHost(), $uri->getPort());
        if ($direct === null) return 'ORIGIN_INVALID';
        if ($origin === $direct) return null;
        foreach ($allowedOrigins as $allowed) {
            if (!is_string($allowed) || $this->canonicalOrigin($allowed) !== $allowed) {
                return 'ORIGIN_INVALID';
            }
            if ($origin === $allowed) return null;
        }
        return 'ORIGIN_FORBIDDEN';
    }

    private function canonicalOrigin(string $origin): ?string
    {
        if ($origin === 'null' || trim($origin) !== $origin || preg_match('/[^\x20-\x7e]/', $origin)) return null;
        $parts = parse_url($origin);
        if (!is_array($parts) || isset($parts['user'], $parts['pass'], $parts['path'], $parts['query'], $parts['fragment'])) return null;
        if (!isset($parts['scheme'], $parts['host'])) return null;
        return $this->canonicalParts((string) $parts['scheme'], (string) $parts['host'], $parts['port'] ?? null);
    }

    private function canonicalParts(string $scheme, string $host, ?int $port): ?string
    {
        $scheme = strtolower($scheme);
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || str_ends_with($host, '.')) return null;
        $host = strtolower($host);
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }
        if (preg_match('/[^\x20-\x7e]/', $host)) return null;
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($host);
            if ($packed === false) return null;
            $host = '[' . strtolower(inet_ntop($packed)) . ']';
        } elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($host !== filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return null;
        } elseif (preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/D', $host) !== 1 || str_contains($host, '..')) {
            return null;
        }
        if ($port !== null && ($port < 1 || $port > 65535)) return null;
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) $port = null;
        return $scheme . '://' . $host . ($port === null ? '' : ':' . $port);
    }
}
