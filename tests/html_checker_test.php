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

use local_accessibilityreview\local\checks\contrast;
use local_accessibilityreview\local\checks\html_checker;

/**
 * Tests for the HTML checks and contrast calculation.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibilityreview\local\checks\html_checker
 * @covers     \local_accessibilityreview\local\checks\contrast
 */
final class html_checker_test extends \basic_testcase {
    /**
     * Check ids found in a fragment.
     *
     * @param string $html
     * @return string[]
     */
    private function ids(string $html): array {
        return array_map(fn($i) => $i->checkid, (new html_checker())->check($html));
    }

    /**
     * Images.
     */
    public function test_images(): void {
        $this->assertSame(['img_alt_missing'], $this->ids('<img src="a.png">'));
        $this->assertSame(['img_alt_empty'], $this->ids('<img src="a.png" alt="">'));
        $this->assertSame([], $this->ids('<img src="a.png" role="presentation">'));
        $this->assertSame([], $this->ids('<img src="a.png" aria-hidden="true">'));
        $this->assertSame([], $this->ids('<img src="a.png" aria-label="Chart of results">'));
        $this->assertSame(['img_alt_suspicious'], $this->ids('<img src="x/IMG_0042.jpg" alt="IMG_0042.jpg">'));
        $this->assertSame(['img_alt_suspicious'], $this->ids('<img src="@@PLUGINFILE@@/week_1-diagram.png" alt="week_1-diagram">'));
        $this->assertSame(['img_alt_suspicious'], $this->ids('<img src="a.png" alt="image">'));
        $this->assertSame([], $this->ids('<img src="a.png" alt="Bar chart showing enrolments rising from 200 to 350">'));
    }

    /**
     * Links.
     */
    public function test_links(): void {
        $this->assertSame(['link_empty'], $this->ids('<a href="https://example.com"></a>'));
        // An image with empty alt inside a link leaves the link without a name (and the image needs confirming).
        $this->assertSame(
            ['img_alt_empty', 'link_empty'],
            $this->ids('<a href="https://example.com"><img src="i.png" alt=""></a>')
        );
        $this->assertSame([], $this->ids('<a href="https://example.com"><img src="i.png" alt="College home"></a>'));
        $this->assertSame([], $this->ids('<a href="https://example.com" aria-label="College home"><i class="icon"></i></a>'));
        $this->assertSame(['link_unclear'], $this->ids('<a href="https://example.com">Click here</a>'));
        $this->assertSame(['link_unclear'], $this->ids('<a href="https://example.com">Read more…</a>'));
        $this->assertSame(
            ['link_url_text'],
            $this->ids('<a href="https://example.com/a/very/long/path">https://example.com/a/very/long/path</a>')
        );
        $this->assertSame([], $this->ids('<a name="anchor"></a>'));
        $this->assertSame([], $this->ids('<a href="https://example.com/reading">Week 2 reading list</a>'));
    }

    /**
     * Tables.
     */
    public function test_tables(): void {
        $data = '<table><tr><td>A</td><td>B</td></tr><tr><td>1</td><td>2</td></tr></table>';
        $this->assertSame(['table_no_headers'], $this->ids($data));
        $this->assertSame([], $this->ids('<table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>'));
        $this->assertSame([], $this->ids('<table role="presentation"><tr><td>A</td><td>B</td></tr>' .
            '<tr><td>1</td><td>2</td></tr></table>'));
        $this->assertSame([], $this->ids('<table><tr><td>Only one row</td><td>x</td></tr></table>'));
        $this->assertSame([], $this->ids('<table><tr><td scope="col">A</td><td scope="col">B</td></tr>' .
            '<tr><td>1</td><td>2</td></tr></table>'));
    }

    /**
     * Headings.
     */
    public function test_headings(): void {
        $this->assertSame([], $this->ids('<h3>First</h3><h4>Second</h4><h3>Third</h3>'));
        $this->assertSame(['heading_skip'], $this->ids('<h2>First</h2><h4>Skipped</h4>'));
        $this->assertSame([], $this->ids('<h4>Fragment may start deep</h4>'));
        $this->assertSame(['heading_empty'], $this->ids('<h3>  </h3>'));
    }

