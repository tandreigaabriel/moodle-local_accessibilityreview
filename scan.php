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
 * Queue a background scan for a course, or for all courses in a category (site managers).
 *
 * POST only, sesskey required. No scanning happens in the web request.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_accessibilityreview\local\scanner;

\local_accessibilityreview\local\license::require_valid();

$courseid = optional_param('id', 0, PARAM_INT);
$categoryid = optional_param('categoryid', -1, PARAM_INT);

if (!data_submitted()) {
    // Only POST may change state; the sesskey is checked below as well.
    throw new moodle_exception('invalidrequest');
}
require_sesskey();

if ($courseid) {
    $course = get_course($courseid);
    require_login($course, false);
    $context = context_course::instance($course->id);
    require_capability('local/accessibilityreview:scan', $context);
    $result = scanner::queue((int) $course->id, (int) $USER->id);
    $message = get_string($result['created'] ? 'scanqueued' : 'scanalreadyqueued', 'local_accessibilityreview');
    redirect(
        new moodle_url('/local/accessibilityreview/index.php', ['id' => $course->id]),
        $message,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Bulk queue for a category (or the whole site with categoryid = 0).
require_login(null, false);
$syscontext = context_system::instance();
require_capability('local/accessibilityreview:manage', $syscontext);
require_capability('local/accessibilityreview:scan', $syscontext);
if ($categoryid < 0) {
    throw new moodle_exception('missingparam', '', '', 'categoryid');
}
$params = ['siteid' => SITEID];
$where = 'id <> :siteid';
if ($categoryid > 0) {
    $category = core_course_category::get($categoryid, MUST_EXIST, true);
    $ids = array_merge([$category->id], $category->get_all_children_ids());
    [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
    $where .= " AND category $insql";
    $params += $inparams;
}
$limit = max(1, (int) (get_config('local_accessibilityreview', 'bulkqueuelimit') ?: 500));
$courseids = $DB->get_fieldset_sql("SELECT id FROM {course} WHERE $where ORDER BY id", $params);
$queued = 0;
foreach (array_slice($courseids, 0, $limit) as $id) {
    $result = scanner::queue((int) $id, (int) $USER->id);
    $queued += $result['created'] ? 1 : 0;
}
redirect(
    new moodle_url('/local/accessibilityreview/site.php', ['categoryid' => max(0, $categoryid)]),
    get_string('bulkqueued', 'local_accessibilityreview', (object) ['queued' => $queued, 'total' => count($courseids),
    'limit' => $limit]),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
