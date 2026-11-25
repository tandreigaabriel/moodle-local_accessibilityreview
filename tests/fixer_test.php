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
use local_accessibilityreview\local\fix\fix_exception;
use local_accessibilityreview\local\fixer;
use local_accessibilityreview\local\progress;
use local_accessibilityreview\local\scanner;

/**
 * Fixing content, undo, progress snapshots and the extra content areas.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibilityreview\local\fixer
 * @covers     \local_accessibilityreview\local\progress
 */
final class fixer_test extends \advanced_testcase {
    /**
     * Queue and synchronously run a scan.
     *
     * @param int $courseid
     * @return int
     */
    private function scan(int $courseid): int {
        $result = scanner::queue($courseid, 0);
        scanner::run_now($result['scanid']);
        return $result['scanid'];
    }

    /**
     * A course with one page, scanned, with fixes enabled.
     *
     * @param string $content
     * @return array{0: \stdClass, 1: \stdClass}
     */
    private function setup_page(string $content): array {
        $this->resetAfterTest();
        set_config('enablefixes', 1, 'local_accessibilityreview');
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => $content,
            'contentformat' => FORMAT_HTML]);
        $this->scan($course->id);
        return [$course, $page];
    }

    /**
     * Finding for the page.
     *
     * @param int $pageid
     * @param string $checkid
     * @return \stdClass
     */
    private function finding(int $pageid, string $checkid): \stdClass {
        global $DB;
        return $DB->get_record(finding_manager::TABLE, ['provider' => 'page_content', 'itemid' => $pageid,
            'checkid' => $checkid], '*', MUST_EXIST);
    }

    /**
     * Applying a fix changes only the element, resolves the finding at once, and can be undone.
     */
    public function test_fix_and_undo(): void {
        global $DB;
        $content = '<p>Intro {mlang en}x{mlang}</p><img src="@@PLUGINFILE@@/a.png"><img src="b.png" alt="B">';
        [, $page] = $this->setup_page($content);
        $this->setAdminUser();

        $finding = $this->finding($page->id, 'img_alt_missing');
        $revision = (int) $DB->get_field('page', 'revision', ['id' => $page->id]);
        $fixid = fixer::apply($finding, ['text' => 'Needle angle diagram'], 2);

        $stored = $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertSame('<p>Intro {mlang en}x{mlang}</p><img src="@@PLUGINFILE@@/a.png" alt="Needle angle diagram">' .
            '<img src="b.png" alt="B">', $stored);
        $this->assertEquals($revision + 1, $DB->get_field('page', 'revision', ['id' => $page->id]));
        $after = $this->finding($page->id, 'img_alt_missing');
        $this->assertEquals(0, $after->present);
        $this->assertSame(finding_manager::STATE_RESOLVED, $after->state);

        $fix = $DB->get_record(fixer::TABLE, ['id' => $fixid], '*', MUST_EXIST);
        $this->assertSame($content, $fix->oldcontent);
        fixer::undo($fix, 2);
        $this->assertSame($content, $DB->get_field('page', 'content', ['id' => $page->id]));
        $this->assertSame('undone', $DB->get_field(fixer::TABLE, 'status', ['id' => $fixid]));
        $reopened = $this->finding($page->id, 'img_alt_missing');
        $this->assertEquals(1, $reopened->present);
        $this->assertSame(finding_manager::STATE_OPEN, $reopened->state);
    }

    /**
     * Undo is refused once the content has been edited by someone else.
     */
    public function test_undo_refused_after_edit(): void {
        global $DB;
        [, $page] = $this->setup_page('<iframe src="https://player.vimeo.com/video/1"></iframe>');
        $this->setAdminUser();
        $fixid = fixer::apply($this->finding($page->id, 'iframe_title_missing'), ['text' => 'Video: Intro'], 2);
        $DB->set_field('page', 'content', '<p>Rewritten by a teacher</p>', ['id' => $page->id]);
        try {
            fixer::undo($DB->get_record(fixer::TABLE, ['id' => $fixid]), 2);
            $this->fail('Expected fix_exception');
        } catch (fix_exception $e) {
            $this->assertSame('undochanged', $e->reason);
        }
        $this->assertSame('<p>Rewritten by a teacher</p>', $DB->get_field('page', 'content', ['id' => $page->id]));
    }

    /**
     * Fixes need the setting, the fix capability and the content's own editing capability.
     */
    public function test_permissions(): void {
        [$course, $page] = $this->setup_page('<img src="a.png">');
        $finding = $this->finding($page->id, 'img_alt_missing');

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->assertSame('nopermission', fixer::why_not($finding, (int) $student->id));
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->assertNull(fixer::why_not($finding, (int) $teacher->id));

        set_config('enablefixes', 0, 'local_accessibilityreview');
        $this->assertSame('disabled', fixer::why_not($finding, (int) $teacher->id));
    }

    /**
     * Content edited after the scan is detected; a moved element is still found by its identity.
     */
    public function test_content_changed(): void {
        global $DB;
        [, $page] = $this->setup_page('<img src="a.png">');
        $finding = $this->finding($page->id, 'img_alt_missing');
        $DB->set_field('page', 'content', '<p>New</p><img src="x.png" alt="X"><img src="a.png">', ['id' => $page->id]);
        fixer::apply($finding, ['text' => 'A'], 2);
        $this->assertSame(
            '<p>New</p><img src="x.png" alt="X"><img src="a.png" alt="A">',
            $DB->get_field('page', 'content', ['id' => $page->id])
        );

        $DB->set_field('page', 'content', '<p>Image removed</p>', ['id' => $page->id]);
        $other = $this->finding($page->id, 'img_alt_missing');
        $DB->set_field(finding_manager::TABLE, 'present', 1, ['id' => $other->id]);
        $DB->set_field(finding_manager::TABLE, 'state', finding_manager::STATE_OPEN, ['id' => $other->id]);
        try {
            fixer::apply($DB->get_record(finding_manager::TABLE, ['id' => $other->id]), ['text' => 'A'], 2);
            $this->fail('Expected fix_exception');
        } catch (fix_exception $e) {
            $this->assertSame('changed', $e->reason);
        }
    }

    /**
     * A completed scan records a progress snapshot.
     */
    public function test_progress_snapshot(): void {
        [$course] = $this->setup_page('<img src="a.png"><iframe src="https://example.com/x"></iframe>');
        $history = progress::history($course->id);
        $this->assertCount(1, $history);
        $this->assertEquals(2, $history[0]->errors);
        $this->scan($course->id);
        $this->assertCount(2, progress::history($course->id));
        $this->assertSame(['errors' => 0, 'warnings' => 0, 'notices' => 0], progress::change(progress::history($course->id)));
    }

    /**
     * Glossary entries and lesson pages are scanned; quiz questions are scanned but never fixable.
     */
    public function test_extra_content_areas(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest();
        set_config('enablefixes', 1, 'local_accessibilityreview');
        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $course->id]);
        $entry = $this->getDataGenerator()->get_plugin_generator('mod_glossary')->create_content(
            $glossary,
            ['definition' => '<img src="g.png">', 'definitionformat' => FORMAT_HTML]
        );

        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => \context_module::instance($quiz->cmid)->id]);
        $question = $questiongenerator->create_question('description', null, ['category' => $category->id,
            'questiontext' => ['text' => '<img src="q.png">', 'format' => FORMAT_HTML]]);
        quiz_add_quiz_question($question->id, $quiz);

        $this->scan($course->id);
        $this->assertTrue($DB->record_exists(finding_manager::TABLE, ['provider' => 'glossary_entry', 'itemid' => $entry->id,
            'checkid' => 'img_alt_missing']));
        $qfinding = $DB->get_record(finding_manager::TABLE, ['provider' => 'quiz_question', 'itemid' => $question->id,
            'checkid' => 'img_alt_missing'], '*', MUST_EXIST);
        $this->setAdminUser();
        $this->assertSame('notsupported', fixer::why_not($qfinding));
    }
}
