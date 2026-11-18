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

namespace local_accessibilityreview\form;

use local_accessibilityreview\local\finding_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Finding review form (state + note). moodleform adds and checks the sesskey.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_form extends \moodleform {
    /**
     * Definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $options = [];
        foreach (finding_manager::states() as $state) {
            $options[$state] = get_string('state_' . $state, 'local_accessibilityreview');
        }
        $mform->addElement('select', 'state', get_string('reviewstatus', 'local_accessibilityreview'), $options);
        $mform->addHelpButton('state', 'reviewstatus', 'local_accessibilityreview');

        $mform->addElement(
            'textarea',
            'note',
            get_string('notes', 'local_accessibilityreview'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('note', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('savereview', 'local_accessibilityreview'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!in_array($data['state'] ?? '', finding_manager::states(), true)) {
            $errors['state'] = get_string('invalidstate', 'local_accessibilityreview');
        }
        if (($data['state'] ?? '') === finding_manager::STATE_EXCEPTION && trim((string) ($data['note'] ?? '')) === '') {
            $errors['note'] = get_string('exceptionneedsnote', 'local_accessibilityreview');
        }
        if (\core_text::strlen((string) ($data['note'] ?? '')) > 5000) {
            $errors['note'] = get_string('notetoolong', 'local_accessibilityreview');
        }
        return $errors;
    }
}
