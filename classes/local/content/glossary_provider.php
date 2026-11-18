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
 * Approved glossary entry definitions.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class glossary_provider extends module_provider_base {
    /**
     * Provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'glossary_entry';
    }

    /**
     * Display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('provider_glossary_entry', 'local_accessibilityreview');
    }

    /**
     * Items, keyed by entry id.
     *
     * @param \stdClass $course
     * @param int $after
     * @param int $limit
     * @return content_item[]
     */
    public function get_items(\stdClass $course, int $after, int $limit): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('glossary_entries')) {
            return [];
        }
        $sql = "SELECT ge.id, ge.concept, ge.definition, ge.definitionformat, g.name AS glossaryname, cm.id AS cmid
                  FROM {glossary_entries} ge
                  JOIN {glossary} g ON g.id = ge.glossaryid
                  JOIN {modules} m ON m.name = 'glossary'
                  JOIN {course_modules} cm ON cm.instance = g.id AND cm.module = m.id
                 WHERE g.course = :course AND cm.course = :course2 AND ge.id > :after AND ge.approved = 1
                       AND cm.deletioninprogress = 0
              ORDER BY ge.id ASC";
        $records = $DB->get_records_sql(
            $sql,
            ['course' => $course->id, 'course2' => $course->id, 'after' => $after],
            0,
            $limit
        );
        $items = [];
        foreach ($records as $record) {
            $a = (object) [
                'glossary' => $this->activity_label((string) $record->glossaryname, 'glossary'),
                'entry' => format_string($record->concept, true, ['escape' => false]),
            ];
            $items[] = new content_item(
                $this->get_name(),
                (int) $course->id,
                (int) $record->cmid,
                (int) $record->id,
                'definition',
                get_string('provider_glossary_label', 'local_accessibilityreview', $a),
                content_item::to_html($record->definition, (int) $record->definitionformat),
                (int) $record->id
            );
        }
        return $items;
    }

    /**
     * View URL for an entry.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_view_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        return new \moodle_url('/mod/glossary/showentry.php', ['eid' => $itemid]);
    }

    /**
     * Edit URL for an entry.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_edit_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        $context = \context_module::instance($cmid, IGNORE_MISSING);
        if (!$context || !has_capability('mod/glossary:manageentries', $context)) {
            return null;
        }
        return new \moodle_url('/mod/glossary/edit.php', ['cmid' => $cmid, 'id' => $itemid]);
    }
}
