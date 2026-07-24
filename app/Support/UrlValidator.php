<?php

namespace LinkGuard\Support;

use InvalidArgumentException;

final class UrlValidator
{
    private const INTERNAL_HOSTS = ['localhost', 'localhost.localdomain', 'ip6-localhost'];
    private const BLOCKED_CIDRS = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '100::/64',
        '2001:db8::/32',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

    public function validate(string $input, bool $resolveDns = true): array
    {
        $url = trim($input);
        if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Enter a valid, complete URL.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only HTTP and HTTPS links can be analyzed.');
        }
        if ($host === '') {
            throw new InvalidArgumentException('The link must include a valid host name.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Links containing embedded credentials are not accepted.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) === false && $this->isNonCanonicalNumericHost($host)) {
            throw new InvalidArgumentException('Non-standard numeric host formats are blocked.');
        }
        if ($this->isInternalHost($host) || $this->isBlockedIp($host)) {
            throw new InvalidArgumentException('Local, private, reserved, and special-purpose addresses are blocked.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) === false && !$this->isValidHostname($host)) {
            throw new InvalidArgumentException('The host name is malformed. Check the copied link for misplaced slashes, spaces, or hyphens.');
        }

        if ($resolveDns && filter_var($host, FILTER_VALIDATE_IP) === false) {
            foreach ($this->resolve($host) as $address) {
                if ($this->isBlockedIp($address)) {
                    throw new InvalidArgumentException('The host resolves to a blocked network address.');
                }
            }
        }

        $serializedHost = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $host . ']' : $host;
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        $normalized = $scheme . '://' . $serializedHost . $port . $path . $query . $fragment;

        return [
            'url' => $normalized,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $parts['port'] ?? null,
            'path' => $path,
            'query' => $parts['query'] ?? '',
            'fragment' => $parts['fragment'] ?? '',
        ];
    }

    public function isBlockedIp(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if (filter_var(
            $value,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false) {
            return true;
        }
        foreach (self::BLOCKED_CIDRS as $cidr) {
            if ($this->isInCidr($value, $cidr)) {
                return true;
            }
        }
        return false;
    }

    private function isInternalHost(string $host): bool
    {
        return in_array($host, self::INTERNAL_HOSTS, true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal');
    }

    private function isValidHostname(string $host): bool
    {
        if (strlen($host) > 253 || !str_contains($host, '.')) {
            return false;
        }
        foreach (explode('.', $host) as $label) {
            if (
                $label === ''
                || strlen($label) > 63
                || preg_match('/^(?!-)[a-z0-9-]+(?<!-)$/i', $label) !== 1
            ) {
                return false;
            }
        }
        return true;
    }

    private function isNonCanonicalNumericHost(string $host): bool
    {
        $parts = explode('.', $host);
        if (count($parts) > 4) {
            return false;
        }
        foreach ($parts as $part) {
            if (preg_match('/^(?:0x[0-9a-f]+|0[0-7]+|[0-9]+)$/i', $part) !== 1) {
                return false;
            }
        }
        return true;
    }

    private function isInCidr(string $address, string $cidr): bool
    {
        [$network, $prefixText] = explode('/', $cidr, 2);
        $addressBytes = inet_pton($address);
        $networkBytes = inet_pton($network);
        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }
        $prefix = (int) $prefixText;
        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;
        if ($wholeBytes > 0 && substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }

    private function resolve(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];
        if (function_exists('dns_get_record')) {
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }
        return array_values(array_unique($addresses));
    }
}
