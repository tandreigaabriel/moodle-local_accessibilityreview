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
 * Book chapter content.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class book_provider extends module_provider_base {
    /**
     * Provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'book_chapter';
    }

    /**
     * Display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('provider_book_chapter', 'local_accessibilityreview');
    }

    /**
     * Items, keyed by chapter id.
     *
     * @param \stdClass $course
     * @param int $after
     * @param int $limit
     * @return content_item[]
     */
    public function get_items(\stdClass $course, int $after, int $limit): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('book_chapters')) {
            return [];
        }
        $sql = "SELECT bc.id, bc.title, bc.content, bc.contentformat, b.name AS bookname, cm.id AS cmid
                  FROM {book_chapters} bc
                  JOIN {book} b ON b.id = bc.bookid
                  JOIN {modules} m ON m.name = 'book'
                  JOIN {course_modules} cm ON cm.instance = b.id AND cm.module = m.id
                 WHERE b.course = :course AND cm.course = :course2 AND bc.id > :after AND cm.deletioninprogress = 0
              ORDER BY bc.id ASC";
        $records = $DB->get_records_sql(
            $sql,
            ['course' => $course->id, 'course2' => $course->id, 'after' => $after],
            0,
            $limit
        );
        $items = [];
        foreach ($records as $record) {
            $a = (object) [
                'book' => $this->activity_label((string) $record->bookname, 'book'),
                'chapter' => format_string($record->title, true, ['escape' => false]),
            ];
            $items[] = new content_item(
                $this->get_name(),
                (int) $course->id,
                (int) $record->cmid,
                (int) $record->id,
                'content',
                get_string('provider_book_label', 'local_accessibilityreview', $a),
                content_item::to_html($record->content, (int) $record->contentformat),
                (int) $record->id
            );
        }
        return $items;
    }

    /**
     * View URL for a chapter.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_view_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        return new \moodle_url('/mod/book/view.php', ['id' => $cmid, 'chapterid' => $itemid]);
    }

    /**
     * Edit URL for a chapter.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_edit_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        $context = \context_module::instance($cmid, IGNORE_MISSING);
        if (!$context || !has_capability('mod/book:edit', $context)) {
            return null;
        }
        return new \moodle_url('/mod/book/edit.php', ['cmid' => $cmid, 'id' => $itemid]);
    }
}
