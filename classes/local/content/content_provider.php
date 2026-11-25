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
 * Supplies scannable content for one kind of source.
 *
 * Implementations must be read-only, must page through records with a
 * monotonic sort key (so scans can resume after a time limit) and must never
 * load an entire course into memory.
 *
 * Other plugins can contribute providers through the
 * \local_accessibilityreview\hook\collect_providers hook.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface content_provider {
    /**
     * Stable provider name stored with findings (lower-case, max 50 chars).
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Content type label used in report filters.
     *
     * @return string
     */
    public function get_display_name(): string;

    /**
     * Next batch of items with a sort key greater than $after.
     *
     * @param \stdClass $course Course record.
     * @param int $after Sort key of the last processed item (0 to start).
     * @param int $limit Maximum number of items.
     * @return content_item[] Ordered by sort key. Fewer than $limit means the provider is exhausted.
     */
    public function get_items(\stdClass $course, int $after, int $limit): array;

    /**
     * URL where the source can be viewed.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_view_url(int $courseid, int $cmid, int $itemid): ?\moodle_url;

    /**
     * URL where the source can be edited, or null if the current user may not edit it.
     *
     * Implementations must check the relevant capability for the current user.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_edit_url(int $courseid, int $cmid, int $itemid): ?\moodle_url;
}
