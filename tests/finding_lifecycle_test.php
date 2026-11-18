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

use local_accessibilityreview\local\finding_manager;
use local_accessibilityreview\local\history;
use local_accessibilityreview\local\links\link_manager;
use local_accessibilityreview\local\resource_manager;
use local_accessibilityreview\local\scanner;

/**
 * End-to-end scanning and finding lifecycle tests.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibilityreview\local\scanner
 * @covers     \local_accessibilityreview\local\finding_manager
 * @covers     \local_accessibilityreview\local\resource_manager
 */
final class finding_lifecycle_test extends \advanced_testcase {
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
     * Finding for a page's content by check id.
     *
     * @param int $pageid
     * @param string $checkid
     * @return \stdClass|false
     */
    private function finding(int $pageid, string $checkid) {
        global $DB;
        return $DB->get_record(finding_manager::TABLE, ['provider' => 'page_content', 'itemid' => $pageid,
            'checkid' => $checkid]);
    }

    /**
     * Scanning creates findings, records links and inventory, and completes.
     */
    public function test_scan_creates_findings(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['summary' => '<p><a href="https://example.com/x"></a></p>']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'content' => '<h2>A</h2><h4>B</h4><img src="a.png"><iframe src="https://player.vimeo.com/video/42?h=abcdef1234">' .
                '</iframe><a href="https://example.com/doc.pdf">Reading (PDF)</a>',
            'contentformat' => FORMAT_HTML]);
        $this->getDataGenerator()->create_module('label', ['course' => $course->id,
            'intro' => '<a href="https://example.com/y">click here</a>', 'introformat' => FORMAT_HTML]);

        $scanid = $this->scan($course->id);
        $scan = $DB->get_record(scanner::TABLE, ['id' => $scanid]);
        $this->assertSame(scanner::STATUS_COMPLETE, $scan->status);
        $this->assertEquals(0, $scan->itemsfailed);

        $this->assertNotEmpty($this->finding($page->id, 'img_alt_missing'));
        $this->assertNotEmpty($this->finding($page->id, 'heading_skip'));
        $this->assertNotEmpty($this->finding($page->id, 'iframe_title_missing'));
        $this->assertTrue($DB->record_exists(finding_manager::TABLE, ['provider' => 'course_summary',
            'checkid' => 'link_empty']));
        $unclear = $DB->get_record(finding_manager::TABLE, ['provider' => 'activity_intro', 'checkid' => 'link_unclear']);
        $this->assertEquals(1, $unclear->manualreview);

        // Links recorded once per URL; inventory has the Vimeo video and external PDF.
        $this->assertEquals(1, $DB->count_records_select(
            link_manager::TABLE,
            $DB->sql_compare_text('url') . ' = :url',
            ['url' => 'https://example.com/doc.pdf']
        ));
        $this->assertTrue($DB->record_exists(resource_manager::TABLE, ['courseid' => $course->id, 'restype' => 'video',
            'source' => 'vimeo']));
        $this->assertTrue($DB->record_exists(resource_manager::TABLE, ['courseid' => $course->id, 'restype' => 'pdf',
            'source' => 'external']));
        // Stored excerpts never contain the private Vimeo hash.
        $iframe = $this->finding($page->id, 'iframe_title_missing');
        $this->assertStringNotContainsString('abcdef1234', $iframe->excerpt);
    }

    /**
     * Review decisions survive unchanged rescans and are reopened when the source changes.
     */
    public function test_lifecycle(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'content' => '<p><img src="a.png"></p><p><a href="https://example.com/z">click here</a></p>',
            'contentformat' => FORMAT_HTML]);
        $this->scan($course->id);

        $img = $this->finding($page->id, 'img_alt_missing');
        $link = $this->finding($page->id, 'link_unclear');
        $this->assertSame(finding_manager::STATE_OPEN, $img->state);

        // Reviewer decisions.
        finding_manager::set_state($img, finding_manager::STATE_INPROGRESS, 'Asked the author', $user->id);
        finding_manager::set_state($link, finding_manager::STATE_EXCEPTION, 'Context makes the link clear', $user->id);

        // Unchanged rescan: identities stable, decisions kept.
        $this->scan($course->id);
        $img2 = $this->finding($page->id, 'img_alt_missing');
        $link2 = $this->finding($page->id, 'link_unclear');
        $this->assertEquals($img->id, $img2->id);
        $this->assertSame(finding_manager::STATE_INPROGRESS, $img2->state);
        $this->assertSame(finding_manager::STATE_EXCEPTION, $link2->state);
        $this->assertEquals(1, $DB->count_records(finding_manager::TABLE, ['provider' => 'page_content',
            'itemid' => $page->id, 'checkid' => 'img_alt_missing']));

        // Source changes elsewhere in the field: the exception is reopened.
        $DB->set_field('page', 'content', '<p><img src="a.png"></p><p>New paragraph.</p>' .
            '<p><a href="https://example.com/z">click here</a></p>', ['id' => $page->id]);
        $this->scan($course->id);
        $link3 = $this->finding($page->id, 'link_unclear');
        $this->assertSame(finding_manager::STATE_OPEN, $link3->state);
        $actions = array_column(history::get('finding', (int) $link3->id), 'action');
        $this->assertContains('reopened', $actions);

        // The image is fixed: no longer detected and resolved by the system.
        $DB->set_field('page', 'content', '<p><img src="a.png" alt="Campus map"></p>', ['id' => $page->id]);
        $this->scan($course->id);
        $img4 = $this->finding($page->id, 'img_alt_missing');
        $this->assertEquals(0, $img4->present);
        $this->assertSame(finding_manager::STATE_RESOLVED, $img4->state);
        $actions = array_column(history::get('finding', (int) $img4->id), 'action');
        $this->assertContains('notdetected', $actions);
        $this->assertContains('statechange', $actions);

        // The problem returns: reopened as "reappeared".
        $DB->set_field('page', 'content', '<p><img src="a.png"></p>', ['id' => $page->id]);
        $this->scan($course->id);
        $img5 = $this->finding($page->id, 'img_alt_missing');
        $this->assertEquals($img->id, $img5->id);
        $this->assertEquals(1, $img5->present);
        $this->assertSame(finding_manager::STATE_OPEN, $img5->state);
    }

    /**
     * Removing the source item marks its findings absent.
     */
    public function test_source_removed(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'content' => '<img src="a.png">', 'contentformat' => FORMAT_HTML]);
        $this->scan($course->id);
        $this->assertEquals(1, $this->finding($page->id, 'img_alt_missing')->present);

        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $page->cmid]);
        $this->scan($course->id);
        $finding = $this->finding($page->id, 'img_alt_missing');
        $this->assertEquals(0, $finding->present);
        $this->assertSame(finding_manager::STATE_RESOLVED, $finding->state);
    }

    /**
     * A second scan is not queued while one is active, and scanning never edits content.
     */
    public function test_no_duplicate_scans_and_read_only(): void {
        global $DB;
        $this->resetAfterTest();
        // Creating the page would otherwise queue a delayed rescan through the observer.
        set_config('rescanonedit', 0, 'local_accessibilityreview');
        $course = $this->getDataGenerator()->create_course();
        $html = '<p><img src="a.png"></p>';
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => $html,
            'contentformat' => FORMAT_HTML]);
        $first = scanner::queue($course->id, 0);
        $second = scanner::queue($course->id, 0);
        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($first['scanid'], $second['scanid']);

        scanner::run_now($first['scanid']);
        $this->assertSame($html, $DB->get_field('page', 'content', ['id' => $page->id]));
        $third = scanner::queue($course->id, 0);
        $this->assertTrue($third['created']);
    }

    /**
     * A scan interrupted by the time limit resumes where it stopped.
     */
    public function test_resumable(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('batchsize', 5, 'local_accessibilityreview');
        $course = $this->getDataGenerator()->create_course();
        for ($i = 0; $i < 12; $i++) {
            $this->getDataGenerator()->create_module('page', ['course' => $course->id,
                'content' => '<img src="p' . $i . '.png">', 'contentformat' => FORMAT_HTML]);
        }
        $scanid = scanner::queue($course->id, 0)['scanid'];
        // Simulate a saved position part-way through the page provider.
        $steps = \local_accessibilityreview\local\content\provider_registry::steps();
        $pagestep = array_search('page_content', $steps, true);
        $cmids = $DB->get_fieldset_sql("SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
            WHERE m.name = 'page' AND cm.course = ? ORDER BY cm.id", [$course->id]);
        $DB->set_field(scanner::TABLE, 'resumestate', json_encode(['step' => $pagestep, 'after' => $cmids[5],
            'completed' => array_slice($steps, 0, $pagestep)]), ['id' => $scanid]);
        scanner::run_now($scanid);
        // Only the pages after the saved position were processed in this run.
        $this->assertEquals(6, $DB->count_records(finding_manager::TABLE, ['courseid' => $course->id,
            'provider' => 'page_content', 'checkid' => 'img_alt_missing']));
        $this->assertSame(scanner::STATUS_COMPLETE, $DB->get_field(scanner::TABLE, 'status', ['id' => $scanid]));
    }

    /**
     * Inventory review history and reopen on file change.
     */
    public function test_resource_review_reopens_on_change(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $id = resource_manager::upsert(1, ['identity' => sha1('t'), 'courseid' => $course->id, 'cmid' => 0,
            'restype' => 'pdf', 'source' => 'file', 'name' => 'a.pdf', 'filehash' => str_repeat('a', 40)]);
        $record = $DB->get_record(resource_manager::TABLE, ['id' => $id]);
        $this->assertSame('pending', $record->analysisstatus);
        $answers = array_fill_keys(resource_manager::checklist('pdf'), 'yes');
        resource_manager::save_review($record, $answers, 'reviewed', 'Checked with PAC', $user->id);
        $this->assertSame('reviewed', $DB->get_field(resource_manager::TABLE, 'reviewstatus', ['id' => $id]));

        resource_manager::upsert(2, ['identity' => sha1('t'), 'courseid' => $course->id, 'cmid' => 0,
            'restype' => 'pdf', 'source' => 'file', 'name' => 'a.pdf', 'filehash' => str_repeat('b', 40)]);
        $record = $DB->get_record(resource_manager::TABLE, ['id' => $id]);
        $this->assertSame('notreviewed', $record->reviewstatus);
        $this->assertSame('pending', $record->analysisstatus);
        $actions = array_column(history::get('resource', $id), 'action');
        $this->assertContains('review', $actions);
        $this->assertContains('reopened', $actions);
    }
}
