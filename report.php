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
 * Printable evidence report for a course: what was checked, what was found, what was decided.
 *
 * It records the review process. It is not a statement of conformance.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- require_login() is called by local\access::require_course_view() or require_site_view().
require_once(__DIR__ . '/../../config.php');

use local_accessibilityreview\local\access;
use local_accessibilityreview\local\checks\check_registry;
use local_accessibilityreview\local\content\provider_registry;
use local_accessibilityreview\local\finding_manager;
use local_accessibilityreview\local\fixer;
use local_accessibilityreview\local\history;
use local_accessibilityreview\local\links\link_manager;
use local_accessibilityreview\local\progress;
use local_accessibilityreview\local\resource_manager;
use local_accessibilityreview\local\scanner;

\local_accessibilityreview\local\license::require_valid();

$p = 'local_accessibilityreview';
$courseid = required_param('id', PARAM_INT);
[$course, $context] = access::require_course_view($courseid);

$url = new moodle_url('/local/accessibilityreview/report.php', ['id' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('evidencereport', $p) . ': ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('coursereport', $p), new moodle_url('/local/accessibilityreview/index.php', ['id' => $courseid]));
$PAGE->navbar->add(get_string('evidencereport', $p));

/** @var \local_accessibilityreview\output\renderer $output */
$output = $PAGE->get_renderer('local_accessibilityreview');

echo $OUTPUT->header();
echo html_writer::start_div('local-accessibilityreview-evidence');
echo $OUTPUT->heading(get_string('evidencereport', $p));
echo $output->details_list([
    get_string('course') => format_string($course->fullname) . ' (' . format_string($course->shortname) . ')',
    get_string('evidence_generated', $p) => s(userdate(time())) . ', ' . s(fullname($USER)),
    get_string('evidence_tool', $p) => s(get_string('pluginname', $p) . ' ' .
        (core_plugin_manager::instance()->get_plugin_info($p)->release ?? '')),
]);
echo html_writer::tag('p', s(get_string('evidence_notconformance', $p)), ['class' => 'alert alert-warning']);

// Scope.
echo html_writer::tag('h3', get_string('evidence_scope', $p));
$areas = array_map(
    fn($provider) => html_writer::tag('li', s($provider->get_display_name())),
    provider_registry::content_providers()
);
echo html_writer::tag('p', s(get_string('evidence_scope_areas', $p)));
echo html_writer::tag('ul', implode('', $areas));
echo html_writer::tag('p', s(get_string('evidence_scope_checks', $p, count(check_registry::all()))));
echo html_writer::tag('p', s(get_string('evidence_scope_limits', $p)));

// Scans.
echo html_writer::tag('h3', get_string('evidence_scans', $p));
$scans = $DB->get_records(
    scanner::TABLE,
    ['courseid' => $courseid, 'status' => scanner::STATUS_COMPLETE],
    'timefinished DESC',
    'id, timefinished, itemsdone, itemsfailed, findingscount',
    0,
    10
);
if ($scans) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('date', $p), get_string('itemsscanned', $p), get_string('itemsfailed', $p),
        get_string('openfindings', $p)];
    foreach ($scans as $scan) {
        $table->data[] = [s($output->time((int) $scan->timefinished)), (int) $scan->itemsdone, (int) $scan->itemsfailed,
            (int) $scan->findingscount];
    }
    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', get_string('evidence_noscans', $p));
}

// Progress.
echo $output->progress_table(progress::history($courseid, 12));

