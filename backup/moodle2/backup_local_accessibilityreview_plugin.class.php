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
 * Backs up the manual accessibility review work of a course.
 *
 * Only data that people entered by hand travels with the course: reviewed findings (state, reviewer,
 * time, history and notes) and reviewed inventory items (PDF, video and SCORM checklists and notes).
 * Open findings, scan runs, snapshots, link caches and applied fixes are not backed up: a scan on the
 * restored course regenerates the findings, and fixes belong to the original content records.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_accessibilityreview_plugin extends backup_local_plugin {
    /**
     * Course-level structure: reviewed findings and inventory items, each with its history.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($wrapper);

        $findings = new backup_nested_element('findings');
        $finding = new backup_nested_element('finding', ['id'], [
            'identity', 'cmid', 'provider', 'itemid', 'field', 'itemlabel', 'checkid', 'signature', 'category',
            'severity', 'manualreview', 'excerpt', 'details', 'contenthash', 'state', 'reviewerid', 'timereviewed',
            'timefirstseen', 'timelastseen', 'timemodified',
        ]);
        $findinghistory = new backup_nested_element('findinghistory');
        $findingentry = new backup_nested_element('findingentry', ['id'], [
            'userid', 'action', 'fromstate', 'tostate', 'note', 'timecreated',
        ]);
        $wrapper->add_child($findings);
        $findings->add_child($finding);
        $finding->add_child($findinghistory);
        $findinghistory->add_child($findingentry);

        $resources = new backup_nested_element('resources');
        $resource = new backup_nested_element('resource', ['id'], [
            'identity', 'cmid', 'restype', 'source', 'name', 'location', 'provider', 'itemid', 'filesize', 'filehash',
            'url', 'reviewstatus', 'checklist', 'reviewnotes', 'reviewerid', 'timereviewed', 'timefirstseen',
            'timelastseen',
        ]);
        $resourcehistory = new backup_nested_element('resourcehistory');
        $resourceentry = new backup_nested_element('resourceentry', ['id'], [
            'userid', 'action', 'fromstate', 'tostate', 'note', 'timecreated',
        ]);
        $wrapper->add_child($resources);
        $resources->add_child($resource);
        $resource->add_child($resourcehistory);
        $resourcehistory->add_child($resourceentry);

        $finding->set_source_sql(
            "SELECT * FROM {local_accessibilityreview_finding}
              WHERE courseid = ? AND (state <> 'open' OR timereviewed > 0)
           ORDER BY id",
            [backup::VAR_COURSEID]
        );
        $findingentry->set_source_sql(
            "SELECT * FROM {local_accessibilityreview_history}
              WHERE itemtype = 'finding' AND itemid = ?
           ORDER BY id",
            [backup::VAR_PARENTID]
        );
        $resource->set_source_sql(
            "SELECT * FROM {local_accessibilityreview_res}
              WHERE courseid = ? AND (reviewstatus <> 'notreviewed' OR timereviewed > 0)
           ORDER BY id",
            [backup::VAR_COURSEID]
        );
        $resourceentry->set_source_sql(
            "SELECT * FROM {local_accessibilityreview_history}
              WHERE itemtype = 'resource' AND itemid = ?
           ORDER BY id",
            [backup::VAR_PARENTID]
        );

        $finding->annotate_ids('user', 'reviewerid');
        $findingentry->annotate_ids('user', 'userid');
        $resource->annotate_ids('user', 'reviewerid');
        $resourceentry->annotate_ids('user', 'userid');

        return $plugin;
    }
}