    /**
     * Iframes.
     */
    public function test_iframes(): void {
        $this->assertSame(['iframe_title_missing'], $this->ids('<iframe src="https://player.vimeo.com/video/1"></iframe>'));
        $this->assertSame(
            ['iframe_title_generic'],
            $this->ids('<iframe src="https://player.vimeo.com/video/1" title="video"></iframe>')
        );
        $this->assertSame(
            [],
            $this->ids('<iframe src="https://player.vimeo.com/video/1" title="Week 1 lecture: introduction"></iframe>')
        );
    }

    /**
     * Contrast is only evaluated when both colours are reliably known.
     */
    public function test_contrast(): void {
        $this->assertSame(
            ['contrast_fail'],
            $this->ids('<p style="color:#777777;background-color:#888888">Low contrast text</p>')
        );
        $this->assertSame([], $this->ids('<p style="color:#000;background-color:#fff">Fine</p>'));
        // Background unknown (from theme): no finding.
        $this->assertSame([], $this->ids('<p style="color:#777777">Only foreground</p>'));
        // Ancestor with a class: background could come from CSS, so undeterminable.
        $this->assertSame([], $this->ids('<div class="box" style=""><span style="color:#777">Text</span></div>'));
        // Background from an unstyled-by-class ancestor is reliable.
        $this->assertSame(
            ['contrast_fail'],
            $this->ids('<div style="background-color:#ffffff"><span style="color:#dddddd">Faint text</span></div>')
        );
        // Gradient / image backgrounds are not determinable.
        $this->assertSame([], $this->ids('<p style="color:#777;background:url(x.png) #888">Text</p>'));
        // Ratio between 3 and 4.5 with unknown size needs review.
        $this->assertSame(
            ['contrast_review'],
            $this->ids('<p style="color:#777777;background-color:#ffffff">Medium contrast</p>')
        );
        // Same colours known to be large text pass.
        $this->assertSame(
            [],
            $this->ids('<p style="color:#777777;background-color:#ffffff;font-size:32px">Large text</p>')
        );
        // Known normal-size text below 4.5:1 fails.
        $this->assertSame(
            ['contrast_fail'],
            $this->ids('<p style="color:#777777;background-color:#ffffff;font-size:14px">Small text</p>')
        );
        // Semi-transparent colours are not evaluated.
        $this->assertSame([], $this->ids('<p style="color:rgba(0,0,0,0.5);background-color:#fff">Text</p>'));
    }

    /**
     * Contrast maths matches known WCAG values.
     */
    public function test_contrast_ratio(): void {
        $this->assertEquals(21.0, contrast::ratio([0, 0, 0], [255, 255, 255]));
        $this->assertEquals(4.54, contrast::ratio(contrast::parse('#767676'), contrast::parse('#ffffff')));
        $this->assertSame([255, 0, 0], contrast::parse('red'));
        $this->assertSame([17, 34, 51], contrast::parse('#123'));
        $this->assertSame([10, 20, 30], contrast::parse('rgb(10, 20, 30)'));
        $this->assertSame([10, 20, 30], contrast::parse('rgba(10 20 30 / 1)'));
        $this->assertNull(contrast::parse('var(--x)'));
        $this->assertNull(contrast::parse('#00000080'));
        $this->assertSame(contrast::TRANSPARENT, contrast::parse('transparent'));
    }

    /**
     * Identical elements get distinct but stable signatures.
     */
    public function test_signatures_stable(): void {
        $checker = new html_checker();
        $html = '<p>Intro</p><img src="a.png"><img src="a.png">';
        $first = array_map(fn($i) => $i->signature, $checker->check($html));
        $second = array_map(fn($i) => $i->signature, $checker->check('<p>Changed intro</p>' . substr($html, 12)));
        $this->assertCount(2, $first);
        $this->assertNotEquals($first[0], $first[1]);
        $this->assertSame($first, $second);
    }

    /**
     * Excerpts redact Vimeo hashes and are bounded.
     */
    public function test_excerpt_safety(): void {
        $issues = (new html_checker())->check('<iframe src="https://player.vimeo.com/video/123?h=abcdef1234"></iframe>');
        $this->assertStringNotContainsString('abcdef1234', $issues[0]->excerpt);
        $long = '<img src="data:image/png;base64,' . str_repeat('A', 5000) . '">';
        $issues = (new html_checker())->check($long);
        $this->assertLessThanOrEqual(300, \core_text::strlen($issues[0]->excerpt));
    }

