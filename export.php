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
 * CSV export of findings with spreadsheet formula-injection protection.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- require_login() is called by local\access::require_course_view() or require_site_view().
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_accessibilityreview\local\access;
use local_accessibilityreview\local\checks\check_registry;
use local_accessibilityreview\local\csv_safe;
use local_accessibilityreview\local\findings_filter;

$courseid = optional_param('id', 0, PARAM_INT);
$site = optional_param('site', 0, PARAM_BOOL);

if ($courseid) {
    [$course, $context] = access::require_course_view($courseid);
    $filter = findings_filter::from_request((int) $course->id);
    $filename = 'accessibility-' . clean_filename($course->shortname) . '-' . date('Ymd');
} else if ($site) {
    $context = access::require_site_view();
    $filter = findings_filter::from_request(0);
    $filename = 'accessibility-site-' . date('Ymd');
} else {
    throw new moodle_exception('missingparam', '', '', 'id');
}

$str = fn(string $id) => get_string($id, 'local_accessibilityreview');
$writer = new csv_export_writer();
$writer->set_filename($filename);
$writer->add_data(csv_safe::row([
    get_string('course'), get_string('shortname'), $str('location'), $str('field'), $str('contenttype'), $str('checkid'),
    $str('check'), $str('category'), $str('severity'), $str('findingtype'), $str('wcag'), $str('reviewstatus'),
    $str('present'), $str('excerpt'), $str('firstseen'), $str('lastseen'), $str('lastreviewed'), $str('findinglink'),
]));

$rs = $filter->recordset();
$names = [];
foreach ($rs as $f) {
    $reviewer = '';
    if ($f->reviewerid) {
        if (!isset($names[$f->reviewerid])) {
            $user = core_user::get_user($f->reviewerid);
            $names[$f->reviewerid] = $user ? fullname($user) : '';
        }
        $reviewer = $names[$f->reviewerid] . ' ' . userdate($f->timereviewed, '%Y-%m-%d %H:%M');
    }
    $def = check_registry::get($f->checkid);
    $writer->add_data(csv_safe::row([
        format_string($f->coursename, true, ['escape' => false]),
        format_string($f->courseshortname, true, ['escape' => false]),
        $f->itemlabel,
        $f->field,
        $f->provider,
        $f->checkid,
        $str('check_' . $f->checkid),
        $str('category_' . $f->category),
        $str('severity_' . $f->severity),
        $f->manualreview ? $str('type_manual') : $str('type_automated'),
        implode(' ', $def['wcag']),
        $str('state_' . $f->state),
        $f->present ? get_string('yes') : get_string('no'),
        $f->excerpt,
        userdate($f->timefirstseen, '%Y-%m-%d %H:%M'),
        userdate($f->timelastseen, '%Y-%m-%d %H:%M'),
        $reviewer,
        (new moodle_url('/local/accessibilityreview/finding.php', ['id' => $f->id]))->out(false),
    ]));
}
$rs->close();
$writer->download_file();
