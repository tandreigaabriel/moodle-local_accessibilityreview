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

namespace local_accessibilityreview\local\content;

/**
 * One scannable content field supplied by a content provider.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_item {
    /**
     * Constructor.
     *
     * @param string $provider Provider name.
     * @param int $courseid Course id.
     * @param int $cmid Course module id, 0 when not an activity.
     * @param int $itemid Source record id (section id, chapter id, instance id...).
     * @param string $field Source field name.
     * @param string $label Human-readable location (already plain text).
     * @param string|null $html HTML to check, or null when the item only supplies URLs.
     * @param int $sortkey Monotonic key used to resume scanning after this item.
     * @param string[] $urls Extra URLs to record (for example a URL resource's target).
     */
    public function __construct(
        /** @var string Provider name. */
        public readonly string $provider,
        /** @var int Course id. */
        public readonly int $courseid,
        /** @var int Course module id. */
        public readonly int $cmid,
        /** @var int Source record id. */
        public readonly int $itemid,
        /** @var string Source field. */
        public readonly string $field,
        /** @var string Location label. */
        public readonly string $label,
        /** @var string|null HTML content. */
        public readonly ?string $html,
        /** @var int Resume key. */
        public readonly int $sortkey,
        /** @var string[] Extra URLs. */
        public readonly array $urls = [],
    ) {
    }

    /**
     * Convert stored text in a Moodle text format to HTML for checking, without
     * running filters (filters can call external services and alter content).
     *
     * @param string|null $text
     * @param int|null $format FORMAT_* constant.
     * @return string|null Null for empty content or plain-text formats.
     */
    public static function to_html(?string $text, ?int $format): ?string {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $format = $format ?? (int) FORMAT_HTML;
        if ($format === (int) FORMAT_MARKDOWN) {
            return markdown_to_html($text);
        }
        if ($format === (int) FORMAT_PLAIN) {
            return null;
        }
        return $text; // FORMAT_HTML and FORMAT_MOODLE are stored as HTML.
    }
}