    /**
     * Links opening new windows, image map areas.
     */
    public function test_new_window_and_areas(): void {
        $this->assertSame(
            ['link_new_window'],
            $this->ids('<a href="https://example.com/a" target="_blank">Course handbook</a>')
        );
        $this->assertSame([], $this->ids('<a href="https://example.com/a" target="_blank">'
            . 'Course handbook (opens in new window)</a>'));
        $this->assertSame([], $this->ids('<a href="https://example.com/a" target="_blank" '
            . 'title="Opens in a new tab">Handbook</a>'));
        $this->assertSame(
            ['area_alt_missing'],
            $this->ids('<map name="m"><area href="a.html" coords="0,0,10,10"><area href="b.html" alt="Room B"></map>')
        );
    }

    /**
     * Structure checks.
     */
    public function test_structure(): void {
        $long = '<p>' . str_repeat('Acupuncture point location and needling depth. ', 70) . '</p>';
        $this->assertSame(['content_no_headings'], $this->ids($long));
        $this->assertSame([], $this->ids('<h3>Points</h3>' . $long));
        $this->assertSame(['heading_visual'], $this->ids('<p><strong>Learning outcomes</strong></p><p>By the end you will…</p>'));
        $this->assertSame([], $this->ids('<p><strong>Remember to bring your notes.</strong></p><p>Next</p>'));
        $this->assertSame([], $this->ids('<p>Some <strong>bold</strong> words.</p><p>Next</p>'));
        $this->assertSame(['list_manual'], $this->ids('<p>• First point</p><p>• Second point</p><p>• Third point</p>'));
        $this->assertSame(['list_manual'], $this->ids('<p>1. Wash hands</p><p>2. Prepare needles</p>'));
        $this->assertSame([], $this->ids('<p>1. Only one numbered paragraph</p><p>Plain text</p>'));
        $this->assertSame(['legend_empty'], $this->ids('<fieldset><legend> </legend></fieldset>'));
        $issues = (new html_checker())->check('<font color="red">a</font><font>b</font><center>c</center>');
        $this->assertSame(['markup_deprecated', 'markup_deprecated'], array_map(fn($i) => $i->checkid, $issues));
        $this->assertSame(2, $issues[0]->params['count']);
    }

    /**
     * Text presentation checks.
     */
    public function test_text(): void {
        $this->assertSame(['text_underline'], $this->ids('<p><u>Important</u></p>'));
        $this->assertSame(['text_underline'], $this->ids('<span style="text-decoration: underline">Important</span>'));
        $this->assertSame([], $this->ids('<a href="https://example.com/x"><u>Course page</u></a>'));
        $this->assertSame(
            ['text_all_caps'],
            $this->ids('<p>PLEASE READ ALL OF THE SAFETY GUIDANCE BEFORE THE CLINIC SESSION</p>')
        );
        $this->assertSame([], $this->ids('<p>NHS and WCAG AA are acronyms in normal text.</p>'));
        $this->assertSame(['motion_blink'], $this->ids('<marquee>News</marquee>'));
    }

    /**
     * Tables and media.
     */
    public function test_tables_extra_and_media(): void {
        $this->assertSame(['table_complex'], $this->ids('<table><tr><th colspan="2">Week</th></tr>' .
            '<tr><td>1</td><td>2</td></tr></table>'));
        $this->assertSame([], $this->ids('<table><tr><th id="w" colspan="2">Week</th></tr>' .
            '<tr><td headers="w">1</td><td headers="w">2</td></tr></table>'));
        $this->assertSame(['table_summary_duplicate'], $this->ids('<table summary="Timetable"><caption>Timetable</caption>' .
            '<tr><th>Day</th><th>Time</th></tr><tr><td>Mon</td><td>9</td></tr></table>'));
        $this->assertSame(['object_title_missing'], $this->ids('<object data="x.swf"><embed src="x.swf"></object>'));
        $this->assertSame([], $this->ids('<object data="x.pdf" title="Course handbook"></object>'));
        $this->assertSame(['media_autoplay', 'media_no_captions'], $this->ids('<video src="v.mp4" autoplay></video>'));
        $this->assertSame([], $this->ids('<video src="v.mp4" controls><track kind="captions" src="v.vtt"></video>'));
        $this->assertSame(['media_autoplay'], $this->ids('<audio src="a.mp3" autoplay></audio>'));
    }
}
