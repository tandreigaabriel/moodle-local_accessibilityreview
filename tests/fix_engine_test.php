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

use local_accessibilityreview\local\checks\html_checker;
use local_accessibilityreview\local\checks\issue;
use local_accessibilityreview\local\fix\fix_engine;
use local_accessibilityreview\local\fix\fix_exception;
use local_accessibilityreview\local\fix\html_editor;

/**
 * Tests for targeted HTML fixes.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibilityreview\local\fix\fix_engine
 * @covers     \local_accessibilityreview\local\fix\html_editor
 */
final class fix_engine_test extends \basic_testcase {
    /**
     * First issue of a check in some HTML.
     *
     * @param string $html
     * @param string $checkid
     * @param int $n Which matching issue (0-based).
     * @return issue
     */
    private function issue(string $html, string $checkid, int $n = 0): issue {
        $matches = array_values(array_filter((new html_checker())->check($html), fn($i) => $i->checkid === $checkid));
        $this->assertTrue(isset($matches[$n]), "No $checkid issue #$n");
        return $matches[$n];
    }

    /**
     * Check ids remaining after a fix.
     *
     * @param string $html
     * @return string[]
     */
    private function ids(string $html): array {
        return array_map(fn($i) => $i->checkid, (new html_checker())->check($html));
    }

    /**
     * Alt text fixes change only the targeted image tag.
     */
    public function test_alt_text(): void {
        $html = "<p>Intro &amp; {mlang en}text{mlang}</p>\n<img src=\"@@PLUGINFILE@@/a.png\">\n" .
            "<!-- <img src=\"commented.png\"> --><img src='@@PLUGINFILE@@/b.png' class=\"x\">";
        $issue = $this->issue($html, 'img_alt_missing', 1);
        $fixed = fix_engine::apply($html, $issue, ['text' => 'Diagram of the "twelve" meridians']);
        $this->assertSame("<p>Intro &amp; {mlang en}text{mlang}</p>\n<img src=\"@@PLUGINFILE@@/a.png\">\n" .
            "<!-- <img src=\"commented.png\"> --><img src='@@PLUGINFILE@@/b.png' class=\"x\" " .
            "alt=\"Diagram of the &quot;twelve&quot; meridians\">", $fixed);
        $this->assertSame(['img_alt_missing'], $this->ids($fixed));

        $decorative = fix_engine::apply($html, $this->issue($html, 'img_alt_missing'), ['decorative' => 1]);
        $this->assertStringContainsString('<img src="@@PLUGINFILE@@/a.png" alt="" role="presentation">', $decorative);

        $suspicious = '<img alt="IMG_0042.jpg" src="x/IMG_0042.jpg"/>';
        $fixed = fix_engine::apply($suspicious, $this->issue($suspicious, 'img_alt_suspicious'), ['text' => 'Needle angle']);
        $this->assertSame('<img alt="Needle angle" src="x/IMG_0042.jpg"/>', $fixed);
    }

    /**
     * Required text and length limits.
     */
    public function test_validation(): void {
        $html = '<img src="a.png">';
        foreach ([['text' => '  '], ['text' => str_repeat('a', 251)]] as $data) {
            try {
                fix_engine::apply($html, $this->issue($html, 'img_alt_missing'), $data);
                $this->fail('Expected fix_exception');
            } catch (fix_exception $e) {
                $this->assertTrue(in_array($e->reason, ['textrequired', 'texttoolong'], true));
            }
        }
    }

    /**
     * A changed document is detected instead of editing the wrong element.
     */
    public function test_mismatch(): void {
        $issue = $this->issue('<img src="a.png"><img src="b.png">', 'img_alt_missing', 1);
        try {
            fix_engine::apply('<img src="c.png"><img src="d.png">', $issue, ['text' => 'x']);
            $this->fail('Expected fix_exception');
        } catch (fix_exception $e) {
            $this->assertSame('changed', $e->reason);
        }
        // An image inserted before the target shifts its position; the fix still targets b.png.
        $fixed = fix_engine::apply(
            '<img src="new.png" alt="New"><img src="a.png"><img src="b.png">',
            $issue,
            ['text' => 'B']
        );
        $this->assertSame('<img src="new.png" alt="New"><img src="a.png"><img src="b.png" alt="B">', $fixed);
    }

