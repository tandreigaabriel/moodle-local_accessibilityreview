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

namespace local_accessibilityreview\local;

/**
 * Computes the review status of a course without any compliance percentage.
 *
 * Status values:
 *  - notscanned: no scan has ever been queued.
 *  - inprogress: a scan is queued or running and none has completed yet.
 *  - failed: the latest scan failed and none has completed.
 *  - incomplete: the last completed scan could not process some items.
 *  - issues: open or in-progress findings exist.
 *  - noautomatedissues: last scan completed with nothing open.
 *  - manuallyreviewed: nothing open, and every inventoried PDF/video/SCORM
 *    item has a completed manual review (and at least something was reviewed).
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_status {
    /**
     * Status summary for a course.
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function get(int $courseid): \stdClass {
        global $DB;
        $out = (object) [
            'status' => 'notscanned',
            'latest' => scanner::latest_scan($courseid),
            'active' => scanner::active_scan($courseid),
            'lastcomplete' => scanner::latest_complete_scan($courseid),
            'open' => 0,
            'resources' => 0,
            'resourcesreviewed' => 0,
            'findingsreviewed' => 0,
        ];
        if (!$out->latest) {
            return $out;
        }
        if (!$out->lastcomplete) {
            $out->status = $out->active ? 'inprogress' : 'failed';
            return $out;
        }
        $out->open = $DB->count_records_select(
            finding_manager::TABLE,
            'courseid = :c AND present = 1 AND state IN (:o, :p)',
            ['c' => $courseid, 'o' => finding_manager::STATE_OPEN, 'p' => finding_manager::STATE_INPROGRESS]
        );
        $out->findingsreviewed = $DB->count_records_select(
            finding_manager::TABLE,
            'courseid = :c AND present = 1 AND reviewerid > 0',
            ['c' => $courseid]
        );
        $out->resources = $DB->count_records(resource_manager::TABLE, ['courseid' => $courseid, 'present' => 1]);
        $out->resourcesreviewed = $DB->count_records_select(
            resource_manager::TABLE,
            "courseid = :c AND present = 1 AND reviewstatus IN ('reviewed', 'notapplicable')",
            ['c' => $courseid]
        );

        if ((int) $out->lastcomplete->itemsfailed > 0) {
            $out->status = 'incomplete';
        } else if ($out->open > 0) {
            $out->status = 'issues';
        } else if ($out->resourcesreviewed === $out->resources && ($out->resources > 0 || $out->findingsreviewed > 0)) {
            $out->status = 'manuallyreviewed';
        } else {
            $out->status = 'noautomatedissues';
        }
        return $out;
    }
}
