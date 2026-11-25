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
 * Converts raw HTTP outcomes into conservative availability statuses.
 *
 * Principles:
 *  - 401/403/407 mean "restricted", never "broken".
 *  - 404/410 and DNS failures are only "unavailable" after repeated
 *    consecutive results (configurable), to avoid false alarms from
 *    transient errors or bot filtering.
 *  - Everything else that is not a success is "unknown".
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_classifier {
    /** @var string Not yet checked. */
    public const UNCHECKED = 'unchecked';
    /** @var string Reachable. */
    public const AVAILABLE = 'available';
    /** @var string Confirmed unavailable. */
    public const UNAVAILABLE = 'unavailable';
    /** @var string Authentication required or access restricted. */
    public const RESTRICTED = 'restricted';
    /** @var string Remote server rate limited the checker. */
    public const RATELIMITED = 'ratelimited';
    /** @var string Could not verify. */
    public const UNKNOWN = 'unknown';

    /**
     * All statuses in display order.
     *
     * @return string[]
     */
    public static function statuses(): array {
        return [self::UNCHECKED, self::AVAILABLE, self::UNAVAILABLE, self::RESTRICTED, self::RATELIMITED, self::UNKNOWN];
    }

    /**
     * Classify a fetch result.
     *
     * @param array $result Result from http_fetcher::fetch().
     * @param int $previousfailures Consecutive not-found results before this check.
     * @param int $confirmattempts Consecutive not-found results needed to confirm unavailability.
     * @return array{status: string, reason: string, failures: int}
     */
    public static function classify(array $result, int $previousfailures, int $confirmattempts): array {
        $confirmattempts = max(1, $confirmattempts);
        if (!empty($result['blocked'])) {
            return ['status' => self::UNKNOWN, 'reason' => 'blocked_' . $result['blocked'], 'failures' => 0];
        }
        $code = (int) $result['code'];
        $errno = (int) $result['errno'];

        if ($code === 0 || ($errno !== 0 && $errno !== 63)) {
            // Transport error before a usable HTTP status.
            if ($errno === 6) {
                $failures = $previousfailures + 1;
                if ($failures >= $confirmattempts) {
                    return ['status' => self::UNAVAILABLE, 'reason' => 'dns', 'failures' => $failures];
                }
                return ['status' => self::UNKNOWN, 'reason' => 'dnspending', 'failures' => $failures];
            }
            $reason = match (true) {
                $errno === 28 => 'timeout',
                in_array($errno, [35, 51, 58, 60, 77, 83, 90, 91], true) => 'ssl',
                $errno === 7 => 'connection',
                default => 'connection',
            };
            return ['status' => self::UNKNOWN, 'reason' => $reason, 'failures' => 0];
        }

        if ($code >= 200 && $code < 300) {
            return ['status' => self::AVAILABLE, 'reason' => !empty($result['redirects']) ? 'redirected' : 'ok',
                'failures' => 0];
        }
        if ($code === 304) {
            return ['status' => self::AVAILABLE, 'reason' => 'ok', 'failures' => 0];
        }
        if ($code >= 300 && $code < 400) {
            return ['status' => self::UNKNOWN, 'reason' => 'toomanyredirects', 'failures' => 0];
        }
        if ($code === 401 || $code === 407) {
            return ['status' => self::RESTRICTED, 'reason' => 'authrequired', 'failures' => 0];
        }
        if ($code === 403 || $code === 451) {
            return ['status' => self::RESTRICTED, 'reason' => 'forbidden', 'failures' => 0];
        }
        if ($code === 429) {
            return ['status' => self::RATELIMITED, 'reason' => 'ratelimited', 'failures' => 0];
        }
        if ($code === 404 || $code === 410) {
            $failures = $previousfailures + 1;
            if ($failures >= $confirmattempts) {
                return ['status' => self::UNAVAILABLE, 'reason' => $code === 410 ? 'gone' : 'notfound',
                    'failures' => $failures];
            }
            return ['status' => self::UNKNOWN, 'reason' => 'notfoundpending', 'failures' => $failures];
        }
        if ($code >= 500) {
            return ['status' => self::UNKNOWN, 'reason' => 'servererror', 'failures' => 0];
        }
        return ['status' => self::UNKNOWN, 'reason' => 'httpother', 'failures' => 0];
    }

    /**
     * Whether a HEAD result should be retried with GET (many servers mishandle HEAD).
     *
     * @param array $result
     * @return bool
     */
    public static function should_retry_with_get(array $result): bool {
        if (!empty($result['blocked'])) {
            return false;
        }
        $code = (int) $result['code'];
        return $code === 0 || in_array($code, [400, 403, 404, 405, 406, 409, 500, 501, 502, 503], true);
    }
}
