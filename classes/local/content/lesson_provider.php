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
 * Lesson page contents (content pages and question page text).
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson_provider extends module_provider_base {
    /**
     * Provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'lesson_page';
    }

    /**
     * Display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('provider_lesson_page', 'local_accessibilityreview');
    }

    /**
     * Items, keyed by lesson page id.
     *
     * @param \stdClass $course
     * @param int $after
     * @param int $limit
     * @return content_item[]
     */
    public function get_items(\stdClass $course, int $after, int $limit): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('lesson_pages')) {
            return [];
        }
        $sql = "SELECT lp.id, lp.title, lp.contents, lp.contentsformat, l.name AS lessonname, cm.id AS cmid
                  FROM {lesson_pages} lp
                  JOIN {lesson} l ON l.id = lp.lessonid
                  JOIN {modules} m ON m.name = 'lesson'
                  JOIN {course_modules} cm ON cm.instance = l.id AND cm.module = m.id
                 WHERE l.course = :course AND cm.course = :course2 AND lp.id > :after AND cm.deletioninprogress = 0
              ORDER BY lp.id ASC";
        $records = $DB->get_records_sql(
            $sql,
            ['course' => $course->id, 'course2' => $course->id, 'after' => $after],
            0,
            $limit
        );
        $items = [];
        foreach ($records as $record) {
            $a = (object) [
                'lesson' => $this->activity_label((string) $record->lessonname, 'lesson'),
                'page' => format_string($record->title, true, ['escape' => false]),
            ];
            $items[] = new content_item(
                $this->get_name(),
                (int) $course->id,
                (int) $record->cmid,
                (int) $record->id,
                'contents',
                get_string('provider_lesson_label', 'local_accessibilityreview', $a),
                content_item::to_html($record->contents, (int) $record->contentsformat),
                (int) $record->id
            );
        }
        return $items;
    }

    /**
     * View URL for a lesson page.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_view_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        return new \moodle_url('/mod/lesson/view.php', ['id' => $cmid, 'pageid' => $itemid]);
    }

    /**
     * Edit URL for a lesson page.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_edit_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        $context = \context_module::instance($cmid, IGNORE_MISSING);
        if (!$context || !has_capability('mod/lesson:edit', $context)) {
            return null;
        }
        return new \moodle_url('/mod/lesson/editpage.php', ['id' => $cmid, 'pageid' => $itemid, 'edit' => 1]);
    }
}