    /**
     * Iframe titles, autoplay and new-window links.
     */
    public function test_frames_media_links(): void {
        $html = '<iframe src="https://player.vimeo.com/video/1" width="640"></iframe>';
        $fixed = fix_engine::apply($html, $this->issue($html, 'iframe_title_missing'), ['text' => 'Video: Pulse taking']);
        $this->assertSame(
            '<iframe src="https://player.vimeo.com/video/1" width="640" title="Video: Pulse taking"></iframe>',
            $fixed
        );

        $html = '<video src="v.mp4" autoplay controls><track kind="captions" src="v.vtt"></video>';
        $fixed = fix_engine::apply($html, $this->issue($html, 'media_autoplay'), []);
        $this->assertSame('<video src="v.mp4" controls><track kind="captions" src="v.vtt"></video>', $fixed);

        $html = '<a href="https://example.com/x" target="_blank">Handbook</a>';
        $this->assertSame(
            '<a href="https://example.com/x">Handbook</a>',
            fix_engine::apply($html, $this->issue($html, 'link_new_window'), ['mode' => 'sametab'])
        );
        $this->assertSame(
            '<a href="https://example.com/x" target="_blank">Handbook (opens in new window)</a>',
            fix_engine::apply(
                $html,
                $this->issue($html, 'link_new_window'),
                ['mode' => 'label', 'label' => '(opens in new window)']
            )
        );
    }

    /**
     * Headings and tables.
     */
    public function test_headings_tables(): void {
        $html = '<h3>Points</h3><p>a</p><h5 class="x">Location</h5><p>b</p>';
        $fixed = fix_engine::apply($html, $this->issue($html, 'heading_skip'), ['level' => 4]);
        $this->assertSame('<h3>Points</h3><p>a</p><h4 class="x">Location</h4><p>b</p>', $fixed);
        $this->assertSame([], $this->ids($fixed));

        $html = '<table><tbody><tr><td>Day</td><td>Time</td></tr><tr><td>Mon</td><td>9</td></tr></tbody></table>';
        $fixed = fix_engine::apply($html, $this->issue($html, 'table_no_headers'), ['headers' => 'row']);
        $this->assertSame('<table><tbody><tr><th scope="col">Day</th><th scope="col">Time</th></tr>' .
            '<tr><td>Mon</td><td>9</td></tr></tbody></table>', $fixed);
        $this->assertSame([], $this->ids($fixed));

        $fixed = fix_engine::apply($html, $this->issue($html, 'table_no_headers'), ['headers' => 'both']);
        $this->assertSame('<table><tbody><tr><th scope="col">Day</th><th scope="col">Time</th></tr>' .
            '<tr><th scope="row">Mon</th><td>9</td></tr></tbody></table>', $fixed);

        $nested = '<table><tr><td><table><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></table></td>' .
            '<td>x</td></tr><tr><td>y</td><td>z</td></tr></table>';
        try {
            fix_engine::apply($nested, $this->issue($nested, 'table_no_headers'), ['headers' => 'row']);
            $this->fail('Expected fix_exception');
        } catch (fix_exception $e) {
            $this->assertSame('tablecomplex', $e->reason);
        }
    }

    /**
     * Attribute editing respects quoted values containing attribute-like text and ">".
     */
    public function test_attribute_tokeniser(): void {
        $tag = '<img title="alt > here" src="a.png" data-x=\'alt="no"\'>';
        $this->assertSame(
            '<img title="alt > here" src="a.png" data-x=\'alt="no"\' alt="Chart">',
            html_editor::set_attribute($tag, 'alt', 'Chart')
        );
        $this->assertSame([0, strlen($tag)], html_editor::find_start_tag('' . $tag, 'img', 0));
        $this->assertSame('<img src="a.png">', html_editor::remove_attribute(
            '<img src="a.png" autoplay AUTOPLAY="1">',
            'autoplay'
        ));
        $this->assertNull(html_editor::find_start_tag('<script>var s = "<img src=x>";</script>', 'img', 0));
    }
}
