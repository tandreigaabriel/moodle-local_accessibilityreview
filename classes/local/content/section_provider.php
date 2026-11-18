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
 * Course section summary provider.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_provider implements content_provider {
    /**
     * Provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'section_summary';
    }

    /**
     * Display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('provider_section_summary', 'local_accessibilityreview');
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
        $records = $DB->get_records_select(
            'course_sections',
            'course = :course AND id > :after',
            ['course' => $course->id, 'after' => $after],
            'id ASC',
            'id, section, name, summary, summaryformat',
            0,
            $limit
        );
        $items = [];
        foreach ($records as $record) {
            $name = trim((string) $record->name) !== '' ? format_string($record->name, true, ['escape' => false]) :
                get_string('sectionnumber', 'local_accessibilityreview', (int) $record->section);
            $label = get_string('provider_section_label', 'local_accessibilityreview', $name);
            $items[] = new content_item(
                $this->get_name(),
                (int) $course->id,
                0,
                (int) $record->id,
                'summary',
                $label,
                content_item::to_html($record->summary, (int) $record->summaryformat),
                (int) $record->id
            );
        }
        return $items;
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
        return new \moodle_url('/course/section.php', ['id' => $itemid]);
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
        return new \moodle_url('/course/editsection.php', ['id' => $itemid]);
    }
}
