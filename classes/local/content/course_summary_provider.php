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
 * Course summary provider.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_summary_provider implements content_provider {
    /**
     * Provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'course_summary';
    }

    /**
     * Display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('provider_course_summary', 'local_accessibilityreview');
    }

    /**
     * Items.
     *
     * @param \stdClass $course
     * @param int $after
     * @param int $limit
     * @return content_item[]
     */
    public function get_items(\stdClass $course, int $after, int $limit): array {
        global $DB;
        if ($after > 0 || $limit < 1) {
            return [];
        }
        $record = $DB->get_record('course', ['id' => $course->id], 'id, summary, summaryformat', MUST_EXIST);
        $html = content_item::to_html($record->summary, (int) $record->summaryformat);
        return [new content_item(
            $this->get_name(),
            (int) $course->id,
            0,
            (int) $course->id,
            'summary',
            get_string('provider_course_summary', 'local_accessibilityreview'),
            $html,
            1
        )];
    }

    /**
     * View URL.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_view_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $courseid]);
    }

    /**
     * Edit URL.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_edit_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context || !has_capability('moodle/course:update', $context)) {
            return null;
        }
        return new \moodle_url('/course/edit.php', ['id' => $courseid]);
    }
}
