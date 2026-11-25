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

use local_accessibilityreview\local\finding_manager;

/**
 * Restores the manual accessibility review work backed up with a course.
 *
 * Rows are inserted while the course is being restored and finished in after_restore_course(),
 * once every activity, section and question has its new id: course module, item and reviewer ids
 * are mapped, the finding identity is rebuilt from the stored signature, and rows whose content was
 * not restored are dropped. Restored rows have no scan yet (lastscanid 0, present 0); the next scan
 * of the course matches them and keeps their reviewed state.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_accessibilityreview_plugin extends restore_local_plugin {
    /** @var array[] Restored findings keyed by new id: the ids from the backup that still need mapping. */
    private array $findings = [];

    /** @var array[] Restored inventory items keyed by new id. */
    private array $resources = [];

    /**
     * Paths handled at the course level.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [
            new restore_path_element($this->get_namefor('finding'), $this->get_pathfor('/findings/finding')),
            new restore_path_element(
                $this->get_namefor('findingentry'),
                $this->get_pathfor('/findings/finding/findinghistory/findingentry')
            ),
            new restore_path_element($this->get_namefor('resource'), $this->get_pathfor('/resources/resource')),
            new restore_path_element(
                $this->get_namefor('resourceentry'),
                $this->get_pathfor('/resources/resource/resourcehistory/resourceentry')
            ),
        ];
    }

    /**
     * Insert a reviewed finding; ids are mapped later in after_restore_course().
     *
     * @param array $data
     * @return void
     */
    public function process_local_accessibilityreview_finding($data) {
        global $DB;
        $data = (object) $data;
        $oldid = (int) $data->id;
        unset($data->id);
        $data->courseid = $this->get_task()->get_courseid();
        // A placeholder until the signature can be used; unique per restore.
        $data->identity = sha1('restored|' . $data->identity . '|' . $data->courseid . '|' . $this->get_restoreid());
        $data->signature = (string) ($data->signature ?? '');
        $data->reviewerid = $this->map_user((int) $data->reviewerid);
        $data->present = 0;
        $data->lastscanid = 0;
        $newid = (int) $DB->insert_record(finding_manager::TABLE, $data);
        $this->set_mapping($this->get_namefor('finding'), $oldid, $newid);
        $this->findings[$newid] = [
            'provider' => $data->provider, 'cmid' => (int) $data->cmid, 'itemid' => (int) $data->itemid,
            'field' => $data->field, 'checkid' => $data->checkid, 'signature' => $data->signature,
        ];
    }

    /**
     * Insert a history entry of a restored finding.
     *
     * @param array $data
     * @return void
     */
    public function process_local_accessibilityreview_findingentry($data) {
        $this->insert_history('finding', (int) $this->get_new_parentid($this->get_namefor('finding')), $data);
    }

    /**
     * Insert a reviewed inventory item; ids are mapped later in after_restore_course().
     *
     * @param array $data
     * @return void
     */
    public function process_local_accessibilityreview_resource($data) {
        global $DB;
        $data = (object) $data;
        $oldid = (int) $data->id;
        unset($data->id);
        $data->courseid = $this->get_task()->get_courseid();
        $data->identity = sha1('restored|' . $data->identity . '|' . $data->courseid . '|' . $this->get_restoreid());
        $data->reviewerid = $this->map_user((int) $data->reviewerid);
        $data->fileid = 0;
        $data->linkid = 0;
        $data->analysisstatus = 'none';
        $data->analysis = null;
        $data->timeanalysed = 0;
        $data->present = 0;
        $data->lastscanid = 0;
        $newid = (int) $DB->insert_record('local_accessibilityreview_res', $data);
        $this->set_mapping($this->get_namefor('resource'), $oldid, $newid);
        $this->resources[$newid] = ['cmid' => (int) $data->cmid, 'source' => $data->source];
    }

    /**
     * Insert a history entry of a restored inventory item.
     *
     * @param array $data
     * @return void
     */
    public function process_local_accessibilityreview_resourceentry($data) {
        $this->insert_history('resource', (int) $this->get_new_parentid($this->get_namefor('resource')), $data);
    }

    /**
     * Map course modules, items and identities now that the whole course is restored.
     *
     * @return void
     */
    public function after_restore_course() {
        global $DB;
        $courseid = $this->get_task()->get_courseid();
        foreach ($this->findings as $id => $row) {
            $cmid = $this->map_cmid($row['cmid']);
            $itemid = $cmid === null ? null : $this->map_itemid($row['provider'], $row['itemid'], $cmid, $courseid);
            if ($itemid === null) {
                $this->drop('finding', $id);
                continue;
            }
            $update = (object) ['id' => $id, 'cmid' => $cmid, 'itemid' => $itemid];
            if ($row['signature'] !== '') {
                $identity = finding_manager::identity(
                    $courseid,
                    $row['provider'],
                    $itemid,
                    $row['field'],
                    $row['checkid'],
                    $row['signature']
                );
                if ($DB->record_exists(finding_manager::TABLE, ['identity' => $identity])) {
                    // The course was already scanned on this site: keep the live finding.
                    $this->drop('finding', $id);
                    continue;
                }
                $update->identity = $identity;
            }
            $DB->update_record(finding_manager::TABLE, $update);
        }
        foreach ($this->resources as $id => $row) {
            $cmid = $this->map_cmid($row['cmid']);
            if ($cmid === null) {
                $this->drop('resource', $id);
                continue;
            }
            $update = (object) ['id' => $id, 'cmid' => $cmid];
            if ($row['source'] === 'package' && $cmid) {
                $identity = sha1('scorm|' . $cmid);
                if ($DB->record_exists('local_accessibilityreview_res', ['identity' => $identity])) {
                    $this->drop('resource', $id);
                    continue;
                }
                $update->identity = $identity;
            }
            $DB->update_record('local_accessibilityreview_res', $update);
        }
        $this->findings = [];
        $this->resources = [];
    }

    /**
     * New course module id, 0 when the row is not tied to a module, null when the module was not restored.
     *
     * @param int $oldcmid
     * @return int|null
     */
    private function map_cmid(int $oldcmid): ?int {
        if ($oldcmid === 0) {
            return 0;
        }
        $newcmid = (int) $this->get_mappingid('course_module', $oldcmid, 0);
        return $newcmid ?: null;
    }

    /**
     * New source record id for a finding, or null when it cannot be mapped.
     *
     * @param string $provider
     * @param int $olditemid
     * @param int $newcmid
     * @param int $courseid
     * @return int|null
     */
    private function map_itemid(string $provider, int $olditemid, int $newcmid, int $courseid): ?int {
        global $DB;
        switch ($provider) {
            case 'course_summary':
                return $courseid;
            case 'section_summary':
                return (int) $this->get_mappingid('course_section', $olditemid, 0) ?: null;
            case 'activity_intro':
            case 'page_content':
            case 'url_resource':
                $instance = $newcmid ? (int) $DB->get_field('course_modules', 'instance', ['id' => $newcmid]) : 0;
                return $instance ?: null;
            case 'book_chapter':
                return (int) $this->get_mappingid('book_chapter', $olditemid, 0) ?: null;
            case 'glossary_entry':
                return (int) $this->get_mappingid('glossary_entry', $olditemid, 0) ?: null;
            case 'lesson_page':
                return (int) $this->get_mappingid('lesson_page', $olditemid, 0) ?: null;
            case 'quiz_question':
                return (int) $this->get_mappingid('question', $olditemid, 0) ?: null;
            default:
                return null;
        }
    }

    /**
     * Reviewer id on this site, or 0 when users were not restored.
     *
     * @param int $olduserid
     * @return int
     */
    private function map_user(int $olduserid): int {
        return $olduserid ? (int) $this->get_mappingid('user', $olduserid, 0) : 0;
    }

    /**
     * Insert one history row for a restored item.
     *
     * @param string $itemtype finding or resource.
     * @param int $itemid New id of the item.
     * @param array $data
     * @return void
     */
    private function insert_history(string $itemtype, int $itemid, array $data): void {
        global $DB;
        if (!$itemid) {
            return;
        }
        $DB->insert_record('local_accessibilityreview_history', (object) [
            'itemtype' => $itemtype,
            'itemid' => $itemid,
            'courseid' => $this->get_task()->get_courseid(),
            'userid' => $this->map_user((int) ($data['userid'] ?? 0)),
            'action' => $data['action'],
            'fromstate' => $data['fromstate'] ?? null,
            'tostate' => $data['tostate'] ?? null,
            'note' => $data['note'] ?? null,
            'timecreated' => (int) ($data['timecreated'] ?? time()),
        ]);
    }

    /**
     * Remove a restored row whose content did not come with the course, together with its history.
     *
     * @param string $itemtype finding or resource.
     * @param int $id
     * @return void
     */
    private function drop(string $itemtype, int $id): void {
        global $DB;
        $table = $itemtype === 'finding' ? finding_manager::TABLE : 'local_accessibilityreview_res';
        $DB->delete_records('local_accessibilityreview_history', ['itemtype' => $itemtype, 'itemid' => $id]);
        $DB->delete_records($table, ['id' => $id]);
    }
}
