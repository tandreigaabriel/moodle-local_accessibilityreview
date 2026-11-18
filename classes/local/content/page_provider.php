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
 * Page activity content.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_provider extends module_provider_base {
    /**
     * Provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'page_content';
    }

    /**
     * Display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('provider_page_content', 'local_accessibilityreview');
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
        if (!$DB->get_manager()->table_exists('page')) {
            return [];
        }
        $sql = "SELECT cm.id AS cmid, p.id, p.name, p.content, p.contentformat
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'page'
                  JOIN {page} p ON p.id = cm.instance
                 WHERE cm.course = :course AND cm.id > :after AND cm.deletioninprogress = 0
              ORDER BY cm.id ASC";
        $records = $DB->get_records_sql($sql, ['course' => $course->id, 'after' => $after], 0, $limit);
        $items = [];
        foreach ($records as $record) {
            $items[] = new content_item(
                $this->get_name(),
                (int) $course->id,
                (int) $record->cmid,
                (int) $record->id,
                'content',
                $this->activity_label((string) $record->name, 'page'),
                content_item::to_html($record->content, (int) $record->contentformat),
                (int) $record->cmid
            );
        }
        return $items;
    }
}
