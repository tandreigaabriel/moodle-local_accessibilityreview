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

namespace local_accessibilityreview\local\checks;

/**
 * A single issue detected in an HTML fragment.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue {
    /**
     * Constructor.
     *
     * @param string $checkid Check identifier from check_registry.
     * @param string $signature Stable, position-independent description of the element.
     * @param string $excerpt Safe plain-text excerpt (escaped again on output).
     * @param array $params Extra details (for example contrast ratio).
     */
    public function __construct(
        /** @var string Check identifier. */
        public readonly string $checkid,
        /** @var string Element signature used for stable identity. */
        public string $signature,
        /** @var string Plain-text excerpt. */
        public readonly string $excerpt,
        /** @var array Extra parameters. */
        public readonly array $params = [],
    ) {
    }

    /** @var string Lower-case name of the element the issue is about ('' when not an element). */
    public string $tag = '';

    /** @var int Zero-based position of the element among elements of the same type in the fragment, or -1. */
    public int $ordinal = -1;
}
