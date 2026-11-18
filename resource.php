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
 * PDF / video / SCORM inventory item with manual review checklist.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- require_login() is called by local\access::require_course_view() or require_site_view().
require_once(__DIR__ . '/../../config.php');

use local_accessibilityreview\form\checklist_form;
use local_accessibilityreview\local\access;
use local_accessibilityreview\local\history;
use local_accessibilityreview\local\links\link_manager;
use local_accessibilityreview\local\resource_manager;

$id = required_param('id', PARAM_INT);
$resource = $DB->get_record_sql("SELECT r.*, l.status AS linkstatus, l.metadata AS linkmetadata, l.reason AS linkreason
                                   FROM {" . resource_manager::TABLE . "} r
                              LEFT JOIN {" . link_manager::TABLE . "} l ON l.id = r.linkid
                                  WHERE r.id = :id", ['id' => $id], MUST_EXIST);
[$course, $context] = access::require_course_view((int) $resource->courseid);
$canreview = has_capability('local/accessibilityreview:review', $context);

$url = new moodle_url('/local/accessibilityreview/resource.php', ['id' => $resource->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('resourcereview', 'local_accessibilityreview'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(
    get_string('coursereport', 'local_accessibilityreview'),
    new moodle_url('/local/accessibilityreview/index.php', ['id' => $course->id, 'tab' => $resource->restype])
);
$PAGE->navbar->add(get_string('resourcereview', 'local_accessibilityreview'));

$form = null;
if ($canreview) {
    $form = new checklist_form($url, ['restype' => $resource->restype]);
    $defaults = ['id' => $resource->id, 'reviewstatus' => $resource->reviewstatus,
        'reviewnotes' => (string) $resource->reviewnotes];
    foreach ((array) json_decode((string) $resource->checklist, true) as $key => $value) {
        $defaults['q_' . $key] = $value;
    }
    $form->set_data($defaults);
    if ($data = $form->get_data()) {
        require_capability('local/accessibilityreview:review', $context);
        $answers = [];
        foreach (resource_manager::checklist($resource->restype) as $key) {
            $answers[$key] = $data->{'q_' . $key} ?? 'notchecked';
        }
        resource_manager::save_review(
            $resource,
            $answers,
            $data->reviewstatus,
            trim((string) $data->reviewnotes),
            (int) $USER->id
        );
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

echo $OUTPUT->header();
echo $OUTPUT->heading(s($output->resource_name($resource)));
echo $output->disclaimer();
echo $OUTPUT->notification(get_string('limitation_' . $resource->restype, 'local_accessibilityreview'), 'warning', false);

$open = '';
if ($resource->source === 'file' && $resource->fileid) {
    $file = get_file_storage()->get_file_by_id($resource->fileid);
    if ($file) {
        $fileurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            false
        );
        $open = html_writer::link($fileurl, get_string('openfile', 'local_accessibilityreview'));
    }
} else if (!empty($resource->url)) {
    $open = html_writer::link(
        $resource->url,
        s($output->display_url($resource->url)),
        ['rel' => 'noopener noreferrer', 'target' => '_blank', 'title' => get_string('opensnewwindow', 'local_accessibilityreview')]
    );
}
$source = '';
if ($resource->cmid) {
    $cmcontext = context_module::instance($resource->cmid, IGNORE_MISSING);
    if ($cmcontext && has_capability('moodle/course:manageactivities', $cmcontext)) {
        $source = html_writer::link(
            new moodle_url('/course/modedit.php', ['update' => $resource->cmid]),
            get_string('editsource', 'local_accessibilityreview')
        );
    }
}

$items = [
    get_string('name', 'local_accessibilityreview') => s($output->resource_name($resource)),
    get_string('location', 'local_accessibilityreview') => s($resource->location),
    get_string('source', 'local_accessibilityreview') => s(get_string('source_' . $resource->source, 'local_accessibilityreview')),
    get_string('size', 'local_accessibilityreview') => $resource->filesize ? display_size((int) $resource->filesize) : '',
    get_string('automatedchecks', 'local_accessibilityreview') => $output->automated_summary($resource),
    get_string('open', 'local_accessibilityreview') => $open . ($source ? ' | ' . $source : ''),
    get_string('firstseen', 'local_accessibilityreview') => s($output->time((int) $resource->timefirstseen)),
    get_string('reviewstatus', 'local_accessibilityreview') => s(get_string(
        'review_' . $resource->reviewstatus,
        'local_accessibilityreview'
    )),
];
if ($resource->reviewerid) {
    $items[get_string('lastreviewed', 'local_accessibilityreview')] = s($output->username((int) $resource->reviewerid) .
        ', ' . $output->time((int) $resource->timereviewed));
}
if (!$canreview && trim((string) $resource->reviewnotes) !== '') {
    $items[get_string('notes', 'local_accessibilityreview')] = nl2br(s($resource->reviewnotes));
}
echo $output->details_list($items);

if ($form) {
    echo html_writer::tag('h3', get_string('checklist', 'local_accessibilityreview'));
    echo html_writer::tag('p', get_string('checklist_intro', 'local_accessibilityreview'));
    $form->display();
}

echo html_writer::tag('h3', get_string('history', 'local_accessibilityreview'));
echo $output->history_table(history::get('resource', (int) $resource->id));

echo $OUTPUT->footer();
