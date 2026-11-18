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
 * Activity introductions (including Text and media areas / labels).
 *
 * Covers every module whose instance table has intro/introformat columns.
 * Question text, student submissions and forum posts are not included.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_intro_provider extends module_provider_base {
    /** @var array<string, bool> Cache of module tables having an intro field. */
    private array $hasintro = [];

    /**
     * Provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'activity_intro';
    }

    /**
     * Display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('provider_activity_intro', 'local_accessibilityreview');
    }

    /**
     * Whether the module table has intro fields.
     *
     * @param string $modname
     * @return bool
     */
    private function has_intro(string $modname): bool {
        global $DB;
        if (!isset($this->hasintro[$modname])) {
            $columns = $DB->get_columns($modname);
            $this->hasintro[$modname] = isset($columns['intro']) && isset($columns['introformat']) && isset($columns['name']);
        }
        return $this->hasintro[$modname];
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
        $sql = 'SELECT cm.id, cm.instance, m.name AS modname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :course AND cm.id > :after AND cm.deletioninprogress = 0
              ORDER BY cm.id ASC';
        $cms = $DB->get_records_sql($sql, ['course' => $course->id, 'after' => $after], 0, $limit);

        // One query per module type in this batch, not one per activity.
        $byinstance = [];
        foreach ($cms as $cm) {
            if ($this->has_intro($cm->modname)) {
                $byinstance[$cm->modname][] = (int) $cm->instance;
            }
        }
        $instances = [];
        foreach ($byinstance as $modname => $ids) {
            $instances[$modname] = $DB->get_records_list(
                $modname,
                'id',
                array_unique($ids),
                '',
                'id, name, intro, introformat'
            );
        }

        $items = [];
        foreach ($cms as $cm) {
            $html = null;
            $label = '';
            if (isset($instances[$cm->modname])) {
                $instance = $instances[$cm->modname][$cm->instance] ?? null;
                if ($instance) {
                    $html = content_item::to_html($instance->intro, (int) $instance->introformat);
                    $label = $this->activity_label((string) $instance->name, $cm->modname);
                }
            }
            // Items without intro are still returned so the resume key advances.
            $items[] = new content_item(
                $this->get_name(),
                (int) $course->id,
                (int) $cm->id,
                (int) $cm->instance,
                'intro',
                $label,
                $html,
                (int) $cm->id
            );
        }
        return $items;
    }
}
