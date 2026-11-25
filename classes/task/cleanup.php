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

namespace local_accessibilityreview\task;

use local_accessibilityreview\local\finding_manager;
use local_accessibilityreview\local\history;
use local_accessibilityreview\local\fixer;
use local_accessibilityreview\local\links\link_manager;
use local_accessibilityreview\local\progress;
use local_accessibilityreview\local\resource_manager;
use local_accessibilityreview\local\scanner;

/**
 * Housekeeping: resumes stalled scans, applies retention and removes data for
 * deleted courses.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup', 'local_accessibilityreview');
    }

    /**
     * Execute.
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $now = time();
        $retention = max(30, (int) (get_config('local_accessibilityreview', 'retentiondays') ?: 365)) * DAYSECS;

        // Stalled scans: resume after 2 hours without progress, fail after 1 day.
        $stalled = $DB->get_records_select(
            scanner::TABLE,
            'status IN (:q, :r) AND timemodified < :cutoff',
            ['q' => scanner::STATUS_QUEUED, 'r' => scanner::STATUS_RUNNING, 'cutoff' => $now - 2 * HOURSECS]
        );
        if (\local_accessibilityreview\local\license::get_status() !== 'valid') {
            $stalled = [];
        }
        foreach ($stalled as $scan) {
            if ($scan->timemodified < $now - DAYSECS) {
                scanner::fail($scan, get_string('error_stalled', 'local_accessibilityreview'));
                mtrace('Marked stalled scan ' . $scan->id . ' as failed.');
            } else {
                $DB->set_field(scanner::TABLE, 'timemodified', $now, ['id' => $scan->id]);
                scanner::queue_task((int) $scan->id, 0);
                mtrace('Re-queued stalled scan ' . $scan->id . '.');
            }
        }

        // Data for deleted courses.
        foreach (
            [scanner::TABLE, finding_manager::TABLE, history::TABLE, link_manager::USE_TABLE,
                resource_manager::TABLE, progress::TABLE, fixer::TABLE] as $table
        ) {
            $DB->delete_records_select($table, 'courseid NOT IN (SELECT id FROM {course})');
        }

        // Old scan records (always keep the latest per course).
        $DB->delete_records_select(
            scanner::TABLE,
            'timecreated < :cutoff AND status IN (:c, :f) AND id NOT IN (
                SELECT latest.id FROM (SELECT MAX(id) AS id FROM {' . scanner::TABLE . '} GROUP BY courseid) latest)',
            ['cutoff' => $now - $retention, 'c' => scanner::STATUS_COMPLETE, 'f' => scanner::STATUS_FAILED]
        );

        // Findings and inventory items absent for longer than the retention period (with their history).
        $this->purge(finding_manager::TABLE, 'finding', 'present = 0 AND timelastseen < :cutoff', $now - $retention);
        $this->purge(resource_manager::TABLE, 'resource', 'present = 0 AND timelastseen < :cutoff', $now - $retention);

        // Progress snapshots and fix records (with the content they kept for undo) past retention.
        $DB->delete_records_select(progress::TABLE, 'timecreated < :cutoff', ['cutoff' => $now - $retention]);
        $DB->delete_records_select(fixer::TABLE, 'timecreated < :cutoff', ['cutoff' => $now - $retention]);

        // Fix records whose content was deleted (activity, entry, chapter, page): drop the copies kept for undo,
        // so text written by a user does not outlive its deletion, for example a privacy request for a glossary entry.
        $DB->delete_records_select(fixer::TABLE, 'cmid > 0 AND cmid NOT IN (SELECT id FROM {course_modules})');
        $dbman = $DB->get_manager();
        foreach (fixer::item_tables() as $provider => $itemtable) {
            if ($dbman->table_exists($itemtable)) {
                $DB->delete_records_select(
                    fixer::TABLE,
                    "provider = :provider AND itemid NOT IN (SELECT id FROM {{$itemtable}})",
                    ['provider' => $provider]
                );
            }
        }

        // Links no longer used anywhere.
        $DB->delete_records_select(link_manager::TABLE, 'timecreated < :cutoff AND id NOT IN (
            SELECT linkid FROM {' . link_manager::USE_TABLE . '})', ['cutoff' => $now - DAYSECS]);
    }

    /**
     * Delete rows and their history.
     *
     * @param string $table
     * @param string $itemtype
     * @param string $select
     * @param int $cutoff
     * @return void
     */
    private function purge(string $table, string $itemtype, string $select, int $cutoff): void {
        global $DB;
        $ids = $DB->get_fieldset_select($table, 'id', $select, ['cutoff' => $cutoff]);
        foreach (array_chunk($ids, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $DB->delete_records_select(
                history::TABLE,
                "itemtype = :itemtype AND itemid $insql",
                $params + ['itemtype' => $itemtype]
            );
            $DB->delete_records_select($table, "id $insql", $params);
        }
    }
}
