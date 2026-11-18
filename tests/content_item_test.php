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

use local_accessibilityreview\local\content\content_item;

/**
 * Conversion of every Moodle text format before checking.
 *
 * Moodle defines the FORMAT_* constants as strings, while stored formats are read as integers.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibilityreview\local\content\content_item
 */
final class content_item_test extends \advanced_testcase {
    /**
     * Every FORMAT_* value, as stored in the database.
     *
     * @return array
     */
    public static function format_provider(): array {
        return [
            'moodle' => [(int) FORMAT_MOODLE, '<p>Text</p>', '<p>Text</p>'],
            'html' => [(int) FORMAT_HTML, '<p>Text</p>', '<p>Text</p>'],
            'plain' => [(int) FORMAT_PLAIN, '<img src="a.png">', null],
            'wiki' => [(int) FORMAT_WIKI, '<p>Text</p>', '<p>Text</p>'],
            'markdown' => [(int) FORMAT_MARKDOWN, '# Title', "<h1>Title</h1>\n"],
        ];
    }

    /**
     * Each format produces the HTML that is actually shown to learners.
     *
     * @dataProvider format_provider
     * @param int $format
     * @param string $text
     * @param string|null $expected
     */
    public function test_to_html_for_every_format(int $format, string $text, ?string $expected): void {
        $this->assertSame($expected, content_item::to_html($text, $format));
    }
}
