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
 * Site accessibility report for authorised administrators.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_accessibilityreview\local\course_status;
use local_accessibilityreview\local\finding_manager;
use local_accessibilityreview\local\findings_filter;
use local_accessibilityreview\local\links\link_manager;
use local_accessibilityreview\local\resource_manager;

\local_accessibilityreview\local\license::require_valid();

$tab = optional_param('tab', 'courses', PARAM_ALPHA);
$tab = in_array($tab, ['courses', 'findings'], true) ? $tab : 'courses';
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = $tab === 'courses' ? 25 : 50;

// Checks login and local/accessibilityreview:view at system context.
admin_externalpage_setup('local_accessibilityreview_site', '', ['tab' => $tab, 'categoryid' => $categoryid]);
$syscontext = context_system::instance();
require_capability('local/accessibilityreview:view', $syscontext);

$baseurl = new moodle_url('/local/accessibilityreview/site.php', ['tab' => $tab]);
/** @var \local_accessibilityreview\output\renderer $output */
$output = $PAGE->get_renderer('local_accessibilityreview');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('sitereport', 'local_accessibilityreview'));
echo $output->disclaimer();
echo $OUTPUT->tabtree([
    new tabobject(
        'courses',
        new moodle_url('/local/accessibilityreview/site.php', ['tab' => 'courses']),
        get_string('tab_courses', 'local_accessibilityreview')
    ),
    new tabobject(
        'findings',
        new moodle_url('/local/accessibilityreview/site.php', ['tab' => 'findings']),
        get_string('tab_findings', 'local_accessibilityreview')
    ),
], $tab);

if ($tab === 'findings') {
    $filter = findings_filter::from_request(0);
    echo $output->findings_filter_form($baseurl, $filter, true);
    $total = $filter->count();
    $pageurl = new moodle_url($baseurl, $filter->to_params());
    echo html_writer::tag('p', get_string('findingscount', 'local_accessibilityreview', $total), ['aria-live' => 'polite']);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
    echo $output->findings_table($filter->get($page * $perpage, $perpage), true);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
    $exporturl = new moodle_url('/local/accessibilityreview/export.php', ['site' => 1] + $filter->to_params());
    echo html_writer::div(html_writer::link(
        $exporturl,
        get_string('exportcsv', 'local_accessibilityreview'),
        ['class' => 'btn btn-secondary']
    ), 'mt-3');
    echo $OUTPUT->footer();
    exit;
}

// Courses tab: category filter.
$categories = [0 => get_string('any', 'local_accessibilityreview')] + core_course_category::make_categories_list();
$select = new single_select(new moodle_url($baseurl), 'categoryid', $categories, $categoryid, null);
$select->set_label(get_string('category'));
echo $OUTPUT->render($select);

$params = ['siteid' => SITEID];
$where = 'c.id <> :siteid';
if ($categoryid) {
    $category = core_course_category::get($categoryid, IGNORE_MISSING, true);
    $ids = $category ? array_merge([$category->id], $category->get_all_children_ids()) : [-1];
    [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
    $where .= " AND c.category $insql";
    $params += $inparams;
}
$total = $DB->count_records_sql("SELECT COUNT(1) FROM {course} c WHERE $where", $params);
$courses = $DB->get_records_sql("SELECT c.id, c.fullname, c.shortname, c.category FROM {course} c WHERE $where
                                  ORDER BY c.fullname, c.id", $params, $page * $perpage, $perpage);

$severitycounts = [];
$linkcounts = [];
$unreviewed = [];
if ($courses) {
    [$insql, $inparams] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
    $rs = $DB->get_recordset_sql("SELECT courseid, severity, COUNT(1) AS total
                                    FROM {" . finding_manager::TABLE . "}
                                   WHERE courseid $insql AND present = 1 AND state IN ('open', 'inprogress')
                                GROUP BY courseid, severity", $inparams);
    foreach ($rs as $row) {
        $severitycounts[$row->courseid][$row->severity] = (int) $row->total;
    }
    $rs->close();
    $linkcounts = $DB->get_records_sql_menu("SELECT u.courseid, COUNT(DISTINCT l.id)
                                               FROM {" . link_manager::USE_TABLE . "} u
                                               JOIN {" . link_manager::TABLE . "} l ON l.id = u.linkid
                                              WHERE u.courseid $insql AND l.status = 'unavailable'
                                           GROUP BY u.courseid", $inparams);
    $unreviewed = $DB->get_records_sql_menu("SELECT courseid, COUNT(1)
                                               FROM {" . resource_manager::TABLE . "}
                                              WHERE courseid $insql AND present = 1
                                                AND reviewstatus IN ('notreviewed', 'inprogress', 'issuesfound')
                                           GROUP BY courseid", $inparams);
}

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->caption = get_string('tab_courses', 'local_accessibilityreview');
$table->captionhide = true;
$table->head = [get_string('course'), get_string('status', 'local_accessibilityreview'),
    get_string('lastcompletedscan', 'local_accessibilityreview'), get_string('severity_error', 'local_accessibilityreview'),
    get_string('severity_warning', 'local_accessibilityreview'), get_string('severity_notice', 'local_accessibilityreview'),
    get_string('linkstatus_unavailable', 'local_accessibilityreview'), get_string('inventoryopen', 'local_accessibilityreview')];
foreach ($courses as $course) {
    $status = course_status::get((int) $course->id);
    $table->data[] = [
        html_writer::link(
            new moodle_url('/local/accessibilityreview/index.php', ['id' => $course->id]),
            format_string($course->fullname)
        ),
        s(get_string('status_' . $status->status, 'local_accessibilityreview')),
        s($output->time($status->lastcomplete ? (int) $status->lastcomplete->timefinished : 0)),
        (int) ($severitycounts[$course->id]['error'] ?? 0),
        (int) ($severitycounts[$course->id]['warning'] ?? 0),
        (int) ($severitycounts[$course->id]['notice'] ?? 0),
        (int) ($linkcounts[$course->id] ?? 0),
        (int) ($unreviewed[$course->id] ?? 0),
    ];
}
$pageurl = new moodle_url($baseurl, ['categoryid' => $categoryid]);
echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
echo html_writer::div(html_writer::table($table), 'table-responsive');
echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);

if (
    has_capability('local/accessibilityreview:manage', $syscontext)
        && has_capability('local/accessibilityreview:scan', $syscontext)
) {
    $scanurl = new moodle_url('/local/accessibilityreview/scan.php', ['categoryid' => $categoryid, 'sesskey' => sesskey()]);
    $form = html_writer::input_hidden_params($scanurl);
    $form .= html_writer::tag(
        'button',
        get_string($categoryid ? 'queuecategory' : 'queueall', 'local_accessibilityreview'),
        ['type' => 'submit', 'class' => 'btn btn-secondary']
    );
    echo html_writer::tag('form', $form, ['method' => 'post', 'action' => $scanurl->out_omit_querystring(),
        'class' => 'mt-3']);
    echo html_writer::tag('p', get_string('queuebulk_help', 'local_accessibilityreview'), ['class' => 'text-muted small']);
}

echo $OUTPUT->footer();
