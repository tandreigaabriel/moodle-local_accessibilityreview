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
 * Fix one finding in place (with preview), or undo a fix.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- require_login() is called by local\access::require_course_view() or require_site_view().
require_once(__DIR__ . '/../../config.php');

use core\output\notification;
use local_accessibilityreview\form\fix_form;
use local_accessibilityreview\local\access;
use local_accessibilityreview\local\finding_manager;
use local_accessibilityreview\local\fix\fix_exception;
use local_accessibilityreview\local\fixer;

$p = 'local_accessibilityreview';
$undoid = optional_param('undo', 0, PARAM_INT);

// Undo a fix (POST with sesskey from the Fixes tab).
if ($undoid) {
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest');
    }
    $fix = $DB->get_record(fixer::TABLE, ['id' => $undoid], '*', MUST_EXIST);
    [$course, $context] = access::require_course_view((int) $fix->courseid);
    require_sesskey();
    require_capability('local/accessibilityreview:fix', $context);
    $back = new moodle_url('/local/accessibilityreview/index.php', ['id' => $course->id, 'tab' => 'fixes']);
    try {
        fixer::undo($fix, (int) $USER->id);
    } catch (fix_exception $e) {
        redirect($back, get_string('fix_error_' . $e->reason, $p), null, notification::NOTIFY_ERROR);
    }
    redirect($back, get_string('fixundone', $p), null, notification::NOTIFY_SUCCESS);
}

$id = required_param('id', PARAM_INT);
$finding = $DB->get_record(finding_manager::TABLE, ['id' => $id], '*', MUST_EXIST);
[$course, $context] = access::require_course_view((int) $finding->courseid);
require_capability('local/accessibilityreview:fix', $context);

$url = new moodle_url('/local/accessibilityreview/fix.php', ['id' => $finding->id]);
$findingurl = new moodle_url('/local/accessibilityreview/finding.php', ['id' => $finding->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('fixtitle', $p));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(
    get_string('coursereport', $p),
    new moodle_url('/local/accessibilityreview/index.php', ['id' => $course->id, 'tab' => 'findings'])
);
$PAGE->navbar->add(get_string('findingdetails', $p), $findingurl);
$PAGE->navbar->add(get_string('fixtitle', $p));

/** @var \local_accessibilityreview\output\renderer $output */
$output = $PAGE->get_renderer('local_accessibilityreview');

$reason = fixer::why_not($finding);
$preview = null;
$issue = null;
$error = null;
if ($reason === null) {
    try {
        $source = fixer::source($finding);
        [$html] = fixer::read($source);
        $issue = fixer::locate($finding, $html, \local_accessibilityreview\local\checks\html_checker::from_config());
    } catch (fix_exception $e) {
        $reason = $e->reason;
    }
}

if ($reason !== null) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('fixtitle', $p));
    echo $OUTPUT->notification(get_string('fix_error_' . $reason, $p), notification::NOTIFY_WARNING);
    echo html_writer::link($findingurl, get_string('backtofinding', $p));
    echo $OUTPUT->footer();
    exit;
}

$details = (array) json_decode((string) $finding->details, true);
$form = new fix_form($url, [
    'checkid' => $finding->checkid,
    'suggestion' => fixer::suggested_title($issue, $html),
    'fromlevel' => (int) ($details['from'] ?? 0),
]);
$form->set_data(['id' => $finding->id]);

if ($form->is_cancelled()) {
    redirect($findingurl);
}
if ($data = $form->get_data()) {
    $input = (array) $data;
    if (!empty($data->applybutton)) {
        try {
            fixer::apply($finding, $input, (int) $USER->id);
        } catch (fix_exception $e) {
            $error = get_string('fix_error_' . $e->reason, $p);
        }
        if ($error === null) {
            $present = (int) $DB->get_field(finding_manager::TABLE, 'present', ['id' => $finding->id]);
            redirect(
                $findingurl,
                get_string($present ? 'fixapplied_stillpresent' : 'fixapplied_resolved', $p),
                null,
                $present ? notification::NOTIFY_WARNING : notification::NOTIFY_SUCCESS
            );
        }
    } else {
        try {
            $preview = fixer::preview($finding, $input);
        } catch (fix_exception $e) {
            $error = get_string('fix_error_' . $e->reason, $p);
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('fixtitle', $p) . ': ' . get_string('check_' . $finding->checkid, $p));
if ($error !== null) {
    echo $OUTPUT->notification($error, notification::NOTIFY_ERROR);
}
echo html_writer::tag('p', s(get_string('fix_intro', $p)));

$items = [
    get_string('location', $p) => s((string) $finding->itemlabel),
    get_string('explanation', $p) => s(get_string('check_' . $finding->checkid . '_desc', $p)),
    get_string('excerpt', $p) => html_writer::tag(
        'pre',
        s((string) $finding->excerpt),
        ['class' => 'local-accessibilityreview-excerpt-block']
    ),
];
$imageurl = fixer::image_url($finding, $issue);
if ($imageurl !== null) {
    $items[get_string('fix_image', $p)] = html_writer::empty_tag('img', ['src' => $imageurl, 'alt' => '',
        'class' => 'local-accessibilityreview-fiximage']);
}
$items[get_string('guide', $p)] = html_writer::link(new moodle_url(
    '/local/accessibilityreview/guide.php',
    ['check' => $finding->checkid]
), get_string('howtofix', $p));
echo $output->details_list($items);

if ($preview !== null) {
    echo html_writer::tag('h3', get_string('fix_previewheading', $p));
    echo $output->change_preview($preview['old'], $preview['new']);
    echo html_writer::tag('p', s(get_string('fix_previewnote', $p)), ['class' => 'text-muted']);
}

$form->display();
echo $OUTPUT->footer();
