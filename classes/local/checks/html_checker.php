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

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use local_accessibilityreview\local\links\vimeo;

/**
 * Runs automated accessibility checks over a stored HTML fragment.
 *
 * Works on stored content only. It does not render the page, apply filters or
 * evaluate theme CSS, so it cannot assess the accessibility of the theme or of
 * content generated at display time.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class html_checker {
    /** @var string Id of the wrapper element used when parsing fragments. */
    public const ROOT_ID = 'accreview-root';

    /** @var int Largest fragment (bytes) that will be parsed. */
    public const MAX_HTML_BYTES = 2097152;

    /** @var string[] Default link texts that need contextual review. */
    public const DEFAULT_UNCLEAR = [
        'click here', 'click', 'here', 'click this', 'read more', 'more', 'more info', 'more information',
        'link', 'this link', 'this', 'learn more', 'go', 'go here', 'see here', 'details', 'download', 'continue',
    ];

    /** @var string[] Default alt texts that are almost never descriptive. */
    public const DEFAULT_GENERIC_ALT = [
        'image', 'picture', 'photo', 'photograph', 'graphic', 'img', 'icon', 'placeholder', 'untitled',
        'spacer', 'blank', 'pic', 'screenshot', 'screen shot', 'alt', 'alt text', 'null', 'none',
    ];

    /** @var string[] Iframe titles that do not describe content. */
    private const GENERIC_IFRAME_TITLES = ['iframe', 'frame', 'embed', 'embedded content', 'video', 'video player',
        'untitled', 'title', 'content', 'player'];

    /** @var string[] Normalised unclear link phrases. */
    private array $unclear;

    /** @var string[] Normalised generic alt words. */
    private array $genericalt;

    /** @var array<string, int> Signature counters for the current fragment. */
    private array $seen = [];

    /**
     * Constructor.
     *
     * @param string[]|null $unclear Unclear link phrases, null for defaults.
     * @param string[]|null $genericalt Generic alt words, null for defaults.
     */
    public function __construct(?array $unclear = null, ?array $genericalt = null) {
        $this->unclear = array_map([self::class, 'normalise'], $unclear ?? self::DEFAULT_UNCLEAR);
        $this->genericalt = array_map([self::class, 'normalise'], $genericalt ?? self::DEFAULT_GENERIC_ALT);
    }

    /**
     * Build a checker from admin settings.
     *
     * @return self
     */
    public static function from_config(): self {
        $unclear = self::config_list((string) get_config('local_accessibilityreview', 'unclearlinktext'));
        $generic = self::config_list((string) get_config('local_accessibilityreview', 'genericalttext'));
        return new self($unclear ?: null, $generic ?: null);
    }

    /**
     * Split a newline/comma separated setting into a list.
     *
     * @param string $value
     * @return string[]
     */
    private static function config_list(string $value): array {
        $parts = preg_split('/[\r\n,]+/', $value);
        return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
    }

    /**
     * Lower-case, collapse whitespace and strip surrounding punctuation.
     *
     * @param string $text
     * @return string
     */
    public static function normalise(string $text): string {
        $text = \core_text::strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
        return trim($text, " \t\n\r\0\x0B.,:;!?…»«>\"'()[]-–—");
    }

    /**
     * Parse an HTML fragment into a DOM wrapped in a root div.
     *
     * Network access is disabled and libxml errors are suppressed.
     *
     * @param string $html
     * @return DOMDocument|null Null when the fragment is empty or cannot be parsed.
     */
    public static function load_dom(string $html): ?DOMDocument {
        if (trim($html) === '' || strlen($html) > self::MAX_HTML_BYTES) {
            return null;
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $ok = $doc->loadHTML(
            '<?xml encoding="utf-8"?><html><body><div id="' . self::ROOT_ID . '">' . $html . '</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $ok ? $doc : null;
    }

    /**
     * Run all checks over a fragment.
     *
     * @param string $html Stored HTML.
     * @return issue[]
     */
    public function check(string $html): array {
        $this->seen = [];
        $doc = self::load_dom($html);
        if (!$doc) {
            return [];
        }
        $xpath = new DOMXPath($doc);
        $issues = array_merge(
            $this->check_images($doc),
            $this->check_links($doc, $xpath),
            $this->check_tables($doc),
            $this->check_headings($xpath),
            $this->check_iframes($doc),
            $this->check_contrast($xpath),
            $this->check_new_window($doc),
            $this->check_areas($doc),
            $this->check_tables_extra($doc),
            $this->check_structure($doc, $xpath),
            $this->check_text($doc, $xpath),
            $this->check_media($doc),
        );
        return $issues;
    }

    /**
     * Create an issue, making the signature unique within the fragment.
     *
     * @param string $checkid
     * @param string $signature
     * @param DOMNode $node
     * @param array $params
     * @return issue
     */
    private function make(string $checkid, string $signature, DOMNode $node, array $params = []): issue {
        $key = $checkid . '|' . $signature;
        $this->seen[$key] = ($this->seen[$key] ?? 0) + 1;
        if ($this->seen[$key] > 1) {
            $signature .= '#' . $this->seen[$key];
        }
        $issue = new issue($checkid, $signature, self::excerpt($node), $params);
        if ($node instanceof DOMElement && $node->getAttribute('id') !== self::ROOT_ID) {
            $issue->tag = strtolower($node->tagName);
            $index = 0;
            foreach ($node->ownerDocument->getElementsByTagName($issue->tag) as $candidate) {
                if ($candidate->isSameNode($node)) {
                    $issue->ordinal = $index;
                    break;
                }
                $index++;
            }
        }
        return $issue;
    }

    /**
     * Safe, truncated plain-text excerpt of an element's markup.
     *
     * Data URIs are shortened and Vimeo privacy hashes are redacted.
     *
     * @param DOMNode $node
     * @return string
     */
    public static function excerpt(DOMNode $node): string {
        $markup = (string) $node->ownerDocument->saveHTML($node);
        $markup = preg_replace('~data:[^"\'\s>]{20,}~i', 'data:…', $markup);
        $markup = vimeo::redact_text($markup);
        $markup = preg_replace('/\s+/u', ' ', $markup);
        if (\core_text::strlen($markup) > 300) {
            $markup = \core_text::substr($markup, 0, 297) . '…';
        }
        return $markup;
    }

    /**
     * Visible text of a node, including alt text of contained images.
     *
     * @param DOMNode $node
     * @return string
     */
    private static function accessible_text(DOMNode $node): string {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->nodeValue;
            } else if ($child instanceof DOMElement) {
                if ($child->getAttribute('aria-hidden') === 'true') {
                    continue;
                }
                if (strtolower($child->tagName) === 'img') {
                    $text .= ' ' . $child->getAttribute('alt') . ' ';
                } else {
                    $text .= ' ' . self::accessible_text($child) . ' ';
                }
            }
        }
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * Short signature for a URL-like attribute (no query string beyond 80 chars).
     *
     * @param string $value
     * @return string
     */
    private static function url_signature(string $value): string {
        $value = trim($value);
        if (str_starts_with(strtolower($value), 'data:')) {
            return 'data:' . sha1($value);
        }
        return \core_text::substr($value, 0, 255);
    }

    /**
     * Image checks.
     *
     * @param DOMDocument $doc
     * @return issue[]
     */
    private function check_images(DOMDocument $doc): array {
        $issues = [];
        /** @var DOMElement $img */
        foreach ($doc->getElementsByTagName('img') as $img) {
            $role = strtolower(trim($img->getAttribute('role')));
            if ($img->getAttribute('aria-hidden') === 'true' || $role === 'presentation' || $role === 'none') {
                continue; // Explicitly decorative.
            }
            $src = $img->getAttribute('src');
            $sig = 'img|' . self::url_signature($src);
            if (!$img->hasAttribute('alt')) {
                if (trim($img->getAttribute('aria-label')) !== '' || trim($img->getAttribute('aria-labelledby')) !== '') {
                    continue;
                }
                $issues[] = $this->make('img_alt_missing', $sig, $img);
                continue;
            }
            $alt = trim($img->getAttribute('alt'));
            if ($alt === '') {
                $issues[] = $this->make('img_alt_empty', $sig, $img);
                continue;
            }
            $reason = $this->suspicious_alt_reason($alt, $src);
            if ($reason !== null) {
                $issues[] = $this->make('img_alt_suspicious', $sig, $img, ['reason' => $reason, 'alt' => $alt]);
            }
        }
        return $issues;
    }

    /**
     * Why an alt text looks unhelpful, or null when it looks plausible.
     *
     * @param string $alt
     * @param string $src
     * @return string|null Reason code: filename, generic or long.
     */
    private function suspicious_alt_reason(string $alt, string $src): ?string {
        $norm = self::normalise($alt);
        if (preg_match('/\.(jpe?g|png|gif|svg|webp|bmp|tiff?|heic)$/i', trim($alt))) {
            return 'filename';
        }
        $path = (string) parse_url(str_replace('@@PLUGINFILE@@', 'http://pluginfile', $src), PHP_URL_PATH);
        $basename = self::normalise(pathinfo(rawurldecode($path), PATHINFO_FILENAME));
        if ($basename !== '' && $norm === $basename && preg_match('/[_\-\d]/', $basename)) {
            return 'filename';
        }
        if (preg_match('/^(img|image|dsc|dscn|pxl|photo|screenshot|screen shot|capture)[\s_\-]*\d+/i', trim($alt))) {
            return 'filename';
        }
        if (in_array($norm, $this->genericalt, true)) {
            return 'generic';
        }
        if (\core_text::strlen($alt) > 150) {
            return 'long';
        }
        return null;
    }

    /**
     * Link checks.
     *
     * @param DOMDocument $doc
     * @param DOMXPath $xpath
     * @return issue[]
     */
    private function check_links(DOMDocument $doc, DOMXPath $xpath): array {
        $issues = [];
        /** @var DOMElement $a */
        foreach ($doc->getElementsByTagName('a') as $a) {
            if (!$a->hasAttribute('href')) {
                continue; // Named anchor, not a link.
            }
            if ($a->getAttribute('aria-hidden') === 'true') {
                continue;
            }
            $href = $a->getAttribute('href');
            $text = self::accessible_text($a);
            $name = $text;
            if ($name === '') {
                $name = trim($a->getAttribute('aria-label'));
            }
            if ($name === '' && ($labelledby = trim($a->getAttribute('aria-labelledby'))) !== '') {
                foreach (preg_split('/\s+/', $labelledby) as $id) {
                    $nodes = $xpath->query('//*[@id=' . self::xpath_literal($id) . ']');
                    if ($nodes && $nodes->length) {
                        $name .= ' ' . self::accessible_text($nodes->item(0));
                    } else {
                        $name = 'unresolved'; // Referenced outside fragment: assume named.
                    }
                }
                $name = trim($name);
            }
            if ($name === '') {
                $name = trim($a->getAttribute('title'));
            }
            $sig = 'a|' . self::url_signature($href) . '|' . \core_text::substr(self::normalise($name), 0, 100);
            if ($name === '') {
                $issues[] = $this->make('link_empty', $sig, $a);
                continue;
            }
            $norm = self::normalise($name);
            if (in_array($norm, $this->unclear, true)) {
                $issues[] = $this->make('link_unclear', $sig, $a, ['text' => $name]);
            } else if (preg_match('~^(https?://|www\.)\S{20,}$~i', $name)) {
                $issues[] = $this->make('link_url_text', $sig, $a);
            }
        }
        return $issues;
    }

    /**
     * Quote a string for use as an XPath literal.
     *
     * @param string $value
     * @return string
     */
    private static function xpath_literal(string $value): string {
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }
        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }
        return "concat('" . str_replace("'", "',\"'\",'", $value) . "')";
    }

    /**
     * Table header checks.
     *
     * @param DOMDocument $doc
     * @return issue[]
     */
    private function check_tables(DOMDocument $doc): array {
        $issues = [];
        /** @var DOMElement $table */
        foreach ($doc->getElementsByTagName('table') as $table) {
            $role = strtolower(trim($table->getAttribute('role')));
            if ($role === 'presentation' || $role === 'none') {
                continue; // Declared layout table.
            }
            if ($table->getElementsByTagName('th')->length > 0) {
                continue;
            }
            $hasrole = false;
            $rows = 0;
            $maxcols = 0;
            foreach ($table->getElementsByTagName('tr') as $tr) {
                $rows++;
                $cols = 0;
                foreach ($tr->childNodes as $cell) {
                    if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                        $cols++;
                        $cellrole = strtolower($cell->getAttribute('role'));
                        if ($cellrole === 'columnheader' || $cellrole === 'rowheader' || $cell->hasAttribute('scope')) {
                            $hasrole = true;
                        }
                    }
                }
                $maxcols = max($maxcols, $cols);
            }
            if ($hasrole || $rows < 2 || $maxcols < 2) {
                continue; // Has headers, or a single row/column (likely layout).
            }
            $sig = 'table|' . \core_text::substr(self::normalise(self::accessible_text($table)), 0, 80);
            $issues[] = $this->make('table_no_headers', $sig, $table, ['rows' => $rows, 'cols' => $maxcols]);
        }
        return $issues;
    }

    /**
     * Heading structure checks within the fragment.
     *
     * The first heading in a fragment is not compared against anything: the
     * surrounding page supplies its own headings, so a fragment may start at any level.
     *
     * @param DOMXPath $xpath
     * @return issue[]
     */
    private function check_headings(DOMXPath $xpath): array {
        $issues = [];
        $previous = 0;
        $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
        /** @var DOMElement $h */
        foreach ($headings as $h) {
            $level = (int) substr(strtolower($h->tagName), 1);
            $text = self::accessible_text($h);
            $sig = 'h|' . $level . '|' . \core_text::substr(self::normalise($text), 0, 100);
            if ($text === '' && trim($h->getAttribute('aria-label')) === '') {
                $issues[] = $this->make('heading_empty', $sig, $h, ['level' => $level]);
                continue;
            }
            if ($previous > 0 && $level > $previous + 1) {
                $issues[] = $this->make('heading_skip', $sig, $h, ['from' => $previous, 'to' => $level]);
            }
            $previous = $level;
        }
        return $issues;
    }

    /**
     * Iframe title checks.
     *
     * @param DOMDocument $doc
     * @return issue[]
     */
    private function check_iframes(DOMDocument $doc): array {
        $issues = [];
        /** @var DOMElement $frame */
        foreach ($doc->getElementsByTagName('iframe') as $frame) {
            if ($frame->getAttribute('aria-hidden') === 'true') {
                continue;
            }
            $title = trim($frame->getAttribute('title'));
            if ($title === '') {
                $title = trim($frame->getAttribute('aria-label'));
            }
            $sig = 'iframe|' . self::url_signature(vimeo::redact_text($frame->getAttribute('src')));
            if ($title === '') {
                $issues[] = $this->make('iframe_title_missing', $sig, $frame);
            } else if (in_array(self::normalise($title), self::GENERIC_IFRAME_TITLES, true)) {
                $issues[] = $this->make('iframe_title_generic', $sig, $frame, ['title' => $title]);
            }
        }
        return $issues;
    }

    /**
     * Parse an inline style attribute into lower-case property => value.
     *
     * @param string $style
     * @return array<string, string>
     */
    public static function parse_style(string $style): array {
        $result = [];
        foreach (explode(';', $style) as $declaration) {
            $pos = strpos($declaration, ':');
            if ($pos === false) {
                continue;
            }
            $prop = strtolower(trim(substr($declaration, 0, $pos)));
            $value = trim(substr($declaration, $pos + 1));
            if ($prop !== '' && $value !== '') {
                $result[$prop] = $value;
            }
        }
        return $result;
    }

    /**
     * Find an inline colour for an element or its ancestors.
     *
     * The search stops (returns null) at any element that could receive the
     * property from a stylesheet (class/id attribute or legacy colour attribute),
     * because the stored HTML alone cannot tell us the rendered value.
     *
     * @param DOMElement $el
     * @param string $kind 'fg' or 'bg'.
     * @return array|null [r, g, b] or null when undeterminable.
     */
    private static function resolve_colour(DOMElement $el, string $kind): ?array {
        $node = $el;
        while ($node instanceof DOMElement) {
            if ($node->getAttribute('id') === self::ROOT_ID) {
                return null; // Reached the fragment root: value comes from the theme.
            }
            $styles = self::parse_style($node->getAttribute('style'));
            if ($kind === 'fg') {
                if (isset($styles['color'])) {
                    $parsed = contrast::parse($styles['color']);
                    return is_array($parsed) ? $parsed : null;
                }
                if (
                    $node->hasAttribute('class') || $node->hasAttribute('color') || in_array(
                        strtolower($node->tagName),
                        ['a', 'button', 'input', 'select', 'textarea', 'mark', 'font'],
                        true
                    )
                ) {
                    return null; // Colour may come from a stylesheet or user agent.
                }
            } else {
                $value = $styles['background-color'] ?? null;
                if ($value === null && isset($styles['background'])) {
                    $bg = trim($styles['background']);
                    // Only a single colour token is reliable; images and gradients are not.
                    $value = preg_match('/^(#[0-9a-f]{3,8}|rgba?\([^)]*\)|[a-z]+)(\s*!important)?$/i', $bg) ? $bg : '';
                    if ($value === '') {
                        return null;
                    }
                }
                if ($value !== null) {
                    $parsed = contrast::parse($value);
                    if ($parsed === contrast::TRANSPARENT) {
                        $node = $node->parentNode;
                        continue;
                    }
                    return is_array($parsed) ? $parsed : null;
                }
                if (
                    $node->hasAttribute('class') || $node->hasAttribute('bgcolor') || $node->hasAttribute('background')
                        || in_array(strtolower($node->tagName), ['mark', 'button', 'input', 'select', 'textarea'], true)
                ) {
                    return null;
                }
            }
            $node = $node->parentNode;
        }
        return null;
    }

    /**
     * Font size (px) and boldness from inline styles on the element or ancestors.
     *
     * @param DOMElement $el
     * @return array{0: float|null, 1: bool|null} [sizepx or null, bold or null]
     */
    private static function resolve_text_size(DOMElement $el): array {
        $size = null;
        $bold = null;
        $node = $el;
        while ($node instanceof DOMElement && $node->getAttribute('id') !== self::ROOT_ID) {
            $styles = self::parse_style($node->getAttribute('style'));
            if ($size === null && isset($styles['font-size'])) {
                if (preg_match('/^([\d.]+)\s*(px|pt)$/i', trim($styles['font-size']), $m)) {
                    $size = strtolower($m[2]) === 'pt' ? (float) $m[1] * 4 / 3 : (float) $m[1];
                } else {
                    return [null, null]; // Relative units depend on the theme.
                }
            }
            if ($bold === null && isset($styles['font-weight'])) {
                $w = strtolower(trim($styles['font-weight']));
                $bold = in_array($w, ['bold', 'bolder'], true) || (is_numeric($w) && (int) $w >= 700);
            }
            if ($bold === null && in_array(strtolower($node->tagName), ['b', 'strong'], true)) {
                $bold = true;
            }
            $node = $node->parentNode;
        }
        return [$size, $bold];
    }

    /**
     * Inline-style colour contrast checks.
     *
     * Only text whose foreground and background are both set inline (on the
     * element or a class-free ancestor inside the fragment) is evaluated.
     *
     * @param DOMXPath $xpath
     * @return issue[]
     */
    private function check_contrast(DOMXPath $xpath): array {
        $issues = [];
        $nodes = $xpath->query('//*[@style]');
        /** @var DOMElement $el */
        foreach ($nodes as $el) {
            $styles = self::parse_style($el->getAttribute('style'));
            if (!isset($styles['color']) && !isset($styles['background-color']) && !isset($styles['background'])) {
                continue;
            }
            // Evaluate the text this element (or its unstyled descendants) carries.
            $text = self::direct_text($el);
            if ($text === '') {
                continue;
            }
            $fg = self::resolve_colour($el, 'fg');
            $bg = self::resolve_colour($el, 'bg');
            if ($fg === null || $bg === null) {
                continue; // Not reliably determinable from stored HTML.
            }
            $ratio = contrast::ratio($fg, $bg);
            [$size, $bold] = self::resolve_text_size($el);
            $large = null;
            if ($size !== null) {
                $large = $size >= 24 || ($size >= 18.66 && $bold === true);
            }
            $params = [
                'ratio' => $ratio,
                'fg' => contrast::to_hex($fg),
                'bg' => contrast::to_hex($bg),
                'large' => $large,
            ];
            $sig = 'contrast|' . $params['fg'] . '|' . $params['bg'] . '|' .
                \core_text::substr(self::normalise($text), 0, 60);
            if ($ratio < 3.0 || ($large === false && $ratio < 4.5)) {
                // Below 3:1 fails at any text size; below 4.5:1 fails for text known to be normal size.
                $params['required'] = ($large === true) ? 3.0 : 4.5;
                $issues[] = $this->make('contrast_fail', $sig, $el, $params);
            } else if ($large === null && $ratio < 4.5) {
                $issues[] = $this->make('contrast_review', $sig, $el, $params);
            }
        }
        return $issues;
    }

    /**
     * Text that inherits colours from this element: direct text nodes plus
     * descendants that do not set their own colours or classes.
     *
     * @param DOMElement $el
     * @return string
     */
    private static function direct_text(DOMElement $el): string {
        $text = '';
        foreach ($el->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->nodeValue;
            } else if ($child instanceof DOMElement) {
                $childstyles = self::parse_style($child->getAttribute('style'));
                if (
                    isset($childstyles['color']) || isset($childstyles['background-color'])
                        || isset($childstyles['background']) || $child->hasAttribute('class')
                ) {
                    continue; // Evaluated separately or undeterminable.
                }
                if (in_array(strtolower($child->tagName), ['script', 'style', 'img', 'iframe', 'a'], true)) {
                    continue;
                }
                $text .= ' ' . self::direct_text($child);
            }
        }
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** @var int Visible characters above which content without headings is reported. */
    public const LONG_CONTENT_CHARS = 3000;

    /** @var string[] Presentational elements that are obsolete in HTML. */
    private const DEPRECATED_TAGS = ['font', 'center', 'basefont', 'big', 'tt', 'strike'];

    /**
     * Whether the node is inside an element with the given tag name.
     *
     * @param DOMNode $node
     * @param string $tag
     * @return bool
     */
    private static function inside(DOMNode $node, string $tag): bool {
        for ($parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
            if (strtolower($parent->tagName) === $tag) {
                return true;
            }
        }
        return false;
    }

    /**
     * Links that open a new window or tab without saying so.
     *
     * @param DOMDocument $doc
     * @return issue[]
     */
    private function check_new_window(DOMDocument $doc): array {
        $issues = [];
        /** @var DOMElement $a */
        foreach ($doc->getElementsByTagName('a') as $a) {
            if (
                !$a->hasAttribute('href') || strtolower(trim($a->getAttribute('target'))) !== '_blank'
                    || $a->getAttribute('aria-hidden') === 'true'
            ) {
                continue;
            }
            $text = self::accessible_text($a);
            $all = $text . ' ' . $a->getAttribute('aria-label') . ' ' . $a->getAttribute('title');
            if (preg_match('/(new|separate|another)\s+(browser\s+)?(window|tab)|opens?\s+in|external/i', $all)) {
                continue;
            }
            $sig = 'newwin|' . self::url_signature($a->getAttribute('href')) . '|' .
                \core_text::substr(self::normalise($text), 0, 100);
            $issues[] = $this->make('link_new_window', $sig, $a);
        }
        return $issues;
    }

    /**
     * Image map areas without alternative text.
     *
     * @param DOMDocument $doc
     * @return issue[]
     */
    private function check_areas(DOMDocument $doc): array {
        $issues = [];
        /** @var DOMElement $area */
        foreach ($doc->getElementsByTagName('area') as $area) {
            if (!$area->hasAttribute('href')) {
                continue;
            }
            if (trim($area->getAttribute('alt')) === '' && trim($area->getAttribute('aria-label')) === '') {
                $sig = 'area|' . self::url_signature($area->getAttribute('href')) . '|' . $area->getAttribute('coords');
                $issues[] = $this->make('area_alt_missing', $sig, $area);
            }
        }
        return $issues;
    }

    /**
     * Complex data tables and duplicated table summaries.
     *
     * @param DOMDocument $doc
     * @return issue[]
     */
    private function check_tables_extra(DOMDocument $doc): array {
        $issues = [];
        /** @var DOMElement $table */
        foreach ($doc->getElementsByTagName('table') as $table) {
            $role = strtolower(trim($table->getAttribute('role')));
            if ($role === 'presentation' || $role === 'none') {
                continue;
            }
            $tabletext = \core_text::substr(self::normalise(self::accessible_text($table)), 0, 80);
            $summary = self::normalise($table->getAttribute('summary'));
            $caption = $table->getElementsByTagName('caption')->item(0);
            if ($summary !== '' && $caption && self::normalise(self::accessible_text($caption)) === $summary) {
                $issues[] = $this->make('table_summary_duplicate', 'tsum|' . $tabletext, $table);
            }
            if ($table->getElementsByTagName('th')->length === 0) {
                continue; // Tables without headers are reported by table_no_headers.
            }
            $spans = false;
            $explicit = false;
            foreach (['td', 'th'] as $tag) {
                /** @var DOMElement $cell */
                foreach ($table->getElementsByTagName($tag) as $cell) {
                    if ((int) $cell->getAttribute('colspan') > 1 || (int) $cell->getAttribute('rowspan') > 1) {
                        $spans = true;
                    }
                    if ($cell->hasAttribute('headers')) {
                        $explicit = true;
                    }
                }
            }
            if ($spans && !$explicit) {
                $issues[] = $this->make('table_complex', 'tcomplex|' . $tabletext, $table);
            }
        }
        return $issues;
    }

    /**
     * Document structure: missing headings, visual-only headings and lists, empty legends, obsolete markup.
     *
     * @param DOMDocument $doc
     * @param DOMXPath $xpath
     * @return issue[]
     */
    private function check_structure(DOMDocument $doc, DOMXPath $xpath): array {
        $issues = [];
        $root = $xpath->query('//*[@id="' . self::ROOT_ID . '"]')->item(0);
        if ($root) {
            $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6|//*[@role="heading"]')->length;
            if ($headings === 0 && \core_text::strlen(self::accessible_text($root)) >= self::LONG_CONTENT_CHARS) {
                $issues[] = $this->make('content_no_headings', 'noheadings', $root);
            }
        }

        /** @var DOMElement $p */
        foreach ($doc->getElementsByTagName('p') as $p) {
            // A paragraph whose only content is one short bold run looks like a heading.
            $elements = [];
            $loosetext = '';
            foreach ($p->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) !== 'br') {
                    $elements[] = $child;
                } else if ($child->nodeType === XML_TEXT_NODE) {
                    $loosetext .= $child->nodeValue;
                }
            }
            if (
                count($elements) === 1 && trim($loosetext, " \t\n\r\0\x0B\u{A0}") === '' &&
                    in_array(strtolower($elements[0]->tagName), ['strong', 'b'], true)
            ) {
                $text = self::accessible_text($elements[0]);
                $length = \core_text::strlen($text);
                if ($length >= 3 && $length <= 80 && !preg_match('/[.!?,;]$/u', $text) && $p->nextElementSibling) {
                    $issues[] = $this->make('heading_visual', 'hvis|' . self::normalise($text), $p);
                }
            }

            // Two or more consecutive paragraphs starting with bullet characters or numbers.
            if (self::looks_like_list_item($p)) {
                $prev = $p->previousElementSibling;
                $startsrun = !($prev instanceof DOMElement && strtolower($prev->tagName) === 'p'
                    && self::looks_like_list_item($prev));
                $next = $p->nextElementSibling;
                if (
                    $startsrun && $next instanceof DOMElement && strtolower($next->tagName) === 'p'
                        && self::looks_like_list_item($next)
                ) {
                    $sig = 'fakelist|' . \core_text::substr(self::normalise(self::accessible_text($p)), 0, 60);
                    $issues[] = $this->make('list_manual', $sig, $p);
                }
            }
        }

        $index = 0;
        foreach ($doc->getElementsByTagName('legend') as $legend) {
            $index++;
            if (self::accessible_text($legend) === '') {
                $issues[] = $this->make('legend_empty', 'legend|' . $index, $legend);
            }
        }

        foreach (self::DEPRECATED_TAGS as $tag) {
            $list = $doc->getElementsByTagName($tag);
            if ($list->length > 0) {
                $issues[] = $this->make(
                    'markup_deprecated',
                    'deprecated|' . $tag,
                    $list->item(0),
                    ['tag' => $tag, 'count' => $list->length]
                );
            }
        }
        return $issues;
    }

    /**
     * Whether a paragraph starts like a manually typed list item.
     *
     * @param DOMElement $p
     * @return bool
     */
    private static function looks_like_list_item(DOMElement $p): bool {
        return (bool) preg_match(
            '/^\s*([\x{2022}\x{25CF}\x{25AA}\x{25E6}\x{00B7}\x{2013}*\-]|\d{1,2}[.)]|[a-z][.)])\s+\S/u',
            self::accessible_text($p)
        );
    }

    /**
     * Text presentation: underlining outside links, long upper-case text, blinking or scrolling text.
     *
     * @param DOMDocument $doc
     * @param DOMXPath $xpath
     * @return issue[]
     */
    private function check_text(DOMDocument $doc, DOMXPath $xpath): array {
        $issues = [];
        $underlined = [];
        foreach ($doc->getElementsByTagName('u') as $u) {
            $underlined[] = $u;
        }
        $blink = [];
        $seenparents = [];
        /** @var DOMElement $el */
        foreach ($xpath->query('//*[@style]') as $el) {
            $styles = self::parse_style($el->getAttribute('style'));
            $decoration = strtolower(($styles['text-decoration'] ?? '') . ' ' . ($styles['text-decoration-line'] ?? ''));
            if (str_contains($decoration, 'underline') && strtolower($el->tagName) !== 'a') {
                $underlined[] = $el;
            }
            if (str_contains($decoration, 'blink')) {
                $blink[] = $el;
            }
            if (strtolower(trim($styles['text-transform'] ?? '')) === 'uppercase') {
                $text = self::accessible_text($el);
                if (preg_match_all('/\p{L}/u', $text) >= 30) {
                    $seenparents[spl_object_id($el)] = true;
                    $issues[] = $this->make('text_all_caps', 'caps|' . \core_text::substr(self::normalise($text), 0, 60), $el);
                }
            }
        }
        foreach ($underlined as $el) {
            if (self::inside($el, 'a') || $el->getElementsByTagName('a')->length > 0) {
                continue;
            }
            $text = self::accessible_text($el);
            if ($text !== '') {
                $issues[] = $this->make('text_underline', 'u|' . \core_text::substr(self::normalise($text), 0, 60), $el);
            }
        }

        foreach ($xpath->query('//text()[normalize-space()]') as $node) {
            $parent = $node->parentNode;
            if (
                !$parent instanceof DOMElement || isset($seenparents[spl_object_id($parent)])
                    || in_array(strtolower($parent->tagName), ['script', 'style'], true)
            ) {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $node->nodeValue));
            $letters = preg_match_all('/\p{L}/u', $text);
            if ($letters < 30 || count(preg_split('/\s+/u', $text)) < 5) {
                continue;
            }
            if (preg_match_all('/\p{Lu}/u', $text) / $letters >= 0.9) {
                $seenparents[spl_object_id($parent)] = true;
                $issues[] = $this->make(
                    'text_all_caps',
                    'caps|' . \core_text::substr(self::normalise($text), 0, 60),
                    $parent
                );
            }
        }

        foreach (['blink', 'marquee'] as $tag) {
            foreach ($doc->getElementsByTagName($tag) as $el) {
                $blink[] = $el;
            }
        }
        foreach ($blink as $el) {
            $sig = 'blink|' . strtolower($el->tagName) . '|' .
                \core_text::substr(self::normalise(self::accessible_text($el)), 0, 60);
            $issues[] = $this->make('motion_blink', $sig, $el);
        }
        return $issues;
    }

    /**
     * Embedded objects and HTML5 media.
     *
     * @param DOMDocument $doc
     * @return issue[]
     */
    private function check_media(DOMDocument $doc): array {
        $issues = [];
        foreach (['object' => 'data', 'embed' => 'src'] as $tag => $attr) {
            /** @var DOMElement $el */
            foreach ($doc->getElementsByTagName($tag) as $el) {
                if ($tag === 'embed' && self::inside($el, 'object')) {
                    continue; // The object is checked instead.
                }
                if ($el->getAttribute('aria-hidden') === 'true') {
                    continue;
                }
                $name = trim($el->getAttribute('title') . $el->getAttribute('aria-label') .
                    $el->getAttribute('aria-labelledby'));
                if ($name === '') {
                    $sig = $tag . '|' . self::url_signature(vimeo::redact_text($el->getAttribute($attr)));
                    $issues[] = $this->make('object_title_missing', $sig, $el, ['tag' => $tag]);
                }
            }
        }
        foreach (['video', 'audio'] as $tag) {
            /** @var DOMElement $el */
            foreach ($doc->getElementsByTagName($tag) as $el) {
                $src = $el->getAttribute('src');
                if ($src === '') {
                    $source = $el->getElementsByTagName('source')->item(0);
                    $src = $source instanceof DOMElement ? $source->getAttribute('src') : '';
                }
                $sig = $tag . '|' . self::url_signature($src);
                if ($el->hasAttribute('autoplay') && !($tag === 'video' && $el->hasAttribute('muted'))) {
                    $issues[] = $this->make('media_autoplay', $sig, $el, ['tag' => $tag]);
                }
                if ($tag === 'video') {
                    $captioned = false;
                    /** @var DOMElement $track */
                    foreach ($el->getElementsByTagName('track') as $track) {
                        if (in_array(strtolower($track->getAttribute('kind')), ['captions', 'subtitles'], true)) {
                            $captioned = true;
                        }
                    }
                    if (!$captioned) {
                        $issues[] = $this->make('media_no_captions', $sig, $el);
                    }
                }
            }
        }
        return $issues;
    }
}
