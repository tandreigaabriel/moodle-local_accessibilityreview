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
 * Inventories PDF and video files stored in teacher-authored course areas.
 *
 * Only metadata from the files table is read here; file content is never
 * loaded. Student-submitted areas (assignments, forums, workshops...) and
 * SCORM package contents are deliberately excluded via an allow-list.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_inventory implements inventory_provider {
    /**
     * Process the next batch of files.
     *
     * @param \stdClass $course
     * @param int $after
     * @param int $limit
     * @param int $scanid
     * @return array{count: int, last: int, done: bool}
     */
    public function process(\stdClass $course, int $after, int $limit, int $scanid): array {
        global $DB;
        $modlike = $DB->sql_like('f.component', ':modprefix');
        $videolike = $DB->sql_like('f.mimetype', ':videoprefix');
        $sql = "SELECT f.id, f.contextid, f.component, f.filearea, f.itemid, f.filepath, f.filename, f.filesize,
                       f.contenthash, f.mimetype, ctx.contextlevel, ctx.instanceid
                  FROM {files} f
                  JOIN {context} ctx ON ctx.id = f.contextid
                 WHERE f.id > :after
                   AND f.filename <> '.'
                   AND (f.mimetype = :pdfmime OR $videolike)
                   AND ((ctx.contextlevel = :courselevel AND ctx.instanceid = :courseid)
                        OR (ctx.contextlevel = :modulelevel AND ctx.instanceid IN (
                            SELECT cm.id FROM {course_modules} cm
                             WHERE cm.course = :courseid2 AND cm.deletioninprogress = 0)))
                   AND ((f.component = 'course' AND f.filearea IN ('summary', 'section'))
                        OR (f.filearea = 'intro' AND $modlike)
                        OR (f.component = 'mod_resource' AND f.filearea = 'content')
                        OR (f.component = 'mod_folder' AND f.filearea = 'content')
                        OR (f.component = 'mod_page' AND f.filearea = 'content')
                        OR (f.component = 'mod_book' AND f.filearea = 'chapter'))
              ORDER BY f.id ASC";
        $params = [
            'after' => $after,
            'pdfmime' => 'application/pdf',
            'videoprefix' => 'video/%',
            'courselevel' => CONTEXT_COURSE,
            'courseid' => $course->id,
            'modulelevel' => CONTEXT_MODULE,
            'courseid2' => $course->id,
            'modprefix' => 'mod_%',
        ];
        $files = $DB->get_records_sql($sql, $params, 0, $limit);
        $last = $after;
        $modinfo = null;
        foreach ($files as $file) {
            $last = (int) $file->id;
            $cmid = 0;
            $location = get_string('provider_course_summary', 'local_accessibilityreview');
            if ((int) $file->contextlevel === CONTEXT_MODULE) {
                $cmid = (int) $file->instanceid;
                $modinfo = $modinfo ?? get_fast_modinfo($course->id, -1);
                try {
                    $cm = $modinfo->get_cm($cmid);
                    $a = (object) ['name' => $cm->get_formatted_name(['escape' => false]),
                        'type' => get_string('modulename', 'mod_' . $cm->modname)];
                    $location = get_string('activitylabel', 'local_accessibilityreview', $a);
                } catch (\moodle_exception $e) {
                    $location = get_string('unknownlocation', 'local_accessibilityreview');
                }
            } else if ($file->filearea === 'section') {
                $location = get_string('provider_section_summary', 'local_accessibilityreview');
            }
            $ispdf = $file->mimetype === 'application/pdf';
            $identity = sha1(implode('|', ['file', $file->contextid, $file->component, $file->filearea, $file->itemid,
                $file->filepath . $file->filename]));
            resource_manager::upsert($scanid, [
                'identity' => $identity,
                'courseid' => (int) $course->id,
                'cmid' => $cmid,
                'restype' => $ispdf ? 'pdf' : 'video',
                'source' => 'file',
                'name' => \core_text::substr($file->filename, 0, 255),
                'location' => \core_text::substr($location, 0, 255),
                'provider' => $file->component . '/' . $file->filearea,
                'itemid' => (int) $file->itemid,
                'fileid' => (int) $file->id,
                'filesize' => (int) $file->filesize,
                'filehash' => (string) $file->contenthash,
            ]);
        }
        return ['count' => count($files), 'last' => $last, 'done' => count($files) < $limit];
    }
}
