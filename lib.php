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

/**
 * Navigation callbacks (kept minimal; logic lives in autoloaded classes).
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the course accessibility report to the course administration menu
 * (shown under "More" in the Boost/Adaptable secondary navigation).
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 * @return void
 */
function local_accessibilityreview_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if ($course->id == SITEID || !has_capability('local/accessibilityreview:view', $context)) {
        return;
    }
    $navigation->add(
        get_string('coursereport', 'local_accessibilityreview'),
        new moodle_url('/local/accessibilityreview/index.php', ['id' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_accessibilityreview',
        new pix_icon('i/report', '')
    );
}
