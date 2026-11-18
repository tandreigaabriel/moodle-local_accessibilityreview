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

namespace local_accessibilityreview;

use local_accessibilityreview\local\scanner;

/**
 * Event observer that queues a delayed rescan when course content changes.
 *
 * Several edits within the delay share one queued scan, because a course
 * never has more than one queued or running scan.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Content in a course was created or changed.
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function content_changed(\core\event\base $event): void {
        $courseid = (int) $event->courseid;
        if ($courseid <= 0 || $courseid == SITEID || !get_config('local_accessibilityreview', 'rescanonedit')) {
            return;
        }
        try {
            $delay = max(1, (int) (get_config('local_accessibilityreview', 'rescandelay') ?: 10)) * MINSECS;
            scanner::queue($courseid, 0, $delay);
        } catch (\Throwable $e) {
            // Never break the user's edit because a scan could not be queued.
            debugging('local_accessibilityreview: could not queue rescan: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
