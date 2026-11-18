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

namespace local_accessibilityreview\local\inventory;

use local_accessibilityreview\local\resource_manager;

/**
 * Inventories SCORM packages for manual review.
 *
 * Reads only database metadata. Package files are not opened, manifests are
 * not parsed and no SCORM JavaScript is ever executed.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorm_inventory implements inventory_provider {
    /**
     * Process the next batch of SCORM activities.
     *
     * @param \stdClass $course
     * @param int $after
     * @param int $limit
     * @param int $scanid
     * @return array{count: int, last: int, done: bool}
     */
    public function process(\stdClass $course, int $after, int $limit, int $scanid): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('scorm')) {
            return ['count' => 0, 'last' => $after, 'done' => true];
        }
        $sql = "SELECT cm.id AS cmid, s.id, s.name, s.scormtype, s.version, s.reference
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                  JOIN {scorm} s ON s.id = cm.instance
                 WHERE cm.course = :course AND cm.id > :after AND cm.deletioninprogress = 0
              ORDER BY cm.id ASC";
        $records = $DB->get_records_sql($sql, ['course' => $course->id, 'after' => $after], 0, $limit);
        $last = $after;
        foreach ($records as $record) {
            $last = (int) $record->cmid;
            $context = \context_module::instance($record->cmid, IGNORE_MISSING);
            $package = null;
            if ($context) {
                $package = $DB->get_record_select(
                    'files',
                    "contextid = :ctx AND component = 'mod_scorm' AND filearea = 'package' AND filename <> '.'",
                    ['ctx' => $context->id],
                    'id, filename, filesize, contenthash',
                    IGNORE_MULTIPLE
                );
            }
            $a = (object) ['name' => format_string($record->name, true, ['escape' => false]),
                'type' => get_string('modulename', 'mod_scorm')];
            resource_manager::upsert($scanid, [
                'identity' => sha1('scorm|' . $record->cmid),
                'courseid' => (int) $course->id,
                'cmid' => (int) $record->cmid,
                'restype' => 'scorm',
                'source' => 'package',
                'name' => \core_text::substr(format_string($record->name, true, ['escape' => false]), 0, 255),
                'location' => \core_text::substr(get_string('activitylabel', 'local_accessibilityreview', $a), 0, 255),
                'provider' => 'mod_scorm',
                'itemid' => (int) $record->id,
                'fileid' => $package ? (int) $package->id : 0,
                'filesize' => $package ? (int) $package->filesize : 0,
                'filehash' => $package ? (string) $package->contenthash : '',
                'analysis' => [
                    'scormtype' => clean_param((string) $record->scormtype, PARAM_ALPHANUMEXT),
                    'version' => clean_param((string) $record->version, PARAM_TEXT),
                ],
            ]);
        }
        return ['count' => count($records), 'last' => $last, 'done' => count($records) < $limit];
    }
}
