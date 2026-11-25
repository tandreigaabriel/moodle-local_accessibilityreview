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

/**
 * Builds part of the PDF / video / SCORM inventory for a course.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface inventory_provider {
    /**
     * Process the next batch.
     *
     * @param \stdClass $course
     * @param int $after Resume key (0 to start).
     * @param int $limit Maximum records to process.
     * @param int $scanid Current scan id.
     * @return array{count: int, last: int, done: bool}
     */
    public function process(\stdClass $course, int $after, int $limit, int $scanid): array;
}
