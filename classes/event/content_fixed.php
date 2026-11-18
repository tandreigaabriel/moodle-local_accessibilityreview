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

namespace local_accessibilityreview\event;

/**
 * Course content was changed (or a change was undone) by an accessibility fix.
 *
 * @property-read array $other {
 *      - string action: applied or undone
 * }
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_fixed extends \core\event\base {
    /**
     * Create the event.
     *
     * @param int $fixid
     * @param \context $context Context of the edited content.
     * @param string $action applied or undone.
     * @return self
     */
    public static function create_from_fix(int $fixid, \context $context, string $action): self {
        /** @var self $event */
        $event = self::create([
            'objectid' => $fixid,
            'context' => $context,
            'other' => ['action' => $action],
        ]);
        return $event;
    }

    /**
     * Init.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'local_accessibilityreview_fix';
    }

    /**
     * Name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_contentfixed', 'local_accessibilityreview');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        $action = ($this->other['action'] ?? '') === 'undone' ? 'undid accessibility fix' : 'applied accessibility fix';
        return "The user with id '{$this->userid}' {$action} with id '{$this->objectid}' in context id '{$this->contextid}'.";
    }

    /**
     * Link to the course report.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/accessibilityreview/index.php', ['id' => $this->courseid, 'tab' => 'fixes']);
    }

    /**
     * Validate.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['action'])) {
            throw new \coding_exception('The \'action\' value must be set in other.');
        }
    }

    /**
     * Object id mapping for backup and restore (fix records are not backed up).
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_accessibilityreview_fix', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Other mapping.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
