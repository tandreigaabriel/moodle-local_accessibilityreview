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
 * Finding details and manual review.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- require_login() is called by local\access::require_course_view() or require_site_view().
require_once(__DIR__ . '/../../config.php');

use local_accessibilityreview\form\review_form;
use local_accessibilityreview\local\access;
use local_accessibilityreview\local\checks\check_registry;
use local_accessibilityreview\local\finding_manager;
use local_accessibilityreview\local\history;

\local_accessibilityreview\local\license::require_valid();

$id = required_param('id', PARAM_INT);
$finding = $DB->get_record(finding_manager::TABLE, ['id' => $id], '*', MUST_EXIST);
[$course, $context] = access::require_course_view((int) $finding->courseid);
if ($finding->provider === 'quiz_question') {
    // The excerpt contains question text.
    require_capability('moodle/question:viewall', $context);
}
$canreview = has_capability('local/accessibilityreview:review', $context);

$url = new moodle_url('/local/accessibilityreview/finding.php', ['id' => $finding->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('findingdetails', 'local_accessibilityreview'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(
    get_string('coursereport', 'local_accessibilityreview'),
    new moodle_url('/local/accessibilityreview/index.php', ['id' => $course->id, 'tab' => 'findings'])
);
$PAGE->navbar->add(get_string('findingdetails', 'local_accessibilityreview'));

$form = null;
if ($canreview) {
    $form = new review_form($url);
    $form->set_data(['id' => $finding->id, 'state' => $finding->state, 'note' => '']);
    if ($data = $form->get_data()) {
        require_capability('local/accessibilityreview:review', $context);
        finding_manager::set_state($finding, $data->state, (string) $data->note, (int) $USER->id);
        redirect(
            $url,
            get_string('reviewsaved', 'local_accessibilityreview'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

/** @var \local_accessibilityreview\output\renderer $output */
$output = $PAGE->get_renderer('local_accessibilityreview');
$def = check_registry::get($finding->checkid);
$details = (array) json_decode((string) $finding->details, true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('check_' . $finding->checkid, 'local_accessibilityreview'));
echo $output->disclaimer();

$extra = '';
if (isset($details['ratio'])) {
    $extra = get_string('contrastdetails', 'local_accessibilityreview', (object) [
        'ratio' => format_float((float) $details['ratio'], 2),
        'fg' => s((string) $details['fg']),
        'bg' => s((string) $details['bg']),
        'required' => format_float((float) ($details['required'] ?? 4.5), 1),
    ]);
} else if (isset($details['from'], $details['to'])) {
    $extra = get_string('headingdetails', 'local_accessibilityreview', (object) ['from' => (int) $details['from'],
        'to' => (int) $details['to']]);
} else if (isset($details['reason'])) {
    $extra = get_string('altreason_' . clean_param($details['reason'], PARAM_ALPHA), 'local_accessibilityreview');
}

$items = [
    get_string('course') => format_string($course->fullname),
    get_string('location', 'local_accessibilityreview') => s((string) $finding->itemlabel),
    get_string('field', 'local_accessibilityreview') => s($output->field_label($finding->field)),
    get_string('category', 'local_accessibilityreview') =>
        s(get_string('category_' . $finding->category, 'local_accessibilityreview')),
    get_string('checkid', 'local_accessibilityreview') => html_writer::tag('code', s($finding->checkid)),
    get_string('severity', 'local_accessibilityreview') => $output->severity($finding->severity),
    get_string('findingtype', 'local_accessibilityreview') => s(get_string($def['manual'] ? 'type_manual_long' :
        'type_automated_long', 'local_accessibilityreview')),
    get_string('wcag', 'local_accessibilityreview') => $output->wcag_details($finding->checkid),
    get_string('explanation', 'local_accessibilityreview') => s(get_string(
        'check_' . $finding->checkid . '_desc',
        'local_accessibilityreview'
    )) . ($extra !== '' ? html_writer::div(s($extra), 'mt-1') : ''),
    get_string('suggestedfix', 'local_accessibilityreview') => s(get_string(
        'check_' . $finding->checkid . '_fix',
        'local_accessibilityreview'
    )) . html_writer::div(html_writer::link(new moodle_url(
        '/local/accessibilityreview/guide.php',
        ['check' => $finding->checkid]
    ), get_string('howtofix', 'local_accessibilityreview')), 'mt-1'),
    get_string('excerpt', 'local_accessibilityreview') => html_writer::tag(
        'pre',
        s((string) $finding->excerpt),
        ['class' => 'local-accessibilityreview-excerpt-block']
    ),
    get_string('reviewstatus', 'local_accessibilityreview') => $output->state($finding->state) .
        (!$finding->present ? html_writer::div(s(get_string('notdetected', 'local_accessibilityreview')), 'small') : ''),
    get_string('firstseen', 'local_accessibilityreview') => s($output->time((int) $finding->timefirstseen)),
    get_string('lastseen', 'local_accessibilityreview') => s($output->time((int) $finding->timelastseen)),
    get_string('source', 'local_accessibilityreview') => $output->source_links($finding),
];
if ($finding->reviewerid) {
    $items[get_string('lastreviewed', 'local_accessibilityreview')] = s($output->username((int) $finding->reviewerid) .
        ', ' . $output->time((int) $finding->timereviewed));
}
echo $output->details_list($items);

if (\local_accessibilityreview\local\fixer::might_fix($finding)) {
    echo html_writer::div($OUTPUT->single_button(
        new moodle_url('/local/accessibilityreview/fix.php', ['id' => $finding->id]),
        get_string('fixthis', 'local_accessibilityreview'),
        'get',
        ['type' => 'primary']
    ), 'my-3');
}

if ($form) {
    echo html_writer::tag('h3', get_string('review', 'local_accessibilityreview'));
    echo html_writer::tag('p', get_string('readonlynotice', 'local_accessibilityreview'), ['class' => 'text-muted']);
    $form->display();
}

echo html_writer::tag('h3', get_string('history', 'local_accessibilityreview'));
echo $output->history_table(history::get('finding', (int) $finding->id));

echo $OUTPUT->footer();
