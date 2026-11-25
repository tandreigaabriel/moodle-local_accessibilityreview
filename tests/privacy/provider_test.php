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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_accessibilityreview\local\finding_manager;
use local_accessibilityreview\local\history;
use local_accessibilityreview\local\scanner;

/**
 * Privacy provider tests.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibilityreview\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Contexts, export and anonymisation.
     */
    public function test_export_and_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $reviewer = $gen->create_user();
        $gen->create_module('page', ['course' => $course->id, 'content' => '<img src="x.png">',
            'contentformat' => FORMAT_HTML]);
        scanner::run_now(scanner::queue($course->id, $reviewer->id)['scanid']);
        $finding = $DB->get_record(finding_manager::TABLE, ['courseid' => $course->id, 'checkid' => 'img_alt_missing']);
        finding_manager::set_state($finding, finding_manager::STATE_INPROGRESS, 'Personal note', $reviewer->id);

        $context = \context_course::instance($course->id);
        $contextlist = provider::get_contexts_for_userid($reviewer->id);
        $this->assertContains((int) $context->id, array_map('intval', $contextlist->get_contextids()));

        provider::export_user_data(new approved_contextlist($reviewer, 'local_accessibilityreview', [$context->id]));
        $this->assertTrue(writer::with_context($context)->has_any_data());

        provider::delete_data_for_user(new approved_contextlist($reviewer, 'local_accessibilityreview', [$context->id]));
        $this->assertFalse($DB->record_exists(history::TABLE, ['userid' => $reviewer->id]));
        $this->assertFalse($DB->record_exists(finding_manager::TABLE, ['reviewerid' => $reviewer->id]));
        $this->assertFalse($DB->record_exists(scanner::TABLE, ['requestedby' => $reviewer->id]));
        // The institutional finding record remains.
        $this->assertTrue($DB->record_exists(finding_manager::TABLE, ['id' => $finding->id]));
        $this->assertFalse($DB->record_exists_select(
            history::TABLE,
            $DB->sql_compare_text('note') . ' = :note',
            ['note' => 'Personal note']
        ));
    }
}
