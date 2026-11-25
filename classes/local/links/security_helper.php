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
 * cURL security helper that applies url_guard on every request and redirect hop,
 * in addition to Moodle's own admin-configured blocked hosts/allowed ports.
 *
 * Moodle's curl class calls url_is_blocked() and then get_resolve_info() for
 * each request, and passes the result to CURLOPT_RESOLVE, so the connection is
 * pinned to the addresses that were validated.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class security_helper extends \core\files\curl_security_helper {
    /** @var url_guard Guard used for every URL. */
    private url_guard $guard;

    /** @var string[] Resolve entries for the last allowed URL. */
    private array $resolveinfo = [];

    /** @var string Reason code for the last blocked URL. */
    public string $lastreason = '';

    /**
     * Constructor.
     *
     * @param url_guard $guard
     */
    public function __construct(url_guard $guard) {
        $this->guard = $guard;
    }

    /**
     * Whether the URL is blocked.
     *
     * @param string $urlstring
     * @param mixed $notused Kept for signature compatibility with core.
     * @return bool
     */
    public function url_is_blocked($urlstring, $notused = null) {
        $this->resolveinfo = [];
        $result = $this->guard->check((string) $urlstring);
        if (!$result['ok']) {
            $this->lastreason = $result['reason'];
            return true;
        }
        // Respect the site's own cURL blocked hosts and allowed ports settings as well.
        if (parent::url_is_blocked($urlstring)) {
            $this->lastreason = 'siteblocked';
            return true;
        }
        $host = trim($result['host'], '[]');
        $v4 = array_values(array_filter($result['ips'], fn($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)));
        $pin = $v4 ?: array_map(fn($ip) => '[' . $ip . ']', $result['ips']);
        $this->resolveinfo = array_map(fn($ip) => $host . ':' . $result['port'] . ':' . $ip, $pin);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->resolveinfo = []; // IP literal: nothing to resolve.
        }
        $this->lastreason = '';
        return false;
    }

    /**
     * Resolve entries for CURLOPT_RESOLVE.
     *
     * @return string[]
     */
    public function get_resolve_info(): array {
        return $this->resolveinfo;
    }
}
