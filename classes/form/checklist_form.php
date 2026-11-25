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

use local_accessibilityreview\local\resource_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Manual review checklist for a PDF, video or SCORM package.
 *
 * Custom data: restype.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checklist_form extends \moodleform {
    /**
     * Definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $restype = $this->_customdata['restype'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'checklisthdr', get_string('checklist', 'local_accessibilityreview'));
        $answers = [];
        foreach (resource_manager::ANSWERS as $answer) {
            $answers[$answer] = get_string('answer_' . $answer, 'local_accessibilityreview');
        }
        foreach (resource_manager::checklist($restype) as $key) {
            $mform->addElement('select', 'q_' . $key, get_string('checklist_' . $key, 'local_accessibilityreview'), $answers);
            $mform->addHelpButton('q_' . $key, 'checklist_' . $key, 'local_accessibilityreview');
            $mform->setDefault('q_' . $key, 'notchecked');
        }

        $mform->addElement('header', 'outcomehdr', get_string('reviewoutcome', 'local_accessibilityreview'));
        $statuses = [];
        foreach (resource_manager::REVIEW_STATUSES as $status) {
            $statuses[$status] = get_string('review_' . $status, 'local_accessibilityreview');
        }
        $mform->addElement('select', 'reviewstatus', get_string('reviewstatus', 'local_accessibilityreview'), $statuses);
        $mform->addElement(
            'textarea',
            'reviewnotes',
            get_string('notes', 'local_accessibilityreview'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('reviewnotes', PARAM_TEXT);

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
        if (!in_array($data['reviewstatus'] ?? '', resource_manager::REVIEW_STATUSES, true)) {
            $errors['reviewstatus'] = get_string('invalidstate', 'local_accessibilityreview');
        }
        if (($data['reviewstatus'] ?? '') === 'reviewed') {
            foreach (resource_manager::checklist($this->_customdata['restype']) as $key) {
                if (($data['q_' . $key] ?? 'notchecked') === 'notchecked') {
                    $errors['reviewstatus'] = get_string('checklistincomplete', 'local_accessibilityreview');
                    break;
                }
            }
        }
        if (\core_text::strlen((string) ($data['reviewnotes'] ?? '')) > 5000) {
            $errors['reviewnotes'] = get_string('notetoolong', 'local_accessibilityreview');
        }
        return $errors;
    }
}
