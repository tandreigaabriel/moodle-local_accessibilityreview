<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_accessibilityreview\local\links;

/**
 * SSRF protection for outbound link checks.
 *
 * A URL is allowed only when:
 *  - the scheme is http or https and the URL contains no user credentials;
 *  - the port is in the allowed list (80/443 by default);
 *  - the host is a normal DNS name or a standard IP literal (no decimal/octal/hex
 *    IPv4 shorthands that cURL would interpret without DNS);
 *  - the host is not a known internal name (localhost, *.internal, metadata hosts);
 *  - EVERY address the host resolves to is a public unicast address.
 *
 * The resolved addresses are returned so the caller can pin cURL to them
 * (CURLOPT_RESOLVE), which closes the DNS-rebinding window between the check
 * and the connection. Every redirect hop must be checked again.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class url_guard {
    /** @var string[] Blocked CIDR ranges in addition to PHP's private/reserved filters. */
    private const BLOCKED_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '255.255.255.255/32',
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/32',
        '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    /** @var string[] Host names that must never be fetched. */
    private const BLOCKED_HOSTS = [
        'localhost', 'metadata', 'metadata.google.internal', 'metadata.goog', 'instance-data',
        'instance-data.ec2.internal', 'metadata.azure.com',
    ];

    /** @var string[] Blocked host suffixes. */
    private const BLOCKED_SUFFIXES = ['.localhost', '.local', '.internal', '.localdomain', '.home.arpa', '.lan', '.intranet'];

    /** @var callable Resolver returning a list of IP strings for a host name. */
    private $resolver;

    /** @var int[] Allowed destination ports. */
    private array $allowedports;

    /**
     * Constructor.
     *
     * @param callable|null $resolver fn(string $host): string[]; defaults to DNS A + AAAA lookup.
     * @param int[] $allowedports Allowed ports.
     */
    public function __construct(?callable $resolver = null, array $allowedports = [80, 443]) {
        $this->resolver = $resolver ?? [self::class, 'dns_resolve'];
        $this->allowedports = array_map('intval', $allowedports);
    }

    /**
     * Default resolver: IPv4 and IPv6 records.
     *
     * @param string $host
     * @return string[]
     */
    public static function dns_resolve(string $host): array {
        $ips = [];
        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }
        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }
        return array_values(array_unique($ips));
    }

    /**
     * Validate a URL.
     *
     * @param string $url
     * @return array{ok: bool, reason: string, host: string, port: int, ips: string[]}
     */
    public function check(string $url): array {
        $fail = fn(string $reason, string $host = '') => ['ok' => false, 'reason' => $reason, 'host' => $host,
            'port' => 0, 'ips' => []];

        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return $fail('invalidurl');
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $fail('invalidurl');
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return $fail('scheme');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return $fail('credentials');
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, $this->allowedports, true)) {
            return $fail('port', $host);
        }

        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP)) {
            if (!self::is_public_ip($literal)) {
                return $fail('privateaddress', $host);
            }
            return ['ok' => true, 'reason' => '', 'host' => $host, 'port' => $port, 'ips' => [$literal]];
        }

        // Reject numeric/hex host shorthands such as 2130706433, 0x7f.1 or 127.1 that cURL may
        // treat as addresses without consulting DNS (bypassing pinning).
        if (preg_match('/^[0-9.]+$/', $host) || preg_match('/^0x[0-9a-f.x]+$/i', $host)) {
            return $fail('invalidhost', $host);
        }
        if (
            !preg_match(
                '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i',
                $host
            ) || !str_contains($host, '.')
        ) {
            return $fail('invalidhost', $host);
        }
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            return $fail('privateaddress', $host);
        }
        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return $fail('privateaddress', $host);
            }
        }

        $ips = call_user_func($this->resolver, $host);
        if (empty($ips)) {
            return $fail('dns', $host);
        }
        foreach ($ips as $ip) {
            if (!self::is_public_ip($ip)) {
                // Any non-public answer blocks the request (defends against mixed rebinding answers).
                return $fail('privateaddress', $host);
            }
        }
        return ['ok' => true, 'reason' => '', 'host' => $host, 'port' => $port, 'ips' => array_values($ips)];
    }

    /**
     * Whether an IP address is a public unicast address.
     *
     * @param string $ip
     * @return bool
     */
    public static function is_public_ip(string $ip): bool {
        $ip = trim($ip, '[]');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        foreach (self::BLOCKED_RANGES as $cidr) {
            if (self::in_cidr($ip, $cidr)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether an address is inside a CIDR range (IPv4 or IPv6).
     *
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    public static function in_cidr(string $ip, string $cidr): bool {
        [$subnet, $bits] = explode('/', $cidr);
        $ipbin = @inet_pton($ip);
        $subnetbin = @inet_pton($subnet);
        if ($ipbin === false || $subnetbin === false || strlen($ipbin) !== strlen($subnetbin)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;
        if ($bytes > 0 && substr($ipbin, 0, $bytes) !== substr($subnetbin, 0, $bytes)) {
            return false;
        }
        if ($remainder === 0) {
            return true;
        }
        $mask = (0xff << (8 - $remainder)) & 0xff;
        return (ord($ipbin[$bytes]) & $mask) === (ord($subnetbin[$bytes]) & $mask);
    }
}
