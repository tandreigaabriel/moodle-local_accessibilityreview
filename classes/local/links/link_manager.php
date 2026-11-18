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

use DOMElement;
use local_accessibilityreview\local\checks\html_checker;
use local_accessibilityreview\local\content\content_item;
use local_accessibilityreview\local\resource_manager;

/**
 * Extracts external links from content, records where they are used, and
 * runs the background availability checks.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_manager {
    /** @var string Link table. */
    public const TABLE = 'local_accessibilityreview_link';

    /** @var string Link use table. */
    public const USE_TABLE = 'local_accessibilityreview_linkuse';

    /**
     * Extract absolute external URLs from HTML.
     *
     * Internal Moodle links and @@PLUGINFILE@@ references are excluded: they
     * require a Moodle session and are not checked.
     *
     * @param string|null $html
     * @return string[]
     */
    public static function extract_urls(?string $html): array {
        if ($html === null) {
            return [];
        }
        $doc = html_checker::load_dom($html);
        if (!$doc) {
            return [];
        }
        $urls = [];
        $map = ['a' => 'href', 'iframe' => 'src', 'source' => 'src', 'video' => 'src', 'audio' => 'src',
            'embed' => 'src', 'object' => 'data'];
        foreach ($map as $tag => $attr) {
            /** @var DOMElement $el */
            foreach ($doc->getElementsByTagName($tag) as $el) {
                if ($el->hasAttribute($attr)) {
                    $url = self::normalise($el->getAttribute($attr));
                    if ($url !== null) {
                        $urls[$url] = true;
                    }
                }
            }
        }
        return array_keys($urls);
    }

    /**
     * Normalise a URL for checking, or null when it should not be checked.
     *
     * @param string $url
     * @return string|null
     */
    public static function normalise(string $url): ?string {
        global $CFG;
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }
        if (!preg_match('~^https?://~i', $url) || strlen($url) > 2048) {
            return null;
        }
        if (str_contains($url, '@@PLUGINFILE@@')) {
            return null;
        }
        $hashpos = strpos($url, '#');
        if ($hashpos !== false) {
            $url = substr($url, 0, $hashpos);
        }
        $wwwhost = strtolower((string) parse_url($CFG->wwwroot, PHP_URL_HOST));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === $wwwhost) {
            return null; // Internal Moodle link.
        }
        return $url;
    }

    /**
     * Link type for a URL.
     *
     * @param string $url
     * @return string vimeo, youtube or web.
     */
    public static function link_type(string $url): string {
        if (vimeo::parse($url)) {
            return 'vimeo';
        }
        if (self::youtube_id($url)) {
            return 'youtube';
        }
        return 'web';
    }

    /**
     * YouTube video id from a URL.
     *
     * @param string $url
     * @return string|null
     */
    public static function youtube_id(string $url): ?string {
        if (
            preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|' .
                'youtube-nocookie\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)
        ) {
            return $m[1];
        }
        return null;
    }

    /**
     * Get or create the cache row for a URL.
     *
     * @param string $url
     * @return \stdClass
     */
    public static function get_or_create(string $url): \stdClass {
        global $DB;
        $hash = sha1($url);
        $record = $DB->get_record(self::TABLE, ['urlhash' => $hash]);
        if ($record) {
            return $record;
        }
        $record = (object) [
            'urlhash' => $hash,
            'url' => $url,
            'host' => \core_text::substr(strtolower((string) parse_url($url, PHP_URL_HOST)), 0, 255),
            'linktype' => self::link_type($url),
            'status' => link_classifier::UNCHECKED,
            'reason' => '',
            'timenextcheck' => 0,
            'timecreated' => time(),
        ];
        try {
            $record->id = $DB->insert_record(self::TABLE, $record);
        } catch (\dml_write_exception $e) {
            $record = $DB->get_record(self::TABLE, ['urlhash' => $hash], '*', MUST_EXIST);
        }
        return $record;
    }

    /**
     * Record the URLs used by a content item, and inventory any videos and external PDFs.
     *
     * @param int $scanid
     * @param content_item $item
     * @param string[] $urls Normalised URLs.
     * @return void
     */
    public static function record_uses(int $scanid, content_item $item, array $urls): void {
        global $DB;
        $DB->delete_records(self::USE_TABLE, ['courseid' => $item->courseid, 'provider' => $item->provider,
            'itemid' => $item->itemid, 'field' => $item->field]);
        $urls = array_values(array_unique($urls));
        if (!$urls) {
            return;
        }
        // Load all known links for this item in one query; only new URLs need their own insert.
        $known = [];
        foreach ($DB->get_records_list(self::TABLE, 'urlhash', array_map('sha1', $urls)) as $row) {
            $known[$row->urlhash] = $row;
        }
        $uses = [];
        foreach ($urls as $url) {
            $link = $known[sha1($url)] ?? self::get_or_create($url);
            $uses[] = (object) [
                'linkid' => $link->id,
                'courseid' => $item->courseid,
                'cmid' => $item->cmid,
                'provider' => $item->provider,
                'itemid' => $item->itemid,
                'field' => $item->field,
                'itemlabel' => \core_text::substr($item->label, 0, 255),
                'lastscanid' => $scanid,
            ];
            self::inventory_media($scanid, $item, $link);
        }
        $DB->insert_records(self::USE_TABLE, $uses);
    }

    /**
     * Add videos and externally hosted PDFs to the inventory.
     *
     * @param int $scanid
     * @param content_item $item
     * @param \stdClass $link
     * @return void
     */
    private static function inventory_media(int $scanid, content_item $item, \stdClass $link): void {
        $base = [
            'courseid' => $item->courseid,
            'cmid' => $item->cmid,
            'location' => $item->label,
            'provider' => $item->provider,
            'itemid' => $item->itemid,
            'url' => $link->url,
            'linkid' => (int) $link->id,
        ];
        if ($link->linktype === 'vimeo') {
            $parsed = vimeo::parse($link->url);
            resource_manager::upsert($scanid, $base + [
                'identity' => sha1('vimeo|' . $item->courseid . '|' . $parsed['id']),
                'restype' => 'video',
                'source' => 'vimeo',
                'name' => get_string('vimeovideo', 'local_accessibilityreview', $parsed['id']),
            ]);
        } else if ($link->linktype === 'youtube') {
            $id = self::youtube_id($link->url);
            resource_manager::upsert($scanid, $base + [
                'identity' => sha1('youtube|' . $item->courseid . '|' . $id),
                'restype' => 'video',
                'source' => 'youtube',
                'name' => get_string('youtubevideo', 'local_accessibilityreview', $id),
            ]);
        } else if (preg_match('~\.pdf$~i', (string) parse_url($link->url, PHP_URL_PATH))) {
            resource_manager::upsert($scanid, $base + [
                'identity' => sha1('extpdf|' . $item->courseid . '|' . $link->urlhash),
                'restype' => 'pdf',
                'source' => 'external',
                'name' => \core_text::substr(rawurldecode(basename((string) parse_url($link->url, PHP_URL_PATH))), 0, 255),
            ]);
        }
    }

    /**
     * Remove link uses from sources not seen in a completed scan.
     *
     * @param int $courseid
     * @param int $scanid
     * @param string[] $providers Providers completed in this scan.
     * @return void
     */
    public static function finalise(int $courseid, int $scanid, array $providers): void {
        global $DB;
        if (!$providers) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($providers, SQL_PARAMS_NAMED, 'prov');
        $params += ['courseid' => $courseid, 'scanid' => $scanid];
        $DB->delete_records_select(
            self::USE_TABLE,
            "courseid = :courseid AND lastscanid <> :scanid AND provider $insql",
            $params
        );
    }

    /**
     * Check links that are due.
     *
     * @param int $maxlinks Maximum links to check.
     * @param int $timelimit Seconds.
     * @param http_fetcher|null $fetcher Fetcher (injected in tests).
     * @return array<string, int> Count per status.
     */
    public static function check_due(int $maxlinks, int $timelimit, ?http_fetcher $fetcher = null): array {
        global $DB;
        $fetcher = $fetcher ?? http_fetcher::from_config();
        $config = get_config('local_accessibilityreview');
        $recheck = max(1, (int) ($config->linkrecheckdays ?? 7)) * DAYSECS;
        $confirm = max(1, (int) ($config->linkconfirmattempts ?? 2));
        $hostdelay = max(0, (int) ($config->linkhostdelay ?? 1000)); // Milliseconds.
        $start = time();
        $counts = [];

        $sql = "SELECT l.*
                  FROM {" . self::TABLE . "} l
                 WHERE l.timenextcheck <= :now
                   AND EXISTS (SELECT 1 FROM {" . self::USE_TABLE . "} u WHERE u.linkid = l.id)
              ORDER BY l.timenextcheck ASC, l.id ASC";
        $links = $DB->get_records_sql($sql, ['now' => time()], 0, $maxlinks);
        $lasthit = [];
        $skiphosts = [];
        foreach ($links as $link) {
            if (time() - $start >= $timelimit) {
                break;
            }
            $hostkey = in_array($link->linktype, ['vimeo', 'youtube'], true) ? $link->linktype : $link->host;
            if (isset($skiphosts[$hostkey])) {
                continue;
            }
            if (isset($lasthit[$hostkey]) && $hostdelay > 0) {
                $elapsedms = (int) ((microtime(true) - $lasthit[$hostkey]) * 1000);
                if ($elapsedms < $hostdelay) {
                    usleep(($hostdelay - $elapsedms) * 1000);
                }
            }
            $lasthit[$hostkey] = microtime(true);
            $result = self::check_one($link, $fetcher, $confirm);
            $lasthit[$hostkey] = microtime(true);

            $next = time() + $recheck;
            if ($result['status'] === link_classifier::UNKNOWN) {
                $next = time() + DAYSECS;
            } else if ($result['status'] === link_classifier::RATELIMITED) {
                $retry = (int) ($result['retryafter'] ?? 0);
                $next = time() + max(6 * HOURSECS, min(7 * DAYSECS, $retry));
                $skiphosts[$hostkey] = true;
            }
            $DB->update_record(self::TABLE, (object) [
                'id' => $link->id,
                'status' => $result['status'],
                'reason' => \core_text::substr($result['reason'], 0, 50),
                'httpcode' => (int) ($result['code'] ?? 0),
                'failures' => (int) $result['failures'],
                'metadata' => isset($result['metadata']) ? json_encode($result['metadata']) : $link->metadata,
                'timechecked' => time(),
                'timenextcheck' => $next,
            ]);
            $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * Check one link.
     *
     * @param \stdClass $link
     * @param http_fetcher $fetcher
     * @param int $confirm
     * @return array{status: string, reason: string, failures: int, code: int}
     */
    public static function check_one(\stdClass $link, http_fetcher $fetcher, int $confirm): array {
        $previous = (int) $link->failures;
        if ($link->linktype === 'vimeo' && ($parsed = vimeo::parse($link->url))) {
            return vimeo::check($fetcher, $parsed['id'], $parsed['hash'], $previous, $confirm);
        }
        if ($link->linktype === 'youtube' && ($id = self::youtube_id($link->url))) {
            $result = $fetcher->fetch('https://www.youtube.com/oembed?format=json&url=' .
                rawurlencode('https://www.youtube.com/watch?v=' . $id), 'GET');
            $code = (int) $result['code'];
            if ($code === 401 || $code === 403) {
                return ['status' => link_classifier::RESTRICTED, 'reason' => 'videorestricted', 'failures' => 0,
                    'code' => $code];
            }
            $classified = link_classifier::classify($result, $previous, $confirm);
            return $classified + ['code' => $code];
        }
        $result = $fetcher->fetch($link->url, 'HEAD');
        if (link_classifier::should_retry_with_get($result)) {
            $result = $fetcher->fetch($link->url, 'GET');
        }
        $classified = link_classifier::classify($result, $previous, $confirm);
        $classified['code'] = (int) $result['code'];
        if (isset($result['headers']['retry-after']) && is_numeric($result['headers']['retry-after'])) {
            $classified['retryafter'] = (int) $result['headers']['retry-after'];
        }
        return $classified;
    }
}
