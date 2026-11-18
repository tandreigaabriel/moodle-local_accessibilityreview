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

namespace local_accessibilityreview\local\fix;

/**
 * A fix could not be applied safely. The error code maps to the string fix_error_{code}.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fix_exception extends \moodle_exception {
    /** @var string Short error code. */
    public string $reason;

    /**
     * Constructor.
     *
     * @param string $reason Short error code, for example "mismatch".
     */
    public function __construct(string $reason) {
        $this->reason = $reason;
        parent::__construct('fix_error_' . $reason, 'local_accessibilityreview');
    }
}
