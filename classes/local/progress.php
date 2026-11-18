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

use local_accessibilityreview\local\links\link_classifier;
use local_accessibilityreview\local\links\link_manager;

/**
 * Progress over time: counts saved after each completed scan.
 *
 * Counts only, never a percentage score: a percentage of automated checks
 * passed would suggest a level of accessibility that automated checks cannot measure.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {
    /** @var string Table name. */
    public const TABLE = 'local_accessibilityreview_snap';

    /**
     * Current counts for a course (two grouped queries plus two counts).
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function current(int $courseid): \stdClass {
        global $DB;
        $out = (object) ['errors' => 0, 'warnings' => 0, 'notices' => 0, 'resolved' => 0, 'accepted' => 0,
            'linksbroken' => 0, 'unreviewed' => 0, 'details' => []];
        $rs = $DB->get_recordset_sql("SELECT severity, state, category, COUNT(1) AS total
                                        FROM {" . finding_manager::TABLE . "}
                                       WHERE courseid = :courseid AND present = 1
                                    GROUP BY severity, state, category", ['courseid' => $courseid]);
        foreach ($rs as $row) {
            $total = (int) $row->total;
            if ($row->state === finding_manager::STATE_RESOLVED) {
                $out->resolved += $total;
            } else if ($row->state === finding_manager::STATE_EXCEPTION) {
                $out->accepted += $total;
            } else {
                $key = $row->severity . 's';
                if (property_exists($out, $key)) {
                    $out->$key += $total;
                }
                $out->details[$row->category] = ($out->details[$row->category] ?? 0) + $total;
            }
        }
        $rs->close();
        $out->linksbroken = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT l.id)
                                                            FROM {" . link_manager::TABLE . "} l
                                                            JOIN {" . link_manager::USE_TABLE . "} u ON u.linkid = l.id
                                                           WHERE u.courseid = :courseid AND l.status = :status",
            ['courseid' => $courseid, 'status' => link_classifier::UNAVAILABLE]
        );
        $out->unreviewed = $DB->count_records(
            resource_manager::TABLE,
            ['courseid' => $courseid, 'present' => 1, 'reviewstatus' => 'notreviewed']
        );
        return $out;
    }

    /**
     * Save a snapshot for a completed scan.
     *
     * @param int $courseid
     * @param int $scanid
     * @return int Snapshot id.
     */
    public static function snapshot(int $courseid, int $scanid): int {
        global $DB;
        $counts = self::current($courseid);
        $counts->details = json_encode($counts->details);
        $counts->courseid = $courseid;
        $counts->scanid = $scanid;
        $counts->timecreated = time();
        return (int) $DB->insert_record(self::TABLE, $counts);
    }

    /**
     * Most recent snapshots, newest first.
     *
     * @param int $courseid
     * @param int $limit
     * @return \stdClass[]
     */
    public static function history(int $courseid, int $limit = 12): array {
        global $DB;
        return array_values($DB->get_records(
            self::TABLE,
            ['courseid' => $courseid],
            'timecreated DESC, id DESC',
            '*',
            0,
            $limit
        ));
    }

    /**
     * Change in open errors, warnings and notices between the oldest and newest of the given snapshots.
     *
     * @param \stdClass[] $snapshots Newest first.
     * @return array{errors: int, warnings: int, notices: int}|null Null with fewer than two snapshots.
     */
    public static function change(array $snapshots): ?array {
        if (count($snapshots) < 2) {
            return null;
        }
        $newest = reset($snapshots);
        $oldest = end($snapshots);
        return [
            'errors' => (int) $newest->errors - (int) $oldest->errors,
            'warnings' => (int) $newest->warnings - (int) $oldest->warnings,
            'notices' => (int) $newest->notices - (int) $oldest->notices,
        ];
    }
}
