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
 * Shared helpers for providers whose sources are course modules.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class module_provider_base implements content_provider {
    /**
     * Module name for a course module id.
     *
     * @param int $cmid
     * @return string|null
     */
    protected function modname(int $cmid): ?string {
        global $DB;
        $sql = 'SELECT m.name FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module WHERE cm.id = :cmid';
        $name = $DB->get_field_sql($sql, ['cmid' => $cmid]);
        return $name ? (string) $name : null;
    }

    /**
     * View URL for a module.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_view_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        $modname = $this->modname($cmid);
        if (!$modname) {
            return null;
        }
        return new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $cmid]);
    }

    /**
     * Edit URL (activity settings form).
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_edit_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        $context = \context_module::instance($cmid, IGNORE_MISSING);
        if (!$context || !has_capability('moodle/course:manageactivities', $context)) {
            return null;
        }
        return new \moodle_url('/course/modedit.php', ['update' => $cmid]);
    }

    /**
     * Build a location label for an activity.
     *
     * @param string $name Activity name (raw).
     * @param string $modname Module name.
     * @return string
     */
    protected function activity_label(string $name, string $modname): string {
        $a = (object) [
            'name' => format_string($name, true, ['escape' => false]),
            'type' => get_string('modulename', 'mod_' . $modname),
        ];
        return get_string('activitylabel', 'local_accessibilityreview', $a);
    }
}
