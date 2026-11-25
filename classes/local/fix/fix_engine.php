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

use local_accessibilityreview\local\checks\check_registry;
use local_accessibilityreview\local\checks\html_checker;
use local_accessibilityreview\local\checks\issue;

/**
 * Computes the corrected HTML for one finding. No database access.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fix_engine {
    /** @var int Maximum length of alternative text or a title entered in a fix. */
    public const MAX_TEXT = 250;

    /**
     * Apply a fix.
     *
     * @param string $html Current stored HTML of the field.
     * @param issue $issue The issue to fix (identified by check id and signature).
     * @param array $data Fix input: text, decorative, headers (row|column|both), level, mode (sametab|label), label.
     * @param html_checker|null $checker Checker configured as for scanning; defaults to the standard settings.
     * @return string Corrected HTML.
     * @throws fix_exception When the fix cannot be applied safely.
     */
    public static function apply(string $html, issue $issue, array $data, ?html_checker $checker = null): string {
        if (!in_array($issue->checkid, check_registry::fixable(), true)) {
            throw new fix_exception('notfixable');
        }
        // Re-detect the issue in exactly this HTML, so the element position is never stale.
        $current = null;
        foreach (($checker ?? new html_checker())->check($html) as $candidate) {
            if ($candidate->checkid === $issue->checkid && $candidate->signature === $issue->signature) {
                $current = $candidate;
                break;
            }
        }
        if (!$current) {
            throw new fix_exception('changed');
        }
        $issue = $current;
        $start = self::locate($html, $issue);
        $starttag = substr($html, $start[0], $start[1]);

        switch ($issue->checkid) {
            case 'img_alt_missing':
            case 'img_alt_suspicious':
            case 'img_alt_empty':
                if (!empty($data['decorative'])) {
                    $starttag = html_editor::set_attribute($starttag, 'alt', '');
                    $starttag = html_editor::set_attribute($starttag, 'role', 'presentation');
                } else {
                    $starttag = html_editor::set_attribute($starttag, 'alt', self::text($data));
                    $role = strtolower((string) (html_editor::attributes($starttag)['role'] ?? ''));
                    if ($role === 'presentation' || $role === 'none') {
                        $starttag = html_editor::remove_attribute($starttag, 'role');
                    }
                }
                return html_editor::splice($html, $start[0], $start[1], $starttag);

            case 'iframe_title_missing':
            case 'iframe_title_generic':
            case 'object_title_missing':
                $starttag = html_editor::set_attribute($starttag, 'title', self::text($data));
                return html_editor::splice($html, $start[0], $start[1], $starttag);

            case 'media_autoplay':
                return html_editor::splice($html, $start[0], $start[1], html_editor::remove_attribute($starttag, 'autoplay'));

            case 'link_new_window':
                if (($data['mode'] ?? '') === 'label') {
                    $label = trim((string) ($data['label'] ?? ''));
                    if ($label === '') {
                        throw new fix_exception('textrequired');
                    }
                    $end = html_editor::find_end_tag($html, 'a', $start[0] + $start[1]);
                    if (!$end) {
                        throw new fix_exception('notfound');
                    }
                    return html_editor::splice($html, $end[0], 0, ' ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8'));
                }
                $starttag = html_editor::remove_attribute($starttag, 'target');
                return html_editor::splice($html, $start[0], $start[1], $starttag);

            case 'table_no_headers':
                $headers = (string) ($data['headers'] ?? 'row');
                $result = html_editor::add_table_headers(
                    $html,
                    $start,
                    in_array($headers, ['row', 'both'], true),
                    in_array($headers, ['column', 'both'], true)
                );
                if ($result === null) {
                    throw new fix_exception('tablecomplex');
                }
                return $result;

            case 'heading_skip':
                $level = (int) ($data['level'] ?? 0);
                $current = (int) substr($issue->tag, 1);
                if ($level < 1 || $level > 6 || $current < 1) {
                    throw new fix_exception('invalidlevel');
                }
                $result = html_editor::rename_element($html, $start, 'h' . $current, 'h' . $level);
                if ($result === null) {
                    throw new fix_exception('notfound');
                }
                return $result;
        }
        throw new fix_exception('notfixable');
    }

    /**
     * Locate the issue's element in the HTML and confirm it is the element the checker reported.
     *
     * @param string $html
     * @param issue $issue
     * @return array{0: int, 1: int} Start tag position.
     * @throws fix_exception
     */
    public static function locate(string $html, issue $issue): array {
        if ($issue->tag === '' || $issue->ordinal < 0) {
            throw new fix_exception('notfound');
        }
        $start = html_editor::find_start_tag($html, $issue->tag, $issue->ordinal);
        $doc = html_checker::load_dom($html);
        $element = $doc ? $doc->getElementsByTagName($issue->tag)->item($issue->ordinal) : null;
        if (!$start || !$element instanceof \DOMElement) {
            throw new fix_exception('notfound');
        }
        $found = html_editor::attributes(substr($html, $start[0], $start[1]));
        if ($found === null || $found !== html_editor::element_attributes($element)) {
            throw new fix_exception('mismatch');
        }
        return $start;
    }

    /**
     * Validated text input (alternative text or title).
     *
     * @param array $data
     * @return string
     * @throws fix_exception
     */
    private static function text(array $data): string {
        $text = trim(preg_replace('/\s+/u', ' ', (string) ($data['text'] ?? '')));
        if ($text === '') {
            throw new fix_exception('textrequired');
        }
        if (\core_text::strlen($text) > self::MAX_TEXT) {
            throw new fix_exception('texttoolong');
        }
        return $text;
    }
}
