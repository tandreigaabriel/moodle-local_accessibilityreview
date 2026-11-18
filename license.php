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
 * Licence activation page.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_accessibilityreview\local\license;

require_login();
require_capability('moodle/site:config', context_system::instance());
$url = new moodle_url('/local/accessibilityreview/license.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('licenseheading', 'local_accessibilityreview'));
$PAGE->set_heading(get_string('licenseheading', 'local_accessibilityreview'));
if (data_submitted()) {
    require_sesskey();
    $key = required_param('key', PARAM_RAW_TRIMMED);
    $ok = license::activate($key);
    redirect(
        $url,
        get_string($ok ? 'licenseactivated' : 'licensefailed', 'local_accessibilityreview'),
        null,
        $ok ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR
    );
}
$p = 'local_accessibilityreview';
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('licenseheading', $p));
echo html_writer::tag('p', get_string('license_' . license::get_status(), $p));
$info = license::get_info();
if ($info && license::get_status() !== 'unlicensed') {
    echo html_writer::tag('p', get_string('licenseplan', $p, s($info->plan)));
    if (!empty($info->support_until)) {
        echo html_writer::tag('p', get_string('licensesupport', $p, userdate($info->support_until)));
    }
}
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('label', get_string('licensekey', $p), ['for' => 'licensekey']);
echo html_writer::empty_tag('input', ['type' => 'password', 'name' => 'key', 'id' => 'licensekey', 'required' => 'required']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('licenseactivate', $p)]);
echo html_writer::end_tag('form');
echo html_writer::tag('p', html_writer::link('https://tandreig.com/plugins/accessibilityreview', get_string('licensebuy', $p)));
echo html_writer::tag('p', html_writer::link('https://tandreig.com/request-key', get_string('licensetrial', $p)));
echo $OUTPUT->footer();
