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

namespace local_accessibilityreview;

use backup;
use backup_controller;
use backup_setting;
use local_accessibilityreview\local\finding_manager;
use local_accessibilityreview\local\history;
use local_accessibilityreview\local\resource_manager;
use local_accessibilityreview\local\scanner;
use restore_controller;
use restore_dbops;

/**
 * Manual review work travels with a course backup and survives the first scan after restore.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_local_accessibilityreview_plugin
 * @covers     \restore_local_accessibilityreview_plugin
 * @covers     \local_accessibilityreview\local\finding_manager
 * @covers     \local_accessibilityreview\local\resource_manager
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Queue and synchronously run a scan.
     *
     * @param int $courseid
     * @return int Scan id.
     */
    private function scan(int $courseid): int {
        $result = scanner::queue($courseid, 0);
        scanner::run_now($result['scanid']);
        return $result['scanid'];
    }

    /**
     * Back a course up and restore it as a new course, without user data.
     *
     * @param \stdClass $course
     * @return int New course id.
     */
    private function backup_and_restore(\stdClass $course): int {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(false);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = restore_dbops::create_new_course($course->fullname, $course->shortname . '_copy', $course->category);
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(false);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * A course with one page whose image has no alt text, scanned, with the finding marked as an exception.
     *
     * @return array [course, page, finding, reviewer]
     */
    private function reviewed_course(): array {
        global $DB;
        $reviewer = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['summary' => '<p><img src="banner.png"></p>',
            'summaryformat' => FORMAT_HTML]);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'content' => '<p><img src="a.png"></p><iframe src="https://player.vimeo.com/video/42" title="Intro"></iframe>',
            'contentformat' => FORMAT_HTML]);
        $this->scan($course->id);
        $finding = $DB->get_record(finding_manager::TABLE, ['provider' => 'page_content', 'itemid' => $page->id,
            'checkid' => 'img_alt_missing'], '*', MUST_EXIST);
        $this->assertNotSame('', $finding->signature);
        finding_manager::set_state($finding, finding_manager::STATE_EXCEPTION, 'Decorative image', (int) $reviewer->id);
        return [$course, $page, $finding, $reviewer];
    }

    public function test_reviewed_finding_travels_with_the_course_and_survives_the_first_scan(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $page, $finding] = $this->reviewed_course();

        $newcourseid = $this->backup_and_restore($course);

        // Only the reviewed finding is restored; the open course-summary finding is not.
        $restored = $DB->get_records(finding_manager::TABLE, ['courseid' => $newcourseid]);
        $this->assertCount(1, $restored);
        $restored = reset($restored);
        $newpage = $DB->get_record('page', ['course' => $newcourseid], '*', MUST_EXIST);
        $newcm = get_coursemodule_from_instance('page', $newpage->id, $newcourseid, false, MUST_EXIST);
        $this->assertSame(finding_manager::STATE_EXCEPTION, $restored->state);
        $this->assertEquals($newpage->id, $restored->itemid);
        $this->assertEquals($newcm->id, $restored->cmid);
        $this->assertEquals(0, $restored->reviewerid, 'Users were not restored');
        $this->assertSame(
            finding_manager::identity(
                $newcourseid,
                'page_content',
                (int) $newpage->id,
                $finding->field,
                'img_alt_missing',
                $finding->signature
            ),
            $restored->identity
        );
        $this->assertTrue(finding_manager::is_restored($restored));
        $notes = array_column(history::get('finding', (int) $restored->id), 'note');
        $this->assertContains('Decorative image', $notes);

        // The first scan of the restored course keeps the exception instead of creating a second finding.
        $scanid = $this->scan($newcourseid);
        $after = $DB->get_records(finding_manager::TABLE, ['courseid' => $newcourseid, 'provider' => 'page_content',
            'checkid' => 'img_alt_missing']);
        $this->assertCount(1, $after);
        $after = reset($after);
        $this->assertEquals($restored->id, $after->id);
        $this->assertSame(finding_manager::STATE_EXCEPTION, $after->state);
        $this->assertEquals(1, $after->present);
        $this->assertEquals($scanid, $after->lastscanid);
        // The open finding of the course summary is detected afresh.
        $this->assertTrue($DB->record_exists(finding_manager::TABLE, ['courseid' => $newcourseid,
            'provider' => 'course_summary', 'state' => finding_manager::STATE_OPEN]));
        // The original course is untouched.
        $this->assertEquals(2, $DB->count_records(finding_manager::TABLE, ['courseid' => $course->id]));
    }

    public function test_finding_reviewed_before_signatures_existed_is_adopted_on_rescan(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, , $finding] = $this->reviewed_course();
        // Findings last seen before 2.3.0 have no signature.
        $DB->set_field(finding_manager::TABLE, 'signature', null, ['id' => $finding->id]);

        $newcourseid = $this->backup_and_restore($course);
        $restored = $DB->get_record(finding_manager::TABLE, ['courseid' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame('', $restored->signature);

        $this->scan($newcourseid);
        $after = $DB->get_records(finding_manager::TABLE, ['courseid' => $newcourseid, 'provider' => 'page_content',
            'checkid' => 'img_alt_missing']);
        $this->assertCount(1, $after);
        $after = reset($after);
        $this->assertEquals($restored->id, $after->id);
        $this->assertSame(finding_manager::STATE_EXCEPTION, $after->state);
        $this->assertSame($finding->signature, $after->signature);
        $this->assertEquals(1, $after->present);
    }

    public function test_reviewed_inventory_item_travels_with_the_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, , , $reviewer] = $this->reviewed_course();
        $video = $DB->get_record(resource_manager::TABLE, ['courseid' => $course->id, 'restype' => 'video',
            'source' => 'vimeo'], '*', MUST_EXIST);
        resource_manager::save_review(
            $video,
            array_fill_keys(resource_manager::checklist('video'), 'yes'),
            'reviewed',
            'Captions checked',
            (int) $reviewer->id
        );

        $newcourseid = $this->backup_and_restore($course);
        $restored = $DB->get_records(resource_manager::TABLE, ['courseid' => $newcourseid]);
        $this->assertCount(1, $restored);
        $restored = reset($restored);
        $this->assertSame('reviewed', $restored->reviewstatus);
        $this->assertSame('Captions checked', $restored->reviewnotes);
        $newpage = $DB->get_record('page', ['course' => $newcourseid], '*', MUST_EXIST);
        $newcm = get_coursemodule_from_instance('page', $newpage->id, $newcourseid, false, MUST_EXIST);
        $this->assertEquals($newcm->id, $restored->cmid);

        $this->scan($newcourseid);
        $after = $DB->get_records(resource_manager::TABLE, ['courseid' => $newcourseid, 'restype' => 'video']);
        $this->assertCount(1, $after);
        $after = reset($after);
        $this->assertEquals($restored->id, $after->id);
        $this->assertSame('reviewed', $after->reviewstatus);
        $this->assertEquals(1, $after->present);
        $this->assertSame(sha1('vimeo|' . $newcourseid . '|42'), $after->identity);
    }
}
