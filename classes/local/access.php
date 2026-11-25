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
 * Server-side permission helpers used by every page, export and action.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /**
     * Whether the current user can view the report for a course.
     *
     * @param int $courseid
     * @return bool
     */
    public static function can_view_course(int $courseid): bool {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        return $context && has_capability('local/accessibilityreview:view', $context);
    }

    /**
     * Whether the current user can queue scans for a course.
     *
     * @param int $courseid
     * @return bool
     */
    public static function can_scan_course(int $courseid): bool {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        return $context && has_capability('local/accessibilityreview:scan', $context);
    }

    /**
     * Whether the current user can review findings in a course.
     *
     * @param int $courseid
     * @return bool
     */
    public static function can_review_course(int $courseid): bool {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        return $context && has_capability('local/accessibilityreview:review', $context);
    }

    /**
     * Whether the current user can use the site report.
     *
     * @return bool
     */
    public static function can_view_site(): bool {
        return has_capability('local/accessibilityreview:view', \context_system::instance());
    }

    /**
     * Require login and course view permission; returns the course and context.
     *
     * @param int $courseid
     * @return array{0: \stdClass, 1: \context_course}
     */
    public static function require_course_view(int $courseid): array {
        $course = get_course($courseid);
        require_login($course, false);
        $context = \context_course::instance($course->id);
        require_capability('local/accessibilityreview:view', $context);
        return [$course, $context];
    }

    /**
     * Require login and site report permission.
     *
     * @return \context_system
     */
    public static function require_site_view(): \context_system {
        require_login(null, false);
        $context = \context_system::instance();
        require_capability('local/accessibilityreview:view', $context);
        return $context;
    }
}