// Findings by check.
echo html_writer::tag('h3', get_string('evidence_findings', $p));
$bycheck = [];
$rs = $DB->get_recordset_sql(
    "SELECT checkid, state, COUNT(1) AS total
                                FROM {" . finding_manager::TABLE . "}
                               WHERE courseid = :courseid
                                 AND (present = 1 OR state = :resolved)
                            GROUP BY checkid, state",
    ['courseid' => $courseid, 'resolved' => finding_manager::STATE_RESOLVED]
);
foreach ($rs as $row) {
    $bycheck[$row->checkid][$row->state] = (int) $row->total;
}
$rs->close();
if ($bycheck) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('check', $p), get_string('severity', $p), get_string('findingtype', $p),
        get_string('state_open', $p), get_string('state_inprogress', $p), get_string('state_resolved', $p),
        get_string('state_exception', $p), get_string('wcag', $p)];
    foreach (check_registry::all() as $checkid => $def) {
        if (!isset($bycheck[$checkid])) {
            continue;
        }
        $c = $bycheck[$checkid];
        $table->data[] = [
            s(get_string('check_' . $checkid, $p)),
            $output->severity($def['severity']),
            s(get_string($def['manual'] ? 'type_manual' : 'type_automated', $p)),
            (int) ($c[finding_manager::STATE_OPEN] ?? 0),
            (int) ($c[finding_manager::STATE_INPROGRESS] ?? 0),
            (int) ($c[finding_manager::STATE_RESOLVED] ?? 0),
            (int) ($c[finding_manager::STATE_EXCEPTION] ?? 0),
            $output->wcag_details($checkid),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive');
} else {
    echo html_writer::tag('p', get_string('nofindings', $p));
}

// Accepted exceptions with their reasons.
echo html_writer::tag('h3', get_string('evidence_exceptions', $p));
$exceptions = $DB->get_records(finding_manager::TABLE, ['courseid' => $courseid, 'state' => finding_manager::STATE_EXCEPTION,
    'present' => 1], 'itemlabel, id', 'id, checkid, itemlabel, reviewerid, timereviewed');
if ($exceptions) {
    [$insql, $inparams] = $DB->get_in_or_equal(array_keys($exceptions), SQL_PARAMS_NAMED);
    $notes = [];
    $rs = $DB->get_recordset_select(
        history::TABLE,
        "itemtype = 'finding' AND itemid $insql AND note IS NOT NULL",
        $inparams,
        'timecreated ASC, id ASC',
        'id, itemid, note'
    );
    foreach ($rs as $h) {
        $notes[$h->itemid] = $h->note; // Keeps the latest note.
    }
    $rs->close();
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('location', $p), get_string('check', $p), get_string('lastreviewed', $p),
        get_string('notes', $p)];
    foreach ($exceptions as $f) {
        $table->data[] = [s($f->itemlabel), s(get_string('check_' . $f->checkid, $p)),
            s($output->username((int) $f->reviewerid) . ', ' . $output->time((int) $f->timereviewed)),
            s((string) ($notes[$f->id] ?? ''))];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive');
} else {
    echo html_writer::tag('p', get_string('evidence_noexceptions', $p));
}

// Fixes applied from the plugin.
$fixcount = $DB->count_records(fixer::TABLE, ['courseid' => $courseid, 'status' => 'applied']);
if ($fixcount) {
    echo html_writer::tag('h3', get_string('tab_fixes', $p));
    echo html_writer::tag('p', s(get_string('evidence_fixes', $p, $fixcount)));
}

// Manual review of documents, video and SCORM.
echo html_writer::tag('h3', get_string('inventory', $p));
$counts = [];
$rs = $DB->get_recordset_sql("SELECT restype, reviewstatus, COUNT(1) AS total
                                FROM {" . resource_manager::TABLE . "}
                               WHERE courseid = :courseid AND present = 1
                            GROUP BY restype, reviewstatus", ['courseid' => $courseid]);
foreach ($rs as $row) {
    $counts[$row->restype][$row->reviewstatus] = (int) $row->total;
}
$rs->close();
$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = array_merge([get_string('type', $p)], array_map(
    fn($s) => get_string('review_' . $s, $p),
    resource_manager::REVIEW_STATUSES
));
foreach (resource_manager::types() as $restype) {
    $row = [s(get_string('tab_' . $restype, $p))];
    foreach (resource_manager::REVIEW_STATUSES as $status) {
        $row[] = (int) ($counts[$restype][$status] ?? 0);
    }
    $table->data[] = $row;
}
echo html_writer::div(html_writer::table($table), 'table-responsive');

// Links.
echo html_writer::tag('h3', get_string('tab_links', $p));
$linkcounts = $DB->get_records_sql_menu("SELECT l.status, COUNT(DISTINCT l.id)
                                           FROM {" . link_manager::TABLE . "} l
                                           JOIN {" . link_manager::USE_TABLE . "} u ON u.linkid = l.id
                                          WHERE u.courseid = :courseid
                                       GROUP BY l.status", ['courseid' => $courseid]);
echo $output->count_table(get_string('linksummary', $p), $output->link_status_rows($linkcounts));

echo html_writer::tag('p', s(get_string('evidence_footer', $p)), ['class' => 'small text-muted mt-4']);
echo html_writer::end_div();
echo $OUTPUT->footer();
