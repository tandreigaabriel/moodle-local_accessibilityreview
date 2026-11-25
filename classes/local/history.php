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
 * Audit history for findings and inventory reviews.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history {
    /** @var string Table name. */
    public const TABLE = 'local_accessibilityreview_history';

    /**
     * Add a history entry.
     *
     * @param string $itemtype finding or resource.
     * @param int $itemid
     * @param int $courseid
     * @param int $userid 0 for system.
     * @param string $action detected, statechange, note, reopened, reappeared, notdetected, sourceremoved, review.
     * @param string|null $fromstate
     * @param string|null $tostate
     * @param string|null $note
     * @return int
     */
    public static function add(
        string $itemtype,
        int $itemid,
        int $courseid,
        int $userid,
        string $action,
        ?string $fromstate,
        ?string $tostate,
        ?string $note
    ): int {
        global $DB;
        return (int) $DB->insert_record(self::TABLE, (object) [
            'itemtype' => $itemtype,
            'itemid' => $itemid,
            'courseid' => $courseid,
            'userid' => $userid,
            'action' => $action,
            'fromstate' => $fromstate,
            'tostate' => $tostate,
            'note' => $note,
            'timecreated' => time(),
        ]);
    }

    /**
     * History for an item, newest first.
     *
     * @param string $itemtype
     * @param int $itemid
     * @return \stdClass[]
     */
    public static function get(string $itemtype, int $itemid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['itemtype' => $itemtype, 'itemid' => $itemid], 'timecreated DESC, id DESC');
    }
}
