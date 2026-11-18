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

use local_accessibilityreview\local\scanner;

/**
 * Optionally queues scans for courses whose last completed scan is older than
 * the configured interval. Disabled by default.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scheduled_scan extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_scheduledscan', 'local_accessibilityreview');
    }

    /**
     * Execute.
     *
     * @return void
     */
    public function execute() {
        if (\local_accessibilityreview\local\license::get_status() !== 'valid') {
            return;
        }
        global $DB;
        $config = get_config('local_accessibilityreview');
        if (empty($config->autoscan)) {
            mtrace('Automatic accessibility scans are disabled.');
            return;
        }
        $interval = max(1, (int) ($config->autoscandays ?? 7)) * DAYSECS;
        $limit = max(1, min(500, (int) ($config->autoscancourses ?? 20)));
        $sql = "SELECT c.id, COALESCE(MAX(s.timefinished), 0) AS lastscan
                  FROM {course} c
             LEFT JOIN {" . scanner::TABLE . "} s ON s.courseid = c.id AND s.status = :complete
                 WHERE c.id <> :siteid AND c.visible = 1
              GROUP BY c.id
                HAVING COALESCE(MAX(s.timefinished), 0) < :threshold
              ORDER BY COALESCE(MAX(s.timefinished), 0) ASC, c.id ASC";
        $courses = $DB->get_records_sql($sql, ['complete' => scanner::STATUS_COMPLETE, 'siteid' => SITEID,
            'threshold' => time() - $interval], 0, $limit);
        $queued = 0;
        foreach ($courses as $course) {
            $result = scanner::queue((int) $course->id, 0);
            if ($result['created']) {
                $queued++;
            }
        }
        mtrace('Queued ' . $queued . ' accessibility scan(s).');
    }
}
