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
 * Course accessibility report.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- require_login() is called by local\access::require_course_view() or require_site_view().
require_once(__DIR__ . '/../../config.php');

use local_accessibilityreview\local\access;
use local_accessibilityreview\local\course_status;
use local_accessibilityreview\local\finding_manager;
use local_accessibilityreview\local\findings_filter;
use local_accessibilityreview\local\fixer;
use local_accessibilityreview\local\links\link_classifier;
use local_accessibilityreview\local\links\link_manager;
use local_accessibilityreview\local\progress;
use local_accessibilityreview\local\resource_manager;

\local_accessibilityreview\local\license::require_valid();

$courseid = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;
if (!in_array($tab, ['overview', 'findings', 'links', 'pdf', 'video', 'scorm', 'fixes'], true)) {
    $tab = 'overview';
}

[$course, $context] = access::require_course_view($courseid);

$baseurl = new moodle_url('/local/accessibilityreview/index.php', ['id' => $courseid, 'tab' => $tab]);
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('coursereport', 'local_accessibilityreview') . ': ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));

/** @var \local_accessibilityreview\output\renderer $output */
$output = $PAGE->get_renderer('local_accessibilityreview');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursereport', 'local_accessibilityreview'));
echo $output->disclaimer();
$canfix = fixer::enabled() && has_capability('local/accessibilityreview:fix', $context);
if ($tab === 'fixes' && !fixer::enabled()) {
    $tab = 'overview';
}
echo $output->course_tabs($courseid, $tab);

