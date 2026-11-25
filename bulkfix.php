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
 * Bulk fix: one page listing every open finding of one check in a course, each with its own input.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- require_login() is called by local\access::require_course_view() or require_site_view().
require_once(__DIR__ . '/../../config.php');

use core\output\notification;
use local_accessibilityreview\local\access;
use local_accessibilityreview\local\finding_manager;
use local_accessibilityreview\local\fix\fix_engine;
use local_accessibilityreview\local\fix\fix_exception;
use local_accessibilityreview\local\fixer;

\local_accessibilityreview\local\license::require_valid();

$p = 'local_accessibilityreview';
$courseid = required_param('id', PARAM_INT);
$checkid = optional_param('check', '', PARAM_ALPHANUMEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 25;

[$course, $context] = access::require_course_view($courseid);
require_capability('local/accessibilityreview:fix', $context);
if (!fixer::enabled()) {
    throw new moodle_exception('fix_error_disabled', $p);
}

$url = new moodle_url('/local/accessibilityreview/bulkfix.php', ['id' => $courseid, 'check' => $checkid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('bulkfix', $p));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('coursereport', $p), new moodle_url(
    '/local/accessibilityreview/index.php',
    ['id' => $courseid, 'tab' => 'fixes']
));
$PAGE->navbar->add(get_string('bulkfix', $p));

/** @var \local_accessibilityreview\output\renderer $output */
$output = $PAGE->get_renderer('local_accessibilityreview');

$openstates = [finding_manager::STATE_OPEN, finding_manager::STATE_INPROGRESS];
[$statesql, $stateparams] = $DB->get_in_or_equal($openstates, SQL_PARAMS_NAMED, 'st');

// Choose a check: list bulk-fixable checks with their open counts.
if (!in_array($checkid, fixer::BULK_CHECKS, true)) {
    [$checksql, $checkparams] = $DB->get_in_or_equal(fixer::BULK_CHECKS, SQL_PARAMS_NAMED, 'ck');
    $counts = $DB->get_records_sql_menu("SELECT checkid, COUNT(1)
                                           FROM {" . finding_manager::TABLE . "}
                                          WHERE courseid = :courseid AND present = 1 AND state $statesql AND checkid $checksql
                                       GROUP BY checkid", ['courseid' => $courseid] + $stateparams + $checkparams);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('bulkfix', $p));
    echo html_writer::tag('p', s(get_string('bulkfix_intro', $p)));
    $items = '';
    foreach (fixer::BULK_CHECKS as $id) {
        $n = (int) ($counts[$id] ?? 0);
        $label = get_string('check_' . $id, $p) . ' (' . $n . ')';
        $items .= html_writer::tag('li', $n ? html_writer::link(new moodle_url($url, ['check' => $id]), s($label)) : s($label));
    }
    echo html_writer::tag('ul', $items);
    echo $OUTPUT->footer();
    exit;
}

$where = "courseid = :courseid AND checkid = :checkid AND present = 1 AND state $statesql";
$params = ['courseid' => $courseid, 'checkid' => $checkid] + $stateparams;
$results = [];

// Apply.
if (optional_param('apply', '', PARAM_RAW) !== '') {
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest');
    }
    require_sesskey();
    $selected = optional_param_array('items', [], PARAM_INT);
    $texts = optional_param_array('text', [], PARAM_TEXT);
    $decorative = optional_param_array('decorative', [], PARAM_BOOL);
    $mode = optional_param('mode', 'label', PARAM_ALPHA);
    $label = optional_param('label', get_string('fix_label_default', $p), PARAM_TEXT);
    if ($selected) {
        [$idsql, $idparams] = $DB->get_in_or_equal(array_values($selected), SQL_PARAMS_NAMED, 'fid');
        $findings = $DB->get_records_select(finding_manager::TABLE, "$where AND id $idsql", $params + $idparams);
        $done = 0;
        $failed = 0;
        foreach ($findings as $finding) {
            $data = ['text' => trim((string) ($texts[$finding->id] ?? '')),
                'decorative' => !empty($decorative[$finding->id]) ? 1 : 0, 'mode' => $mode, 'label' => $label];
            if (str_starts_with($checkid, 'img_alt_') && !$data['decorative'] && $data['text'] === '') {
                continue; // Nothing entered for this image: leave it.
            }
            try {
                fixer::apply($finding, $data, (int) $USER->id);
                $done++;
            } catch (fix_exception $e) {
                $failed++;
                $results[] = s($finding->itemlabel) . ': ' . s(get_string('fix_error_' . $e->reason, $p));
            }
        }
        $message = get_string('bulkfix_done', $p, (object) ['done' => $done, 'failed' => $failed]);
        if (!$failed) {
            redirect($url, $message, null, notification::NOTIFY_SUCCESS);
        }
        array_unshift($results, s($message));
    }
}

$total = $DB->count_records_select(finding_manager::TABLE, $where, $params);
$findings = $DB->get_records_select(finding_manager::TABLE, $where, $params, 'itemlabel, id', '*', $page * $perpage, $perpage);
$prepared = fixer::prepare($findings);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkfix', $p) . ': ' . get_string('check_' . $checkid, $p));
if ($results) {
    echo $OUTPUT->notification(implode(html_writer::empty_tag('br'), $results), notification::NOTIFY_WARNING);
}
echo html_writer::tag('p', s(get_string('bulkfix_' . (str_starts_with($checkid, 'img_alt_') ? 'images' :
    (in_array($checkid, ['link_new_window', 'media_autoplay'], true) ? 'select' : 'titles')) . '_intro', $p)));
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/accessibilityreview/guide.php', ['check' => $checkid]),
    get_string('howtofix', $p)
));

