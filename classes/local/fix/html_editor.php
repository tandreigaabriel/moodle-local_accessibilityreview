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

use DOMElement;
use local_accessibilityreview\local\checks\html_checker;

/**
 * Targeted edits to stored HTML.
 *
 * Edits are made to the original text, touching only the tags being fixed,
 * instead of re-serialising a parsed DOM (which would reformat the whole
 * field and could alter filter syntax, entities or whitespace). Every edit is
 * located by element type and position, and confirmed against the parsed
 * element before it is applied.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class html_editor {
    /** @var string Pattern fragment matching the inside of a start tag, respecting quoted values. */
    private const TAGBODY = '(?:[^>"\']|"[^"]*"|\'[^\']*\')*';

    /**
     * Locate the nth start tag of an element type, in the same order a DOM parser would number them.
     *
     * Comments and the contents of script, style and textarea elements are skipped.
     *
     * @param string $html
     * @param string $tag Lower-case tag name.
     * @param int $ordinal Zero-based position among elements of this type.
     * @return array{0: int, 1: int}|null [byte offset, byte length] or null when not found.
     */
    public static function find_start_tag(string $html, string $tag, int $ordinal): ?array {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $tag)) {
            return null;
        }
        $pattern = '~<!--.*?-->|<(script|style|textarea)\b' . self::TAGBODY . '>.*?</\1\s*>|<' . $tag .
            '(?=[\s/>])' . self::TAGBODY . '>~is';
        if (!preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return null;
        }
        $index = 0;
        foreach ($matches as $match) {
            if (str_starts_with($match[0][0], '<!--') || (isset($match[1]) && $match[1][1] >= 0 && $match[1][0] !== '')) {
                continue;
            }
            if ($index === $ordinal) {
                return [$match[0][1], strlen($match[0][0])];
            }
            $index++;
        }
        return null;
    }

    /**
     * Attributes of a start tag, as parsed by the DOM parser.
     *
     * @param string $starttag
     * @return array<string, string>|null
     */
    public static function attributes(string $starttag): ?array {
        if (!preg_match('~^<([a-z][a-z0-9]*)~i', $starttag, $m)) {
            return null;
        }
        $doc = html_checker::load_dom($starttag);
        $el = $doc ? $doc->getElementsByTagName(strtolower($m[1]))->item(0) : null;
        if (!$el instanceof DOMElement) {
            return null;
        }
        return self::element_attributes($el);
    }

    /**
     * Attributes of a DOM element.
     *
     * @param DOMElement $el
     * @return array<string, string>
     */
    public static function element_attributes(DOMElement $el): array {
        $out = [];
        foreach ($el->attributes as $attr) {
            $out[strtolower($attr->name)] = $attr->value;
        }
        ksort($out);
        return $out;
    }

    /**
     * Split a start tag into its name and attribute tokens.
     *
     * @param string $starttag
     * @return array{name: string, namelength: int, attrs: array, end: int}|null
     */
    private static function tokenise(string $starttag): ?array {
        if (!preg_match('~^<([a-zA-Z][a-zA-Z0-9]*)~', $starttag, $m)) {
            return null;
        }
        $pos = strlen($m[0]);
        $attrs = [];
        while (preg_match('~\G(\s+)([^\s"\'>/=]+)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+))?~', $starttag, $am, 0, $pos)) {
            $attrs[] = ['name' => strtolower($am[2]), 'start' => $pos, 'length' => strlen($am[0])];
            $pos += strlen($am[0]);
        }
        return ['name' => strtolower($m[1]), 'namelength' => strlen($m[0]), 'attrs' => $attrs, 'end' => $pos];
    }

    /**
     * Set (add or replace) an attribute on a start tag.
     *
     * @param string $starttag
     * @param string $name
     * @param string $value Plain text; it is escaped here.
     * @return string
     */
    public static function set_attribute(string $starttag, string $name, string $value): string {
        $tokens = self::tokenise($starttag);
        if (!$tokens) {
            return $starttag;
        }
        $new = ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        foreach ($tokens['attrs'] as $attr) {
            if ($attr['name'] === strtolower($name)) {
                return substr($starttag, 0, $attr['start']) . $new . substr($starttag, $attr['start'] + $attr['length']);
            }
        }
        return substr($starttag, 0, $tokens['end']) . $new . substr($starttag, $tokens['end']);
    }

    /**
     * Remove every occurrence of an attribute from a start tag.
     *
     * @param string $starttag
     * @param string $name
     * @return string
     */
    public static function remove_attribute(string $starttag, string $name): string {
        $tokens = self::tokenise($starttag);
        if (!$tokens) {
            return $starttag;
        }
        foreach (array_reverse($tokens['attrs']) as $attr) {
            if ($attr['name'] === strtolower($name)) {
                $starttag = substr($starttag, 0, $attr['start']) . substr($starttag, $attr['start'] + $attr['length']);
            }
        }
        return $starttag;
    }

    /**
     * Change the element name of a start tag, keeping its attributes.
     *
     * @param string $starttag
     * @param string $newname
     * @return string
     */
    public static function rename_start_tag(string $starttag, string $newname): string {
        $tokens = self::tokenise($starttag);
        if (!$tokens) {
            return $starttag;
        }
        return '<' . $newname . substr($starttag, $tokens['namelength']);
    }

    /**
     * Position of the first end tag of a type at or after an offset.
     *
     * @param string $html
     * @param string $tag
     * @param int $from
     * @return array{0: int, 1: int}|null
     */
    public static function find_end_tag(string $html, string $tag, int $from): ?array {
        if (preg_match('~</' . preg_quote($tag, '~') . '\s*>~i', $html, $m, PREG_OFFSET_CAPTURE, $from)) {
            return [$m[0][1], strlen($m[0][0])];
        }
        return null;
    }

    /**
     * Replace a byte range.
     *
     * @param string $html
     * @param int $offset
     * @param int $length
     * @param string $replacement
     * @return string
     */
    public static function splice(string $html, int $offset, int $length, string $replacement): string {
        return substr($html, 0, $offset) . $replacement . substr($html, $offset + $length);
    }

    /**
     * Rename an element whose end tag is the next end tag of the same type (headings, links).
     *
     * @param string $html
     * @param int[] $start Start tag position: [offset, length].
     * @param string $oldname
     * @param string $newname
     * @return string|null Null when the end tag cannot be found.
     */
    public static function rename_element(string $html, array $start, string $oldname, string $newname): ?string {
        $end = self::find_end_tag($html, $oldname, $start[0] + $start[1]);
        if (!$end) {
            return null;
        }
        // Replace the end tag first so the start offset stays valid.
        $html = self::splice($html, $end[0], $end[1], '</' . $newname . '>');
        $starttag = substr($html, $start[0], $start[1]);
        return self::splice($html, $start[0], $start[1], self::rename_start_tag($starttag, $newname));
    }

    /**
     * Turn data cells into header cells: the first row (column headers) and/or the first cell of each row (row headers).
     *
     * @param string $html
     * @param int[] $start Position of the table's start tag: [offset, length].
     * @param bool $firstrow
     * @param bool $firstcolumn
     * @return string|null Null for nested tables or when the table end cannot be found.
     */
    public static function add_table_headers(string $html, array $start, bool $firstrow, bool $firstcolumn): ?string {
        $end = self::find_end_tag($html, 'table', $start[0] + $start[1]);
        if (!$end) {
            return null;
        }
        $bodystart = $start[0] + $start[1];
        $body = substr($html, $bodystart, $end[0] - $bodystart);
        if (preg_match('~<table[\s>]~i', $body)) {
            return null; // Nested tables are not edited automatically.
        }
        $cell = '~<td(?=[\s/>])(' . self::TAGBODY . ')>(.*?)</td\s*>~is';
        $rowindex = 0;
        $body = preg_replace_callback(
            '~(<tr(?=[\s>])' . self::TAGBODY . '>)(.*?)(</tr\s*>)~is',
            function (array $row) use ($cell, $firstrow, $firstcolumn, &$rowindex): string {
                $content = $row[2];
                if ($firstrow && $rowindex === 0) {
                    $content = preg_replace_callback($cell, fn($c) => self::header_cell($c, 'col'), $content);
                } else if ($firstcolumn) {
                    // Only when the row's first cell is a data cell.
                    if (preg_match('~^\s*<td[\s/>]~i', $content)) {
                        $content = preg_replace_callback($cell, fn($c) => self::header_cell($c, 'row'), $content, 1);
                    }
                }
                $rowindex++;
                return $row[1] . $content . $row[3];
            },
            $body
        );
        if ($body === null) {
            return null;
        }
        return substr($html, 0, $bodystart) . $body . substr($html, $end[0]);
    }

    /**
     * Rebuild one matched data cell as a header cell.
     *
     * @param array $match Regex match: [0] whole cell, [1] attributes, [2] content.
     * @param string $scope col or row.
     * @return string
     */
    private static function header_cell(array $match, string $scope): string {
        $starttag = self::set_attribute('<th' . $match[1] . '>', 'scope', $scope);
        return $starttag . $match[2] . '</th>';
    }
}
