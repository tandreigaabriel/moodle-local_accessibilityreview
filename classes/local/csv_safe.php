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

namespace local_accessibilityreview\local;

/**
 * Spreadsheet formula-injection protection for CSV exports.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_safe {
    /**
     * Neutralise a cell value so spreadsheet software treats it as text.
     *
     * Values whose first non-whitespace character is =, +, -, @, |, or that
     * start with a tab or carriage return, are prefixed with an apostrophe.
     * Control characters other than newline are removed.
     *
     * @param mixed $value
     * @return string
     */
    public static function cell($value): string {
        $value = (string) $value;
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', str_replace("\r", ' ', $value));
        if ($value !== '' && preg_match('/^[\s]*[=+\-@|]/', $value)) {
            return "'" . $value;
        }
        if ($value !== '' && ($value[0] === "\t")) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Apply cell() to every value in a row.
     *
     * @param array $row
     * @return string[]
     */
    public static function row(array $row): array {
        return array_map([self::class, 'cell'], array_values($row));
    }
}
