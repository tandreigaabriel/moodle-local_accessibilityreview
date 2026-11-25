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
 * Minimal outbound HTTP client for link checks.
 *
 * - Uses Moodle's curl class (honours site proxy settings) with a security
 *   helper that validates and DNS-pins every hop.
 * - Follows redirects manually so each Location is validated before use.
 * - Never sends cookies, Moodle session data or credentials; the only headers
 *   are a User-Agent, Accept and (for API calls) an explicitly supplied header.
 * - Limits response size and time.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class http_fetcher {
    /** @var int Maximum response body kept in memory. */
    private const MAX_BODY = 1048576;

    /**
     * Constructor.
     *
     * @param url_guard $guard SSRF guard.
     * @param int $timeout Total timeout per request in seconds.
     * @param int $maxredirects Maximum redirects to follow.
     * @param string $useragent User-Agent header value.
     */
    public function __construct(
        /** @var url_guard SSRF guard. */
        private url_guard $guard,
        /** @var int Timeout seconds. */
        private int $timeout = 10,
        /** @var int Max redirects. */
        private int $maxredirects = 5,
        /** @var string User agent. */
        private string $useragent = 'MoodleAccessibilityLinkChecker/1.0',
    ) {
    }

    /**
     * Build from plugin settings.
     *
     * @return self
     */
    public static function from_config(): self {
        $config = get_config('local_accessibilityreview');
        return new self(
            new url_guard(),
            max(2, min(60, (int) ($config->linktimeout ?? 10))),
            max(0, min(10, (int) ($config->maxredirects ?? 5))),
            !empty($config->useragent) ? clean_param($config->useragent, PARAM_TEXT) : 'MoodleAccessibilityLinkChecker/1.0'
        );
    }

    /**
     * Fetch a URL.
     *
     * @param string $url Absolute http(s) URL.
     * @param string $method HEAD or GET.
     * @param string[] $extraheaders Additional request headers (for example an API Authorization header).
     * @return array{code: int, errno: int, blocked: string, finalurl: string, headers: array, body: string, redirects: int}
     */
    public function fetch(string $url, string $method = 'HEAD', array $extraheaders = []): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $current = $url;
        $redirects = 0;
        while (true) {
            $helper = new security_helper($this->guard);
            if ($helper->url_is_blocked($current)) {
                return $this->result(0, 0, $helper->lastreason ?: 'blocked', $current, [], '', $redirects);
            }
            // No 'cookie' setting: nothing is stored or sent. ignoresecurity stays false.
            $curl = new \curl(['securityhelper' => $helper]);
            $curl->setHeader(array_merge(['User-Agent: ' . $this->useragent, 'Accept: */*'], $extraheaders));
            $options = [
                'CURLOPT_FOLLOWLOCATION' => 0,
                'CURLOPT_MAXREDIRS' => 0,
                'CURLOPT_TIMEOUT' => $this->timeout,
                'CURLOPT_CONNECTTIMEOUT' => min(5, $this->timeout),
                'CURLOPT_USERAGENT' => $this->useragent,
                'CURLOPT_MAXFILESIZE' => self::MAX_BODY,
                'CURLOPT_SSL_VERIFYPEER' => true,
            ];
            if ($method === 'HEAD') {
                $body = $curl->head($current, $options);
            } else {
                $options['CURLOPT_RANGE'] = '0-65535';
                $body = $curl->get($current, [], $options);
            }
            $info = $curl->get_info();
            $errno = (int) $curl->get_errno();
            $code = (int) ($info['http_code'] ?? 0);
            $responseheaders = [];
            foreach ((array) $curl->getResponse() as $key => $value) {
                $responseheaders[strtolower((string) $key)] = is_array($value) ? end($value) : (string) $value;
            }
            $body = is_string($body) ? substr($body, 0, self::MAX_BODY) : '';

            if ($code >= 300 && $code < 400 && $code !== 304) {
                $location = (string) ($info['redirect_url'] ?? '');
                if ($location === '' && isset($responseheaders['location'])) {
                    $location = self::absolute_url($current, trim($responseheaders['location']));
                }
                if ($location === '' || $redirects >= $this->maxredirects) {
                    return $this->result(
                        $code,
                        $errno,
                        $location === '' ? '' : 'toomanyredirects',
                        $current,
                        $responseheaders,
                        '',
                        $redirects
                    );
                }
                $redirects++;
                $current = $location;
                continue;
            }
            return $this->result($code, $errno, '', $current, $responseheaders, $body, $redirects);
        }
    }

    /**
     * Build a result array.
     *
     * @param int $code
     * @param int $errno
     * @param string $blocked
     * @param string $finalurl
     * @param array $headers
     * @param string $body
     * @param int $redirects
     * @return array
     */
    private function result(
        int $code,
        int $errno,
        string $blocked,
        string $finalurl,
        array $headers,
        string $body,
        int $redirects
    ): array {
        return ['code' => $code, 'errno' => $errno, 'blocked' => $blocked, 'finalurl' => $finalurl,
            'headers' => $headers, 'body' => $body, 'redirects' => $redirects];
    }

    /**
     * Resolve a possibly relative Location header against the current URL.
     *
     * @param string $base
     * @param string $location
     * @return string
     */
    public static function absolute_url(string $base, string $location): string {
        if ($location === '') {
            return '';
        }
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($location, '//')) {
            return $parts['scheme'] . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $path = $parts['path'] ?? '/';
        $dir = substr($path, 0, (int) strrpos($path, '/') + 1);
        return $origin . $dir . $location;
    }
}
