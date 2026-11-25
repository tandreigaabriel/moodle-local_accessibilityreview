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

use local_accessibilityreview\local\access;
use local_accessibilityreview\local\content\provider_registry;
use local_accessibilityreview\local\csv_safe;
use local_accessibilityreview\local\findings_filter;
use local_accessibilityreview\local\scanner;

/**
 * Capability defaults, server-side access checks and export safety.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibilityreview\local\access
 * @covers     \local_accessibilityreview\local\csv_safe
 * @covers     \local_accessibilityreview\local\findings_filter
 */
final class permissions_test extends \advanced_testcase {
    /**
     * Default role capabilities.
     */
    public function test_default_capabilities(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $other = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'teacher');
        $editor = $gen->create_and_enrol($course, 'editingteacher');

        $this->setUser($student);
        $this->assertFalse(access::can_view_course($course->id));
        $this->assertFalse(access::can_scan_course($course->id));
        $this->assertFalse(access::can_review_course($course->id));
        $this->assertFalse(access::can_view_site());

        $this->setUser($teacher);
        $this->assertTrue(access::can_view_course($course->id));
        $this->assertFalse(access::can_scan_course($course->id));
        $this->assertFalse(access::can_review_course($course->id));

        $this->setUser($editor);
        $this->assertTrue(access::can_view_course($course->id));
        $this->assertTrue(access::can_scan_course($course->id));
        $this->assertTrue(access::can_review_course($course->id));
        // No access to a course they do not teach, nor to the site report.
        $this->assertFalse(access::can_view_course($other->id));
        $this->assertFalse(access::can_view_site());

        $this->setAdminUser();
        $this->assertTrue(access::can_view_site());
    }

    /**
     * Students are refused by the server-side check used by every page and export.
     */
    public function test_require_course_view_blocks_student(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        access::require_course_view($course->id);
    }

    /**
     * Course filters never return another course's findings.
     */
    public function test_filter_scoped_to_course(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $c1 = $gen->create_course();
        $c2 = $gen->create_course();
        foreach ([$c1, $c2] as $course) {
            $gen->create_module('page', ['course' => $course->id, 'content' => '<img src="x.png">',
                'contentformat' => FORMAT_HTML]);
            scanner::run_now(scanner::queue($course->id, 0)['scanid']);
        }
        $filter = new findings_filter();
        $filter->courseid = $c1->id;
        $filter->state = 'all';
        $this->assertGreaterThan(0, $filter->count());
        foreach ($filter->get() as $finding) {
            $this->assertEquals($c1->id, $finding->courseid);
        }
    }

    /**
     * Edit links are only offered to users who may edit the source.
     */
    public function test_edit_links_respect_capabilities(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $teacher = $gen->create_and_enrol($course, 'teacher');
        $editor = $gen->create_and_enrol($course, 'editingteacher');
        $provider = provider_registry::get('page_content');

        $this->setUser($teacher);
        $this->assertNull($provider->get_edit_url($course->id, $page->cmid, $page->id));
        $this->assertNotNull($provider->get_view_url($course->id, $page->cmid, $page->id));
        $this->setUser($editor);
        $this->assertNotNull($provider->get_edit_url($course->id, $page->cmid, $page->id));
    }

    /**
     * CSV cells that could be interpreted as formulas are neutralised.
     */
    public function test_csv_formula_injection(): void {
        $this->assertSame("'=HYPERLINK(\"http://evil\")", csv_safe::cell('=HYPERLINK("http://evil")'));
        $this->assertSame("'+1+1", csv_safe::cell('+1+1'));
        $this->assertSame("'-2", csv_safe::cell('-2'));
        $this->assertSame("'@SUM(A1)", csv_safe::cell('@SUM(A1)'));
        $this->assertSame("' =1", csv_safe::cell(' =1'));
        $this->assertSame("'\tx", csv_safe::cell("\tx"));
        $this->assertSame('Plain text', csv_safe::cell('Plain text'));
        $this->assertSame('a b', csv_safe::cell("a\rb"));
    }
}
