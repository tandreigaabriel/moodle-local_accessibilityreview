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
 * Vimeo URL parsing, privacy-hash redaction and availability/caption checks.
 *
 * Availability uses Vimeo's public oEmbed endpoint, sending the Moodle site
 * as Referer so domain-restricted embeds that allow this site are recognised.
 * Caption evidence is only available when an administrator configures an API
 * access token; otherwise captions are always reported as unknown.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vimeo {
    /** @var string oEmbed endpoint. */
    private const OEMBED = 'https://vimeo.com/api/oembed.json?url=';

    /** @var string API base. */
    private const API = 'https://api.vimeo.com/videos/';

    /**
     * Parse a Vimeo URL.
     *
     * @param string $url
     * @return array{id: string, hash: string}|null
     */
    public static function parse(string $url): ?array {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }
        $hash = '';
        if (preg_match('~^https?://player\.vimeo\.com/video/(\d+)(?:/([0-9a-f]{6,}))?~i', $url, $m)) {
            $id = $m[1];
            $hash = $m[2] ?? '';
        } else if (
            preg_match('~^https?://(?:www\.)?vimeo\.com/(?:channels/[\w-]+/|groups/[\w-]+/videos/|album/\d+/video/|' .
                'showcase/\d+/video/|manage/videos/)?(\d+)(?:/([0-9a-f]{6,}))?(?:[/?#]|$)~i', $url, $m)
        ) {
            $id = $m[1];
            $hash = $m[2] ?? '';
        } else {
            return null;
        }
        if ($hash === '') {
            $query = (string) parse_url($url, PHP_URL_QUERY);
            parse_str($query, $params);
            if (!empty($params['h']) && is_string($params['h']) && preg_match('/^[0-9a-f]{6,}$/i', $params['h'])) {
                $hash = $params['h'];
            }
        }
        return ['id' => $id, 'hash' => strtolower($hash)];
    }

    /**
     * Canonical watch URL, preserving the unlisted privacy hash when present.
     *
     * @param string $id
     * @param string $hash
     * @return string
     */
    public static function canonical_url(string $id, string $hash = ''): string {
        return 'https://vimeo.com/' . $id . ($hash !== '' ? '/' . $hash : '');
    }

    /**
     * Embedded player address for a video, as used in iframes.
     *
     * @param string $id
     * @param string $hash
     * @return string
     */
    public static function player_url(string $id, string $hash = ''): string {
        return 'https://player.vimeo.com/video/' . $id . ($hash !== '' ? '?h=' . $hash : '');
    }

    /**
     * Redact Vimeo privacy hashes from any text (URLs, markup, log lines).
     *
     * @param string $text
     * @return string
     */
    public static function redact_text(string $text): string {
        if (stripos($text, 'vimeo') === false) {
            return $text;
        }
        $text = preg_replace('~(vimeo\.com/(?:video/)?\d+/)[0-9a-f]{6,}~i', '$1[hash]', $text);
        return preg_replace_callback(
            '~(player\.vimeo\.com/video/\d+[^"\'\s<>]*?[?&](?:amp;)?h=)[0-9a-f]{6,}~i',
            fn($m) => $m[1] . '[hash]',
            $text
        );
    }

    /**
     * Configured API token, or empty string.
     *
     * @return string
     */
    public static function api_token(): string {
        return trim((string) get_config('local_accessibilityreview', 'vimeotoken'));
    }

    /**
     * Check a Vimeo video.
     *
     * @param http_fetcher $fetcher
     * @param string $id
     * @param string $hash
     * @param int $previousfailures
     * @param int $confirmattempts
     * @return array{status: string, reason: string, failures: int, code: int, metadata: array}
     */
    public static function check(
        http_fetcher $fetcher,
        string $id,
        string $hash,
        int $previousfailures,
        int $confirmattempts
    ): array {
        global $CFG;
        $metadata = ['captions' => 'unknown', 'captionsource' => '', 'title' => ''];
        $oembed = $fetcher->fetch(
            self::OEMBED . rawurlencode(self::canonical_url($id, $hash)),
            'GET',
            ['Referer: ' . $CFG->wwwroot . '/']
        );
        $code = (int) $oembed['code'];

        $status = link_classifier::UNKNOWN;
        $reason = 'connection';
        $failures = 0;
        if (!empty($oembed['blocked'])) {
            $reason = 'blocked_' . $oembed['blocked'];
        } else if ($code === 200) {
            $status = link_classifier::AVAILABLE;
            $reason = 'ok';
            $json = json_decode($oembed['body'], true);
            if (is_array($json) && isset($json['title'])) {
                $metadata['title'] = \core_text::substr(clean_param((string) $json['title'], PARAM_TEXT), 0, 255);
            }
        } else if ($code === 403 || $code === 401) {
            // Private or embed/domain restricted: not broken.
            $status = link_classifier::RESTRICTED;
            $reason = 'vimeorestricted';
        } else if ($code === 429) {
            $status = link_classifier::RATELIMITED;
            $reason = 'ratelimited';
        } else if ($code === 404) {
            // Vimeo returns 404 for deleted videos but also for some private videos.
            $status = link_classifier::UNKNOWN;
            $reason = 'vimeonotfound';
        } else if ($code >= 500) {
            $reason = 'servererror';
        } else if ($code === 0 && (int) $oembed['errno'] === 28) {
            $reason = 'timeout';
        }

        // The oEmbed endpoint does not reliably describe videos whose embedding is limited to specific websites
        // (it can answer 403, 404 or even 500). Load the player page the way a browser on this
        // Moodle site would: it plays here (available) or the player refuses (domain-restricted).
        if (empty($oembed['blocked']) && $status !== link_classifier::AVAILABLE && $code !== 429) {
            $player = $fetcher->fetch(self::player_url($id, $hash), 'GET', ['Referer: ' . $CFG->wwwroot . '/']);
            $playercode = (int) $player['code'];
            if (empty($player['blocked']) && $playercode === 200) {
                $status = link_classifier::AVAILABLE;
                $reason = 'vimeoplayerok';
            } else if (empty($player['blocked']) && ($playercode === 401 || $playercode === 403)) {
                $status = link_classifier::RESTRICTED;
                $reason = 'vimeodomain';
            }
        }

        $token = self::api_token();
        if ($token !== '' && empty($oembed['blocked']) && $status !== link_classifier::RATELIMITED) {
            $api = self::api_check($fetcher, $token, $id, $hash);
            if ($api['found'] === true) {
                if ($status !== link_classifier::AVAILABLE) {
                    // The account can see it: it exists but is restricted for anonymous embedding.
                    $status = link_classifier::RESTRICTED;
                    $reason = 'vimeorestricted';
                }
                $metadata['captions'] = $api['captions'];
                $metadata['captioncount'] = $api['captioncount'];
                $metadata['captionlanguages'] = $api['languages'];
                $metadata['captionsource'] = $api['captions'] === 'unknown' ? '' : 'api';
            } else if ($api['found'] === false && $code === 404) {
                // Both oEmbed and the API (for the configured account) report not found.
                $failures = $previousfailures + 1;
                if ($failures >= max(1, $confirmattempts)) {
                    $status = link_classifier::UNAVAILABLE;
                    $reason = 'vimeogone';
                } else {
                    $reason = 'notfoundpending';
                }
            } else if ($api['found'] === 'ratelimited') {
                $metadata['captions'] = 'unknown';
            }
        }
        return ['status' => $status, 'reason' => $reason, 'failures' => $failures, 'code' => $code,
            'metadata' => $metadata];
    }

    /**
     * Query the Vimeo API for existence and text tracks.
     *
     * @param http_fetcher $fetcher
     * @param string $token
     * @param string $id
     * @param string $hash
     * @return array{found: bool|string|null, captions: string, captioncount: int, languages: string[]}
     */
    private static function api_check(http_fetcher $fetcher, string $token, string $id, string $hash): array {
        $headers = [
            'Authorization: bearer ' . $token,
            'Accept: application/vnd.vimeo.*+json;version=3.4',
        ];
        $ref = $id . ($hash !== '' ? ':' . $hash : '');
        $out = ['found' => null, 'captions' => 'unknown', 'captioncount' => 0, 'languages' => []];
        $video = $fetcher->fetch(self::API . rawurlencode($ref) . '?fields=uri,name', 'GET', $headers);
        $code = (int) $video['code'];
        if ($code === 404) {
            $out['found'] = false;
            return $out;
        }
        if ($code === 429) {
            $out['found'] = 'ratelimited';
            return $out;
        }
        if ($code !== 200) {
            return $out; // Unknown: token scope, network, etc.
        }
        $out['found'] = true;
        $tracks = $fetcher->fetch(self::API . rawurlencode($id) . '/texttracks?fields=type,language,active', 'GET', $headers);
        if ((int) $tracks['code'] !== 200) {
            return $out; // Could not read tracks: captions stay unknown.
        }
        $json = json_decode($tracks['body'], true);
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
            return $out;
        }
        $count = 0;
        $languages = [];
        foreach ($json['data'] as $track) {
            $type = strtolower((string) ($track['type'] ?? ''));
            if (in_array($type, ['captions', 'subtitles'], true) && !empty($track['active'])) {
                $count++;
                $languages[] = clean_param((string) ($track['language'] ?? ''), PARAM_ALPHANUMEXT);
            }
        }
        $out['captions'] = $count > 0 ? 'present' : 'none';
        $out['captioncount'] = $count;
        $out['languages'] = array_values(array_unique(array_filter($languages)));
        return $out;
    }
}
