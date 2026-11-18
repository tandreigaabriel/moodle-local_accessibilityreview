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
 * Colour parsing and WCAG 2.x contrast ratio calculation.
 *
 * Only colours that can be determined unambiguously are parsed. Anything else
 * (CSS variables, gradients, currentColor, semi-transparent colours, unknown
 * names) returns null so callers can treat contrast as undeterminable instead
 * of guessing.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contrast {
    /** @var string Marker returned for the transparent keyword. */
    public const TRANSPARENT = 'transparent';

    /** @var array<string, string> Named colours supported (CSS basic + common extended). */
    private const NAMED = [
        'black' => '000000', 'silver' => 'c0c0c0', 'gray' => '808080', 'grey' => '808080',
        'white' => 'ffffff', 'maroon' => '800000', 'red' => 'ff0000', 'purple' => '800080',
        'fuchsia' => 'ff00ff', 'magenta' => 'ff00ff', 'green' => '008000', 'lime' => '00ff00',
        'olive' => '808000', 'yellow' => 'ffff00', 'navy' => '000080', 'blue' => '0000ff',
        'teal' => '008080', 'aqua' => '00ffff', 'cyan' => '00ffff', 'orange' => 'ffa500',
        'darkgray' => 'a9a9a9', 'darkgrey' => 'a9a9a9', 'lightgray' => 'd3d3d3', 'lightgrey' => 'd3d3d3',
        'dimgray' => '696969', 'dimgrey' => '696969', 'gainsboro' => 'dcdcdc', 'whitesmoke' => 'f5f5f5',
        'darkred' => '8b0000', 'darkblue' => '00008b', 'darkgreen' => '006400', 'lightblue' => 'add8e6',
        'lightgreen' => '90ee90', 'lightyellow' => 'ffffe0', 'pink' => 'ffc0cb', 'gold' => 'ffd700',
        'brown' => 'a52a2a', 'beige' => 'f5f5dc', 'ivory' => 'fffff0', 'khaki' => 'f0e68c',
        'lavender' => 'e6e6fa', 'salmon' => 'fa8072', 'tomato' => 'ff6347', 'crimson' => 'dc143c',
        'coral' => 'ff7f50', 'indigo' => '4b0082', 'violet' => 'ee82ee', 'orchid' => 'da70d6',
        'plum' => 'dda0dd', 'tan' => 'd2b48c', 'skyblue' => '87ceeb', 'lightskyblue' => '87cefa',
        'steelblue' => '4682b4', 'royalblue' => '4169e1', 'dodgerblue' => '1e90ff', 'darkorange' => 'ff8c00',
        'lightgoldenrodyellow' => 'fafad2', 'lightcyan' => 'e0ffff', 'mintcream' => 'f5fffa',
        'aliceblue' => 'f0f8ff', 'ghostwhite' => 'f8f8ff', 'snow' => 'fffafa', 'seashell' => 'fff5ee',
        'linen' => 'faf0e6', 'honeydew' => 'f0fff0', 'azure' => 'f0ffff', 'lemonchiffon' => 'fffacd',
        'cornsilk' => 'fff8dc', 'lightpink' => 'ffb6c1', 'hotpink' => 'ff69b4', 'yellowgreen' => '9acd32',
        'limegreen' => '32cd32', 'forestgreen' => '228b22', 'seagreen' => '2e8b57', 'midnightblue' => '191970',
        'slategray' => '708090', 'slategrey' => '708090', 'lightslategray' => '778899', 'darkslategray' => '2f4f4f',
    ];

    /**
     * Parse a CSS colour value.
     *
     * @param string $value Raw CSS value.
     * @return array|string|null [r, g, b] ints, self::TRANSPARENT, or null when undeterminable.
     */
    public static function parse(string $value) {
        $value = strtolower(trim(preg_replace('/\s*!important\s*$/i', '', $value)));
        if ($value === '') {
            return null;
        }
        if ($value === 'transparent') {
            return self::TRANSPARENT;
        }
        if (isset(self::NAMED[$value])) {
            return self::hex_to_rgb(self::NAMED[$value]);
        }
        if (preg_match('/^#([0-9a-f]{3,8})$/', $value, $m)) {
            $hex = $m[1];
            $len = strlen($hex);
            if ($len === 3 || $len === 4) {
                if ($len === 4 && $hex[3] !== 'f') {
                    return null; // Partial transparency: cannot determine rendered colour.
                }
                return self::hex_to_rgb($hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]);
            }
            if ($len === 6) {
                return self::hex_to_rgb($hex);
            }
            if ($len === 8) {
                if (substr($hex, 6, 2) !== 'ff') {
                    return null;
                }
                return self::hex_to_rgb(substr($hex, 0, 6));
            }
            return null;
        }
        if (preg_match('/^rgba?\(\s*([^)]*)\)$/', $value, $m)) {
            $parts = preg_split('/\s*[,\/]\s*|\s+/', trim($m[1]));
            if (count($parts) < 3 || count($parts) > 4) {
                return null;
            }
            if (count($parts) === 4) {
                $alpha = $parts[3];
                $alphavalue = str_ends_with($alpha, '%') ? ((float) $alpha) / 100 : (float) $alpha;
                if (!is_numeric(rtrim($alpha, '%')) || $alphavalue < 1) {
                    return null;
                }
            }
            $rgb = [];
            for ($i = 0; $i < 3; $i++) {
                $part = $parts[$i];
                if (str_ends_with($part, '%')) {
                    $num = rtrim($part, '%');
                    if (!is_numeric($num)) {
                        return null;
                    }
                    $rgb[] = (int) round(max(0, min(100, (float) $num)) * 2.55);
                } else {
                    if (!is_numeric($part)) {
                        return null;
                    }
                    $rgb[] = (int) max(0, min(255, round((float) $part)));
                }
            }
            return $rgb;
        }
        return null;
    }

    /**
     * Convert a six-digit hex string to RGB.
     *
     * @param string $hex Hex without hash.
     * @return array [r, g, b]
     */
    private static function hex_to_rgb(string $hex): array {
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * Relative luminance as defined by WCAG 2.x.
     *
     * @param array $rgb [r, g, b]
     * @return float
     */
    public static function luminance(array $rgb): float {
        $channels = [];
        foreach ($rgb as $c) {
            $s = $c / 255;
            $channels[] = ($s <= 0.03928) ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Contrast ratio between two colours.
     *
     * @param array $a [r, g, b]
     * @param array $b [r, g, b]
     * @return float Ratio rounded to two decimals (1.0 - 21.0).
     */
    public static function ratio(array $a, array $b): float {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        $lighter = max($la, $lb);
        $darker = min($la, $lb);
        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    /**
     * Format RGB as a hex string for display.
     *
     * @param array $rgb [r, g, b]
     * @return string
     */
    public static function to_hex(array $rgb): string {
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }
}
