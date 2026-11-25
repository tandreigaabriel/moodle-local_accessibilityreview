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
 * Guides: how to fix each kind of issue, and how students can adapt course content.
 *
 * The guides contain only general advice, so any logged-in user may read them.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_accessibilityreview\local\checks\check_registry;

\local_accessibilityreview\local\license::require_valid();

$check = optional_param('check', '', PARAM_ALPHANUMEXT);
$page = optional_param('page', '', PARAM_ALPHA);

require_login(null, false);
if ($check !== '' && !check_registry::exists($check)) {
    $check = '';
}

$params = array_filter(['check' => $check, 'page' => $page]);
$url = new moodle_url('/local/accessibilityreview/guide.php', $params);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

$p = 'local_accessibilityreview';
$indexurl = new moodle_url('/local/accessibilityreview/guide.php');
$PAGE->navbar->add(get_string('guides', $p), $indexurl);
if ($check !== '') {
    $title = get_string('check_' . $check, $p);
} else if ($page === 'students') {
    $title = get_string('studentguide', $p);
} else if ($page === 'manual') {
    $title = get_string('manual', $p);
} else {
    $title = get_string('guides', $p);
}
if ($check !== '' || $page === 'students' || $page === 'manual') {
    $PAGE->navbar->add($title);
}
$PAGE->set_title($title);
$PAGE->set_heading(get_string('guides', $p));

/** @var \local_accessibilityreview\output\renderer $output */
$output = $PAGE->get_renderer('local_accessibilityreview');

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
if ($check !== '') {
    echo $output->check_guide($check);
    echo html_writer::tag('p', html_writer::link($indexurl, get_string('guide_all', $p)), ['class' => 'mt-4']);
} else if ($page === 'students') {
    echo $output->student_guide();
} else if ($page === 'manual') {
    echo $output->user_manual();
} else {
    echo $output->guide_index();
}
echo $OUTPUT->footer();
