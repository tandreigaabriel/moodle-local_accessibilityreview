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

use local_accessibilityreview\local\fixer;
use local_accessibilityreview\task\cleanup;

/**
 * Cleanup of the content copies kept for undo.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibilityreview\task\cleanup
 */
final class cleanup_test extends \advanced_testcase {
    /**
     * Insert a fix record with a content copy.
     *
     * @param int $courseid
     * @param int $cmid
     * @param string $provider
     * @param int $itemid
     * @param string $field
     * @return int
     */
    private function fix(int $courseid, int $cmid, string $provider, int $itemid, string $field): int {
        global $DB;
        return (int) $DB->insert_record(fixer::TABLE, (object) ['courseid' => $courseid, 'cmid' => $cmid,
            'checkid' => 'img_alt_missing', 'provider' => $provider, 'itemid' => $itemid, 'field' => $field,
            'itemlabel' => 'Item', 'oldcontent' => '<p>Text written by a student</p>', 'newcontent' => '<p>New</p>',
            'userid' => 2, 'timecreated' => time()]);
    }

    /**
     * Copies are deleted with their content, kept while it exists.
     */
    public function test_copies_of_deleted_content_are_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('rescanonedit', 0, 'local_accessibilityreview');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $glossary = $gen->create_module('glossary', ['course' => $course->id]);
        $entry = $gen->get_plugin_generator('mod_glossary')->create_content($glossary, ['definition' => '<p>Mine</p>']);
        $forum = $gen->create_module('forum', ['course' => $course->id]);

        $keptpage = $this->fix($course->id, $page->cmid, 'page_content', $page->id, 'content');
        $keptentry = $this->fix($course->id, $glossary->cmid, 'glossary_entry', $entry->id, 'definition');
        $keptintro = $this->fix($course->id, $forum->cmid, 'activity_intro', $forum->id, 'intro');
        $deletedentry = $this->fix($course->id, $glossary->cmid, 'glossary_entry', $entry->id + 1000, 'definition');
        $deletedpage = $this->fix($course->id, $page->cmid, 'page_content', $page->id + 1000, 'content');
        $deletedcm = $this->fix($course->id, $forum->cmid + 1000, 'activity_intro', $forum->id + 1000, 'intro');

        ob_start();
        (new cleanup())->execute();
        ob_end_clean();

        foreach ([$keptpage, $keptentry, $keptintro] as $id) {
            $this->assertTrue($DB->record_exists(fixer::TABLE, ['id' => $id]));
        }
        foreach ([$deletedentry, $deletedpage, $deletedcm] as $id) {
            $this->assertFalse($DB->record_exists(fixer::TABLE, ['id' => $id]));
        }
    }
}