if (!$findings) {
    echo html_writer::tag('p', get_string('nofindings', $p));
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'page', 'value' => $page]);

if ($checkid === 'link_new_window') {
    echo html_writer::start_tag('fieldset', ['class' => 'mb-3']);
    echo html_writer::tag('legend', get_string('fix_mode', $p), ['class' => 'h5']);
    foreach (['label', 'sametab'] as $i => $value) {
        $attrs = ['type' => 'radio', 'name' => 'mode', 'value' => $value, 'id' => 'mode-' . $value];
        if ($i === 0) {
            $attrs['checked'] = 'checked';
        }
        echo html_writer::div(html_writer::empty_tag('input', $attrs) . ' ' .
            html_writer::label(get_string('fix_mode_' . $value, $p), 'mode-' . $value));
    }
    echo html_writer::label(get_string('fix_label', $p), 'bulk-label', true, ['class' => 'mt-2']);
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'label', 'id' => 'bulk-label', 'size' => 40,
        'maxlength' => 60, 'value' => get_string('fix_label_default', $p), 'class' => 'form-control w-auto']);
    echo html_writer::end_tag('fieldset');
}

$table = new html_table();
$table->attributes['class'] = 'generaltable local-accessibilityreview-bulkfix';
$table->caption = get_string('bulkfix', $p);
$table->captionhide = true;
$isimage = str_starts_with($checkid, 'img_alt_');
$needstext = $isimage || in_array($checkid, ['iframe_title_missing', 'iframe_title_generic', 'object_title_missing'], true);
$table->head = [get_string('include', $p), get_string('location', $p), get_string('excerpt', $p)];
if ($needstext) {
    $table->head[] = get_string($isimage ? 'fix_alttext' : 'fix_title', $p);
}
foreach ($findings as $finding) {
    $entry = $prepared[$finding->id];
    $fid = (int) $finding->id;
    $location = s((string) $finding->itemlabel);
    $excerpt = html_writer::tag(
        'code',
        s(core_text::substr((string) $finding->excerpt, 0, 160)),
        ['class' => 'local-accessibilityreview-excerpt']
    );
    if ($entry['imageurl']) {
        $excerpt = html_writer::empty_tag('img', ['src' => $entry['imageurl'], 'alt' => '',
            'class' => 'local-accessibilityreview-fiximage d-block mb-1']) . $excerpt;
    }
    if ($entry['error'] !== null) {
        $row = ['', $location, $excerpt . html_writer::div(
            s(get_string('fix_error_' . $entry['error'], $p)),
            'small text-danger'
        )];
        if ($needstext) {
            $row[] = '';
        }
        $table->data[] = $row;
        continue;
    }
    $include = html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'items[]', 'value' => $fid,
        'id' => 'item-' . $fid, 'checked' => 'checked']) .
        html_writer::label(
            get_string('includeitem', $p, $finding->itemlabel),
            'item-' . $fid,
            false,
            ['class' => 'sr-only visually-hidden']
        );
    $row = [$include, $location, $excerpt];
    if ($needstext) {
        $input = html_writer::label(
            get_string($isimage ? 'fix_alttextfor' : 'fix_titlefor', $p, $finding->itemlabel),
            'text-' . $fid,
            false,
            ['class' => 'sr-only visually-hidden']
        ) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'text[' . $fid . ']', 'id' => 'text-' . $fid,
                'maxlength' => fix_engine::MAX_TEXT, 'size' => 40, 'class' => 'form-control',
                'value' => $entry['suggestion']]);
        if ($isimage) {
            $input .= html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'decorative[' . $fid . ']',
                'value' => 1, 'id' => 'decorative-' . $fid]) . ' ' .
                html_writer::label(get_string('fix_decorative_label', $p), 'decorative-' . $fid), 'small mt-1');
        }
        $row[] = $input;
    }
    $table->data[] = $row;
}
echo html_writer::div(html_writer::table($table), 'table-responsive');
echo html_writer::tag('p', s(get_string('bulkfix_note', $p)), ['class' => 'text-muted']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'apply', 'value' => get_string('bulkfix_apply', $p),
    'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
echo $OUTPUT->footer();
