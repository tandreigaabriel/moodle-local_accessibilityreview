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

use local_accessibilityreview\local\fix\fix_engine;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for fixing one finding. Fields depend on the check.
 *
 * Custom data: checkid (string), suggestion (string), fromlevel (int).
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fix_form extends \moodleform {
    /**
     * Definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $p = 'local_accessibilityreview';
        $checkid = (string) $this->_customdata['checkid'];
        $suggestion = (string) ($this->_customdata['suggestion'] ?? '');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        switch ($checkid) {
            case 'img_alt_missing':
            case 'img_alt_suspicious':
            case 'img_alt_empty':
                $mform->addElement(
                    'advcheckbox',
                    'decorative',
                    get_string('fix_decorative', $p),
                    get_string('fix_decorative_label', $p)
                );
                $mform->addHelpButton('decorative', 'fix_decorative', $p);
                $mform->addElement(
                    'textarea',
                    'text',
                    get_string('fix_alttext', $p),
                    ['rows' => 2, 'cols' => 60, 'maxlength' => fix_engine::MAX_TEXT]
                );
                $mform->setType('text', PARAM_TEXT);
                $mform->addHelpButton('text', 'fix_alttext', $p);
                $mform->hideIf('text', 'decorative', 'checked');
                if ($checkid === 'img_alt_empty') {
                    $mform->setDefault('decorative', 1);
                }
                break;

            case 'iframe_title_missing':
            case 'iframe_title_generic':
            case 'object_title_missing':
                $mform->addElement(
                    'text',
                    'text',
                    get_string('fix_title', $p),
                    ['size' => 60, 'maxlength' => fix_engine::MAX_TEXT]
                );
                $mform->setType('text', PARAM_TEXT);
                $mform->addHelpButton('text', 'fix_title', $p);
                if ($suggestion !== '') {
                    $mform->setDefault('text', $suggestion);
                }
                break;

            case 'table_no_headers':
                $options = [];
                foreach (['row', 'column', 'both'] as $value) {
                    $options[] = $mform->createElement('radio', 'headers', '', get_string('fix_headers_' . $value, $p), $value);
                }
                $mform->addGroup($options, 'headersgroup', get_string('fix_headers', $p), \html_writer::empty_tag('br'), false);
                $mform->setDefault('headers', 'row');
                $mform->setType('headers', PARAM_ALPHA);
                break;

            case 'heading_skip':
                $from = max(1, (int) ($this->_customdata['fromlevel'] ?? 1));
                $levels = [];
                for ($i = 1; $i <= 6; $i++) {
                    $levels[$i] = get_string('fix_level_option', $p, $i);
                }
                $mform->addElement('select', 'level', get_string('fix_level', $p), $levels);
                $mform->setDefault('level', min(6, $from + 1));
                $mform->setType('level', PARAM_INT);
                break;

            case 'link_new_window':
                $options = [
                    $mform->createElement('radio', 'mode', '', get_string('fix_mode_sametab', $p), 'sametab'),
                    $mform->createElement('radio', 'mode', '', get_string('fix_mode_label', $p), 'label'),
                ];
                $mform->addGroup($options, 'modegroup', get_string('fix_mode', $p), \html_writer::empty_tag('br'), false);
                $mform->setDefault('mode', 'label');
                $mform->setType('mode', PARAM_ALPHA);
                $mform->addElement('text', 'label', get_string('fix_label', $p), ['size' => 40, 'maxlength' => 60]);
                $mform->setType('label', PARAM_TEXT);
                $mform->setDefault('label', get_string('fix_label_default', $p));
                $mform->hideIf('label', 'mode', 'neq', 'label');
                break;

            case 'media_autoplay':
                $mform->addElement('static', 'autoplayinfo', '', get_string('fix_autoplay_info', $p));
                break;
        }

        $buttons = [
            $mform->createElement('submit', 'previewbutton', get_string('fix_preview', $p)),
            $mform->createElement('submit', 'applybutton', get_string('fix_apply', $p)),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
        $mform->closeHeaderBefore('buttonar');
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
        $checkid = (string) $this->_customdata['checkid'];
        $p = 'local_accessibilityreview';
        $needstext = in_array($checkid, ['iframe_title_missing', 'iframe_title_generic', 'object_title_missing'], true)
            || (str_starts_with($checkid, 'img_alt_') && empty($data['decorative']));
        if ($needstext) {
            $text = trim((string) ($data['text'] ?? ''));
            if ($text === '') {
                $errors['text'] = get_string('fix_error_textrequired', $p);
            } else if (\core_text::strlen($text) > fix_engine::MAX_TEXT) {
                $errors['text'] = get_string('fix_error_texttoolong', $p);
            }
        }
        if ($checkid === 'link_new_window' && ($data['mode'] ?? '') === 'label' && trim((string) ($data['label'] ?? '')) === '') {
            $errors['label'] = get_string('fix_error_textrequired', $p);
        }
        return $errors;
    }
}