switch ($tab) {
    case 'overview':
        $status = course_status::get($courseid);
        echo $output->status_panel($status, $courseid, access::can_scan_course($courseid));

        $links = [
            html_writer::link(
                new moodle_url('/local/accessibilityreview/report.php', ['id' => $courseid]),
                get_string('evidencereport', 'local_accessibilityreview'),
                ['class' => 'btn btn-secondary mr-2 me-2 mb-2']
            ),
            html_writer::link(
                new moodle_url('/local/accessibilityreview/guide.php'),
                get_string('guides', 'local_accessibilityreview'),
                ['class' => 'btn btn-secondary mr-2 me-2 mb-2']
            ),
            html_writer::link(
                new moodle_url('/local/accessibilityreview/guide.php', ['page' => 'manual']),
                get_string('manual', 'local_accessibilityreview'),
                ['class' => 'btn btn-secondary mr-2 me-2 mb-2']
            ),
        ];
        if ($canfix) {
            $links[] = html_writer::link(
                new moodle_url('/local/accessibilityreview/bulkfix.php', ['id' => $courseid]),
                get_string('bulkfix', 'local_accessibilityreview'),
                ['class' => 'btn btn-secondary mr-2 me-2 mb-2']
            );
        }
        echo html_writer::div(implode('', $links), 'my-3');

        echo $output->progress_table(progress::history($courseid, 8));

        $counts = [];
        $rs = $DB->get_recordset_sql(
            "SELECT category, severity, COUNT(1) AS total
                                        FROM {" . finding_manager::TABLE . "}
                                       WHERE courseid = :courseid AND present = 1 AND state IN (:o, :p)
                                    GROUP BY category, severity",
            ['courseid' => $courseid, 'o' => finding_manager::STATE_OPEN, 'p' => finding_manager::STATE_INPROGRESS]
        );
        foreach ($rs as $row) {
            $counts[$row->category][$row->severity] = (int) $row->total;
        }
        $rs->close();
        echo html_writer::tag('h3', get_string('tab_findings', 'local_accessibilityreview'));
        echo $output->category_summary($counts);

        $linkcounts = $DB->get_records_sql_menu("SELECT l.status, COUNT(DISTINCT l.id)
                                                   FROM {" . link_manager::TABLE . "} l
                                                   JOIN {" . link_manager::USE_TABLE . "} u ON u.linkid = l.id
                                                  WHERE u.courseid = :courseid
                                               GROUP BY l.status", ['courseid' => $courseid]);
        echo html_writer::tag('h3', get_string('tab_links', 'local_accessibilityreview'));
        echo html_writer::tag('p', get_string('links_separate', 'local_accessibilityreview'));
        echo $output->count_table(get_string('linksummary', 'local_accessibilityreview'), $output->link_status_rows($linkcounts));

        echo html_writer::tag('h3', get_string('inventory', 'local_accessibilityreview'));
        foreach (resource_manager::types() as $restype) {
            $reviewcounts = $DB->get_records_sql_menu("SELECT reviewstatus, COUNT(1)
                                                         FROM {" . resource_manager::TABLE . "}
                                                        WHERE courseid = :courseid AND restype = :restype AND present = 1
                                                     GROUP BY reviewstatus", ['courseid' => $courseid, 'restype' => $restype]);
            $rows = [];
            foreach (resource_manager::REVIEW_STATUSES as $reviewstatus) {
                $label = get_string('review_' . $reviewstatus, 'local_accessibilityreview');
                $rows[$label] = (int) ($reviewcounts[$reviewstatus] ?? 0);
            }
            echo $output->count_table(get_string('tab_' . $restype, 'local_accessibilityreview'), $rows);
        }
        break;

    case 'findings':
        $filter = findings_filter::from_request($courseid);
        $filterurl = new moodle_url('/local/accessibilityreview/index.php', ['id' => $courseid, 'tab' => 'findings']);
        echo $output->findings_filter_form($filterurl, $filter, false);
        $total = $filter->count();
        $pageurl = new moodle_url($filterurl, $filter->to_params());
        echo html_writer::tag('p', get_string('findingscount', 'local_accessibilityreview', $total), ['aria-live' => 'polite']);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
        echo $output->findings_table($filter->get($page * $perpage, $perpage), false);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
        $exporturl = new moodle_url('/local/accessibilityreview/export.php', ['id' => $courseid] + $filter->to_params());
        echo html_writer::div(html_writer::link(
            $exporturl,
            get_string('exportcsv', 'local_accessibilityreview'),
            ['class' => 'btn btn-secondary']
        ), 'mt-3');
        break;

    case 'links':
        $statusfilter = optional_param('linkstatus', '', PARAM_ALPHA);
        if (!in_array($statusfilter, link_classifier::statuses(), true)) {
            $statusfilter = '';
        }
        $options = ['' => get_string('any', 'local_accessibilityreview')];
        foreach (link_classifier::statuses() as $s) {
            $options[$s] = get_string('linkstatus_' . $s, 'local_accessibilityreview');
        }
        $select = new single_select($baseurl, 'linkstatus', $options, $statusfilter, null);
        $select->set_label(get_string('linkstatus', 'local_accessibilityreview'));
        echo html_writer::tag('p', get_string('links_separate', 'local_accessibilityreview'));
        if (empty(get_config('local_accessibilityreview', 'linkcheck'))) {
            echo $OUTPUT->notification(get_string('linkcheckdisabled', 'local_accessibilityreview'), 'warning');
        }
        echo $OUTPUT->render($select);

        $where = 'u.courseid = :courseid';
        $params = ['courseid' => $courseid];
        if ($statusfilter !== '') {
            $where .= ' AND l.status = :status';
            $params['status'] = $statusfilter;
        }
        $from = "FROM {" . link_manager::USE_TABLE . "} u JOIN {" . link_manager::TABLE . "} l ON l.id = u.linkid WHERE $where";
        $total = $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $rows = $DB->get_records_sql(
            "SELECT u.id AS useid, u.courseid, u.cmid, u.provider, u.itemid, u.itemlabel,
                                             l.url, l.status, l.reason, l.httpcode, l.timechecked
                                        $from
                                    ORDER BY CASE l.status WHEN 'unavailable' THEN 1 WHEN 'unknown' THEN 2
                                             WHEN 'ratelimited' THEN 3 WHEN 'restricted' THEN 4 ELSE 5 END, u.id",
            $params,
            $page * $perpage,
            $perpage
        );
        $pageurl = new moodle_url($baseurl, ['linkstatus' => $statusfilter]);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
        echo $output->links_table($rows);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
        break;

    case 'fixes':
        echo html_writer::tag('p', get_string('fixes_intro', 'local_accessibilityreview'));
        if ($canfix) {
            echo html_writer::div(html_writer::link(
                new moodle_url('/local/accessibilityreview/bulkfix.php', ['id' => $courseid]),
                get_string('bulkfix', 'local_accessibilityreview'),
                ['class' => 'btn btn-secondary']
            ), 'mb-3');
        }
        $total = $DB->count_records(fixer::TABLE, ['courseid' => $courseid]);
        $fixes = $DB->get_records(
            fixer::TABLE,
            ['courseid' => $courseid],
            'timecreated DESC, id DESC',
            'id, findingid, checkid, itemlabel, summary, status, userid, undoneby, timecreated, timeundone',
            $page * $perpage,
            $perpage
        );
        echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
        echo $output->fixes_table(array_values($fixes), $canfix);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
        break;

    default:
        $restype = $tab;
        echo html_writer::tag('p', get_string('inventory_' . $restype . '_intro', 'local_accessibilityreview'));
        if ($restype === 'pdf' && empty(get_config('local_accessibilityreview', 'pdfanalysis'))) {
            echo $OUTPUT->notification(get_string('pdfanalysisdisabled', 'local_accessibilityreview'), 'info');
        }
        $params = ['courseid' => $courseid, 'restype' => $restype];
        $where = 'r.courseid = :courseid AND r.restype = :restype AND r.present = 1';
        $total = $DB->count_records_sql("SELECT COUNT(1) FROM {" . resource_manager::TABLE . "} r WHERE $where", $params);
        $rows = $DB->get_records_sql(
            "SELECT r.*, l.status AS linkstatus, l.metadata AS linkmetadata,
                                             l.reason AS linkreason, l.timechecked AS linkchecked
                                        FROM {" . resource_manager::TABLE . "} r
                                   LEFT JOIN {" . link_manager::TABLE . "} l ON l.id = r.linkid
                                       WHERE $where
                                    ORDER BY CASE r.reviewstatus WHEN 'issuesfound' THEN 1 WHEN 'notreviewed' THEN 2
                                             WHEN 'inprogress' THEN 3 ELSE 4 END, r.location, r.name, r.id",
            $params,
            $page * $perpage,
            $perpage
        );
        echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
        echo $output->resources_table($rows, $restype);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
        break;
}

echo $OUTPUT->footer();
