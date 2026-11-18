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
 * Ad-hoc task processing one course scan for a bounded time, re-queuing
 * itself until the scan is finished.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scan_course extends \core\task\adhoc_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_scancourse', 'local_accessibilityreview');
    }

    /**
     * Execute.
     *
     * @return void
     */
    public function execute() {
        $data = $this->get_custom_data();
        $scanid = (int) ($data->scanid ?? 0);
        if (!$scanid) {
            return;
        }
        $timelimit = max(10, (int) (get_config('local_accessibilityreview', 'maxruntime') ?: 120));
        $result = scanner::process($scanid, $timelimit);
        if ($result === false) {
            mtrace('Accessibility scan ' . $scanid . ': time limit reached, continuing in a new task.');
            scanner::queue_task($scanid, 5);
        } else if ($result === null) {
            mtrace('Accessibility scan ' . $scanid . ': course is locked by another process, retrying later.');
            scanner::queue_task($scanid, 120);
        } else {
            mtrace('Accessibility scan ' . $scanid . ': finished.');
        }
    }
}
