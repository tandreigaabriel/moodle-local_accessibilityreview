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
 * Definitions of all automated HTML checks.
 *
 * WCAG references are only listed where the mapping is accurate. Checks that
 * are good practice but not a WCAG failure in themselves (for example heading
 * level jumps) intentionally list no success criterion.
 *
 * Language strings per check: check_{id} (title), check_{id}_desc (explanation)
 * and check_{id}_fix (suggested correction).
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_registry {
    /** @var string Definite failure detected automatically. */
    public const SEVERITY_ERROR = 'error';
    /** @var string Probable problem. */
    public const SEVERITY_WARNING = 'warning';
    /** @var string Item worth confirming. */
    public const SEVERITY_NOTICE = 'notice';

    /** @var array<string, array> Check definitions. */
    private const CHECKS = [
        'img_alt_missing' => ['category' => 'images', 'severity' => 'error', 'manual' => false, 'wcag' => ['1.1.1']],
        'img_alt_empty' => ['category' => 'images', 'severity' => 'notice', 'manual' => true, 'wcag' => ['1.1.1']],
        'img_alt_suspicious' => ['category' => 'images', 'severity' => 'warning', 'manual' => true, 'wcag' => ['1.1.1']],
        'link_empty' => ['category' => 'links', 'severity' => 'error', 'manual' => false, 'wcag' => ['2.4.4', '4.1.2']],
        'link_unclear' => ['category' => 'links', 'severity' => 'warning', 'manual' => true, 'wcag' => ['2.4.4']],
        'link_url_text' => ['category' => 'links', 'severity' => 'notice', 'manual' => true, 'wcag' => []],
        'table_no_headers' => ['category' => 'tables', 'severity' => 'warning', 'manual' => true, 'wcag' => ['1.3.1']],
        'heading_skip' => ['category' => 'headings', 'severity' => 'warning', 'manual' => false, 'wcag' => []],
        'heading_empty' => ['category' => 'headings', 'severity' => 'warning', 'manual' => false, 'wcag' => []],
        'iframe_title_missing' => ['category' => 'frames', 'severity' => 'error', 'manual' => false, 'wcag' => ['4.1.2']],
        'iframe_title_generic' => ['category' => 'frames', 'severity' => 'notice', 'manual' => true, 'wcag' => ['4.1.2']],
        'contrast_fail' => ['category' => 'contrast', 'severity' => 'error', 'manual' => false, 'wcag' => ['1.4.3']],
        'contrast_review' => ['category' => 'contrast', 'severity' => 'warning', 'manual' => true, 'wcag' => ['1.4.3']],
        'link_new_window' => ['category' => 'links', 'severity' => 'notice', 'manual' => true, 'wcag' => ['3.2.5']],
        'area_alt_missing' => ['category' => 'images', 'severity' => 'error', 'manual' => false,
            'wcag' => ['1.1.1', '2.4.4']],
        'table_complex' => ['category' => 'tables', 'severity' => 'notice', 'manual' => true, 'wcag' => ['1.3.1']],
        'table_summary_duplicate' => ['category' => 'tables', 'severity' => 'notice', 'manual' => false, 'wcag' => []],
        'content_no_headings' => ['category' => 'structure', 'severity' => 'warning', 'manual' => true,
            'wcag' => ['1.3.1', '2.4.6']],
        'heading_visual' => ['category' => 'structure', 'severity' => 'notice', 'manual' => true, 'wcag' => ['1.3.1']],
        'list_manual' => ['category' => 'structure', 'severity' => 'notice', 'manual' => true, 'wcag' => ['1.3.1']],
        'legend_empty' => ['category' => 'structure', 'severity' => 'warning', 'manual' => false, 'wcag' => ['1.3.1']],
        'markup_deprecated' => ['category' => 'structure', 'severity' => 'notice', 'manual' => false, 'wcag' => []],
        'text_underline' => ['category' => 'text', 'severity' => 'notice', 'manual' => true, 'wcag' => []],
        'text_all_caps' => ['category' => 'text', 'severity' => 'notice', 'manual' => true, 'wcag' => []],
        'motion_blink' => ['category' => 'text', 'severity' => 'error', 'manual' => false, 'wcag' => ['2.2.2']],
        'object_title_missing' => ['category' => 'media', 'severity' => 'error', 'manual' => false,
            'wcag' => ['1.1.1', '4.1.2']],
        'media_autoplay' => ['category' => 'media', 'severity' => 'warning', 'manual' => false, 'wcag' => ['1.4.2']],
        'media_no_captions' => ['category' => 'media', 'severity' => 'warning', 'manual' => true, 'wcag' => ['1.2.2']],
    ];

    /** @var array<string, array{0: string, 1: string}> WCAG 2.2 criteria used by the checks: [level, Understanding page slug]. */
    private const CRITERIA = [
        '1.1.1' => ['A', 'non-text-content'],
        '1.2.2' => ['A', 'captions-prerecorded'],
        '1.3.1' => ['A', 'info-and-relationships'],
        '1.4.2' => ['A', 'audio-control'],
        '1.4.3' => ['AA', 'contrast-minimum'],
        '2.2.2' => ['A', 'pause-stop-hide'],
        '2.4.4' => ['A', 'link-purpose-in-context'],
        '2.4.6' => ['AA', 'headings-and-labels'],
        '3.2.5' => ['AAA', 'change-on-request'],
        '4.1.2' => ['A', 'name-role-value'],
    ];

    /**
     * Details of a WCAG success criterion referenced by a check.
     *
     * EN 301 549 (V3.2.1) clause 9 reproduces the WCAG 2.1 criteria for web content
     * with "9." in front of the WCAG number, so the clause is derived from the number.
     *
     * @param string $sc Success criterion number, for example "1.1.1".
     * @return array{sc: string, level: string, url: string, en301549: string}
     */
    public static function criterion(string $sc): array {
        [$level, $slug] = self::CRITERIA[$sc] ?? ['', ''];
        return [
            'sc' => $sc,
            'level' => $level,
            'url' => $slug !== '' ? 'https://www.w3.org/WAI/WCAG22/Understanding/' . $slug . '.html' : '',
            'en301549' => '9.' . $sc,
        ];
    }

    /**
     * Fixes that can be applied from the plugin, by check id.
     *
     * @return string[]
     */
    public static function fixable(): array {
        return ['img_alt_missing', 'img_alt_suspicious', 'img_alt_empty', 'iframe_title_missing', 'iframe_title_generic',
            'table_no_headers', 'heading_skip', 'link_new_window', 'media_autoplay', 'object_title_missing'];
    }

    /**
     * All check definitions.
     *
     * @return array<string, array>
     */
    public static function all(): array {
        return self::CHECKS;
    }

    /**
     * One check definition.
     *
     * @param string $checkid
     * @return array
     * @throws \coding_exception For an unknown check.
     */
    public static function get(string $checkid): array {
        if (!isset(self::CHECKS[$checkid])) {
            throw new \coding_exception('Unknown accessibility check: ' . $checkid);
        }
        return self::CHECKS[$checkid];
    }

    /**
     * Whether the check id exists.
     *
     * @param string $checkid
     * @return bool
     */
    public static function exists(string $checkid): bool {
        return isset(self::CHECKS[$checkid]);
    }

    /**
     * Distinct categories in display order.
     *
     * @return string[]
     */
    public static function categories(): array {
        return ['images', 'links', 'tables', 'headings', 'structure', 'text', 'frames', 'media', 'contrast'];
    }

    /**
     * Severities in descending order.
     *
     * @return string[]
     */
    public static function severities(): array {
        return [self::SEVERITY_ERROR, self::SEVERITY_WARNING, self::SEVERITY_NOTICE];
    }
}
