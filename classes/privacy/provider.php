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

namespace local_accessibilityreview\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * Personal data stored: the user who queued a scan, the reviewer of a finding
 * or inventory item, and authored review history entries (with notes). All
 * data is held in course context. On deletion the user reference is
 * anonymised (set to 0) and notes they authored are removed; the course's
 * accessibility records themselves are institutional and are kept.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var array<int, array{0: string, 1: string}> Tables and their user id fields. */
    private const USER_FIELDS = [
        ['local_accessibilityreview_scan', 'requestedby'],
        ['local_accessibilityreview_finding', 'reviewerid'],
        ['local_accessibilityreview_res', 'reviewerid'],
        ['local_accessibilityreview_history', 'userid'],
        ['local_accessibilityreview_fix', 'userid'],
        ['local_accessibilityreview_fix', 'undoneby'],
    ];

    /**
     * Metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_accessibilityreview_scan', [
            'courseid' => 'privacy:metadata:scan:courseid',
            'requestedby' => 'privacy:metadata:scan:requestedby',
            'timecreated' => 'privacy:metadata:scan:timecreated',
        ], 'privacy:metadata:scan');
        $collection->add_database_table('local_accessibilityreview_finding', [
            'courseid' => 'privacy:metadata:finding:courseid',
            'reviewerid' => 'privacy:metadata:finding:reviewerid',
            'timereviewed' => 'privacy:metadata:finding:timereviewed',
            'state' => 'privacy:metadata:finding:state',
        ], 'privacy:metadata:finding');
        $collection->add_database_table('local_accessibilityreview_res', [
            'courseid' => 'privacy:metadata:res:courseid',
            'reviewerid' => 'privacy:metadata:res:reviewerid',
            'timereviewed' => 'privacy:metadata:res:timereviewed',
            'reviewnotes' => 'privacy:metadata:res:reviewnotes',
        ], 'privacy:metadata:res');
        $collection->add_database_table('local_accessibilityreview_history', [
            'courseid' => 'privacy:metadata:history:courseid',
            'userid' => 'privacy:metadata:history:userid',
            'action' => 'privacy:metadata:history:action',
            'note' => 'privacy:metadata:history:note',
            'timecreated' => 'privacy:metadata:history:timecreated',
        ], 'privacy:metadata:history');
        $collection->add_database_table('local_accessibilityreview_fix', [
            'courseid' => 'privacy:metadata:fix:courseid',
            'userid' => 'privacy:metadata:fix:userid',
            'undoneby' => 'privacy:metadata:fix:undoneby',
            'summary' => 'privacy:metadata:fix:summary',
            'oldcontent' => 'privacy:metadata:fix:oldcontent',
            'newcontent' => 'privacy:metadata:fix:newcontent',
            'timecreated' => 'privacy:metadata:fix:timecreated',
            'timeundone' => 'privacy:metadata:fix:timeundone',
        ], 'privacy:metadata:fix');
        $collection->add_external_location_link('linkchecks', [
            'url' => 'privacy:metadata:linkchecks:url',
        ], 'privacy:metadata:linkchecks');
        return $collection;
    }

    /**
     * Contexts with data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        foreach (self::USER_FIELDS as [$table, $field]) {
            $sql = "SELECT ctx.id
                      FROM {context} ctx
                      JOIN {{$table}} t ON t.courseid = ctx.instanceid AND ctx.contextlevel = :courselevel
                     WHERE t.$field = :userid";
            $contextlist->add_from_sql($sql, ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);
        }
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        foreach (self::USER_FIELDS as [$table, $field]) {
            $userlist->add_from_sql(
                $field,
                "SELECT $field FROM {{$table}} WHERE courseid = :courseid AND $field > 0",
                ['courseid' => $context->instanceid]
            );
        }
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $courseid = $context->instanceid;
            $history = $DB->get_records(
                'local_accessibilityreview_history',
                ['courseid' => $courseid, 'userid' => $userid],
                'timecreated ASC'
            );
            $reviews = $DB->get_records('local_accessibilityreview_res', ['courseid' => $courseid, 'reviewerid' => $userid]);
            $findings = $DB->get_records(
                'local_accessibilityreview_finding',
                ['courseid' => $courseid, 'reviewerid' => $userid],
                '',
                'id, checkid, itemlabel, state, timereviewed'
            );
            $scans = $DB->get_records(
                'local_accessibilityreview_scan',
                ['courseid' => $courseid, 'requestedby' => $userid],
                '',
                'id, status, timecreated'
            );
            $fixes = $DB->get_records_select(
                'local_accessibilityreview_fix',
                'courseid = :courseid AND (userid = :userid OR undoneby = :undoneby)',
                ['courseid' => $courseid, 'userid' => $userid, 'undoneby' => $userid],
                'timecreated ASC',
                'id, checkid, itemlabel, summary, status, userid, undoneby, timecreated, timeundone'
            );
            $data = (object) [
                'history' => array_values(array_map(fn($h) => (object) [
                    'itemtype' => $h->itemtype,
                    'action' => $h->action,
                    'fromstate' => $h->fromstate,
                    'tostate' => $h->tostate,
                    'note' => $h->note,
                    'timecreated' => transform::datetime($h->timecreated),
                ], $history)),
                'findingsreviewed' => array_values(array_map(fn($f) => (object) [
                    'check' => $f->checkid,
                    'location' => $f->itemlabel,
                    'state' => $f->state,
                    'timereviewed' => transform::datetime($f->timereviewed),
                ], $findings)),
                'resourcesreviewed' => array_values(array_map(fn($r) => (object) [
                    'name' => $r->name,
                    'reviewstatus' => $r->reviewstatus,
                    'reviewnotes' => $r->reviewnotes,
                    'timereviewed' => transform::datetime($r->timereviewed),
                ], $reviews)),
                'scansrequested' => array_values(array_map(fn($s) => (object) [
                    'status' => $s->status,
                    'timecreated' => transform::datetime($s->timecreated),
                ], $scans)),
                'fixes' => array_values(array_map(fn($x) => (object) [
                    'check' => $x->checkid,
                    'location' => $x->itemlabel,
                    'change' => $x->summary,
                    'appliedbyyou' => transform::yesno((int) $x->userid === (int) $userid),
                    'undonebyyou' => transform::yesno((int) $x->undoneby === (int) $userid),
                    'status' => $x->status,
                    'timecreated' => transform::datetime($x->timecreated),
                    'timeundone' => $x->timeundone ? transform::datetime($x->timeundone) : null,
                ], $fixes)),
            ];
            if (
                $data->history || $data->findingsreviewed || $data->resourcesreviewed || $data->scansrequested
                    || $data->fixes
            ) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_accessibilityreview')],
                    $data
                );
            }
        }
    }

    /**
     * Delete all user data in a context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if (!$context instanceof \context_course) {
            return;
        }
        self::anonymise($context->instanceid, null);
    }

    /**
     * Delete data for one user in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                self::anonymise($context->instanceid, [$userid]);
            }
        }
    }

    /**
     * Delete data for several users in one context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_course && $userlist->get_userids()) {
            self::anonymise($context->instanceid, $userlist->get_userids());
        }
    }

    /**
     * Remove user references (and notes they wrote) for a course.
     *
     * @param int $courseid
     * @param int[]|null $userids Null for all users.
     * @return void
     */
    private static function anonymise(int $courseid, ?array $userids): void {
        global $DB;
        foreach (self::USER_FIELDS as [$table, $field]) {
            $select = "courseid = :courseid AND $field > 0";
            $params = ['courseid' => $courseid];
            if ($userids !== null) {
                [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
                $select .= " AND $field $insql";
                $params += $inparams;
            }
            if ($table === 'local_accessibilityreview_history') {
                $DB->set_field_select($table, 'note', null, $select, $params);
            }
            if ($table === 'local_accessibilityreview_res') {
                $DB->set_field_select($table, 'reviewnotes', null, $select, $params);
            }
            $DB->set_field_select($table, $field, 0, $select, $params);
        }
    }
}
