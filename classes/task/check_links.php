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

use local_accessibilityreview\local\links\link_manager;

/**
 * Checks link availability for links that are due. One runner at a time
 * (scheduled tasks are not run concurrently), with a per-host delay.
 *
 * Log output never includes full URLs, so private Vimeo hashes and query
 * strings are not written to task logs.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_links extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_checklinks', 'local_accessibilityreview');
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
        $config = get_config('local_accessibilityreview');
        if (empty($config->linkcheck)) {
            mtrace('Link checking is disabled.');
            return;
        }
        $max = max(1, min(1000, (int) ($config->linksperrun ?? 100)));
        $timelimit = max(10, (int) ($config->linktimelimit ?? 300));
        $counts = link_manager::check_due($max, $timelimit);
        foreach ($counts as $status => $count) {
            mtrace('Links ' . $status . ': ' . $count);
        }
        if (!$counts) {
            mtrace('No links due for checking.');
        }
    }
}
