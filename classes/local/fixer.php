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

use local_accessibilityreview\event\content_fixed;
use local_accessibilityreview\local\checks\check_registry;
use local_accessibilityreview\local\checks\html_checker;
use local_accessibilityreview\local\checks\issue;
use local_accessibilityreview\local\content\content_item;
use local_accessibilityreview\local\fix\fix_engine;
use local_accessibilityreview\local\fix\fix_exception;

/**
 * Applies and undoes fixes to course content.
 *
 * Only stored HTML in the content areas listed in SOURCES can be changed.
 * Every change keeps the previous field content so it can be undone, as long
 * as nobody has edited the field since. Quiz questions are never edited here,
 * because questions are versioned and may already have been attempted.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fixer {
    /** @var string Table name. */
    public const TABLE = 'local_accessibilityreview_fix';

    /** @var array<string, array> Writable sources by provider: table, text and format fields, capability and file area. */
    private const SOURCES = [
        'course_summary' => ['table' => 'course', 'field' => 'summary', 'format' => 'summaryformat',
            'cap' => 'moodle/course:update', 'component' => 'course', 'filearea' => 'summary', 'fileitem' => false],
        'section_summary' => ['table' => 'course_sections', 'field' => 'summary', 'format' => 'summaryformat',
            'cap' => 'moodle/course:update', 'component' => 'course', 'filearea' => 'section', 'fileitem' => true],
        'activity_intro' => ['table' => null, 'field' => 'intro', 'format' => 'introformat',
            'cap' => 'moodle/course:manageactivities', 'component' => null, 'filearea' => 'intro', 'fileitem' => false],
        'page_content' => ['table' => 'page', 'field' => 'content', 'format' => 'contentformat',
            'cap' => 'moodle/course:manageactivities', 'component' => 'mod_page', 'filearea' => 'content', 'fileitem' => false],
        'book_chapter' => ['table' => 'book_chapters', 'field' => 'content', 'format' => 'contentformat',
            'cap' => 'mod/book:edit', 'component' => 'mod_book', 'filearea' => 'chapter', 'fileitem' => true],
        'glossary_entry' => ['table' => 'glossary_entries', 'field' => 'definition', 'format' => 'definitionformat',
            'cap' => 'mod/glossary:manageentries', 'component' => 'mod_glossary', 'filearea' => 'entry', 'fileitem' => true],
        'lesson_page' => ['table' => 'lesson_pages', 'field' => 'contents', 'format' => 'contentsformat',
            'cap' => 'mod/lesson:edit', 'component' => 'mod_lesson', 'filearea' => 'page_contents', 'fileitem' => true],
    ];

    /**
     * Tables holding the content of each provider with a fixed table (activity intros are found through cmid).
     *
     * @return array<string, string> provider => table
     */
    public static function item_tables(): array {
        $tables = [];
        foreach (self::SOURCES as $provider => $def) {
            if ($def['table'] !== null) {
                $tables[$provider] = $def['table'];
            }
        }
        return $tables;
    }

    /**
     * Whether the administrator has enabled fixes.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return !empty(get_config('local_accessibilityreview', 'enablefixes'));
    }

    /**
     * Resolve where a finding's (or fix record's) content is stored.
     *
     * @param \stdClass $ref Record with courseid, cmid, provider, itemid, field.
     * @return \stdClass|null Source details, or null when the content area cannot be edited here.
     */
    public static function source(\stdClass $ref): ?\stdClass {
        $def = self::SOURCES[$ref->provider] ?? null;
        if (!$def || $def['field'] !== $ref->field) {
            return null;
        }
        $source = (object) $def;
        $source->id = (int) $ref->itemid;
        $source->courseid = (int) $ref->courseid;
        $source->cmid = (int) $ref->cmid;
        if ($ref->provider === 'activity_intro') {
            $cm = get_coursemodule_from_id('', (int) $ref->cmid, (int) $ref->courseid, false, IGNORE_MISSING);
            if (!$cm || (int) $cm->instance !== (int) $ref->itemid) {
                return null;
            }
            $source->table = $cm->modname;
            $source->component = 'mod_' . $cm->modname;
        }
        $source->context = $source->cmid ? \context_module::instance($source->cmid, IGNORE_MISSING)
            : \context_course::instance($source->courseid, IGNORE_MISSING);
        if (!$source->context) {
            return null;
        }
        $source->fileitemid = $source->fileitem ? $source->id : 0;
        $source->provider = $ref->provider;
        return $source;
    }

    /**
     * Quick test for showing a "Fix" link in lists: no database queries beyond cached capability checks.
     * The fix page itself checks everything with why_not().
     *
     * @param \stdClass $finding
     * @return bool
     */
    public static function might_fix(\stdClass $finding): bool {
        return self::enabled() && !empty($finding->present)
            && in_array($finding->checkid, check_registry::fixable(), true)
            && isset(self::SOURCES[$finding->provider])
            && in_array($finding->state, [finding_manager::STATE_OPEN, finding_manager::STATE_INPROGRESS], true)
            && has_capability('local/accessibilityreview:fix', \context_course::instance((int) $finding->courseid));
    }

    /**
     * Whether the user may fix this finding here, and if not, why.
     *
     * @param \stdClass $finding
     * @param int|null $userid Defaults to the current user.
     * @return string|null Null when allowed, otherwise an error code (string fix_error_{code}).
     */
    public static function why_not(\stdClass $finding, ?int $userid = null): ?string {
        if (!self::enabled()) {
            return 'disabled';
        }
        if (!in_array($finding->checkid, check_registry::fixable(), true)) {
            return 'notfixable';
        }
        if (
            !$finding->present || !in_array(
                $finding->state,
                [finding_manager::STATE_OPEN, finding_manager::STATE_INPROGRESS],
                true
            )
        ) {
            return 'notopen';
        }
        $source = self::source($finding);
        if (!$source) {
            return 'notsupported';
        }
        if (
            !has_capability('local/accessibilityreview:fix', \context_course::instance((int) $finding->courseid), $userid)
                || !has_capability($source->cap, $source->context, $userid)
        ) {
            return 'nopermission';
        }
        return null;
    }

    /**
     * Read the stored field.
     *
     * @param \stdClass $source
     * @return array{0: string, 1: int} [text, format]
     * @throws fix_exception
     */
    public static function read(\stdClass $source): array {
        global $DB;
        $record = $DB->get_record($source->table, ['id' => $source->id], 'id, ' . $source->field . ', ' . $source->format);
        if (!$record) {
            throw new fix_exception('notfound');
        }
        $format = (int) $record->{$source->format};
        if (!in_array($format, [(int) FORMAT_HTML, (int) FORMAT_MOODLE], true)) {
            throw new fix_exception('format');
        }
        return [(string) $record->{$source->field}, $format];
    }

    /**
     * Find the finding's issue in the current content.
     *
     * @param \stdClass $finding
     * @param string $html
     * @param html_checker $checker
     * @return issue
     * @throws fix_exception When the issue is no longer present.
     */
    public static function locate(\stdClass $finding, string $html, html_checker $checker): issue {
        foreach ($checker->check($html) as $issue) {
            $identity = finding_manager::identity(
                (int) $finding->courseid,
                $finding->provider,
                (int) $finding->itemid,
                $finding->field,
                $issue->checkid,
                $issue->signature
            );
            if ($identity === $finding->identity) {
                return $issue;
            }
        }
        throw new fix_exception('changed');
    }

    /**
     * Compute a fix without saving it.
     *
     * @param \stdClass $finding
     * @param array $data Fix input.
     * @return array{old: string, new: string, source: \stdClass, issue: issue}
     * @throws fix_exception
     */
    public static function preview(\stdClass $finding, array $data): array {
        $source = self::source($finding);
        if (!$source) {
            throw new fix_exception('notsupported');
        }
        [$old] = self::read($source);
        $checker = html_checker::from_config();
        $issue = self::locate($finding, $old, $checker);
        $new = fix_engine::apply($old, $issue, $data, $checker);
        if ($new === $old) {
            throw new fix_exception('nochange');
        }
        return ['old' => $old, 'new' => $new, 'source' => $source, 'issue' => $issue];
    }

    /**
     * Apply a fix and re-check the item.
     *
     * @param \stdClass $finding
     * @param array $data Fix input.
     * @param int $userid
     * @return int Fix record id.
     * @throws fix_exception
     */
    public static function apply(\stdClass $finding, array $data, int $userid): int {
        global $DB;
        $reason = self::why_not($finding, $userid);
        if ($reason !== null) {
            throw new fix_exception($reason);
        }
        $result = self::preview($finding, $data);
        $source = $result['source'];
        $summary = array_intersect_key($data, array_flip(['text', 'decorative', 'headers', 'level', 'mode', 'label']));
        $transaction = $DB->start_delegated_transaction();
        try {
            // Guard against an edit saved between the preview and now.
            [$current] = self::read($source);
            if ($current !== $result['old']) {
                throw new fix_exception('changed');
            }
            self::write($source, $result['new']);
            $fixid = (int) $DB->insert_record(self::TABLE, (object) [
                'courseid' => $finding->courseid,
                'cmid' => $finding->cmid,
                'findingid' => $finding->id,
                'checkid' => $finding->checkid,
                'provider' => $finding->provider,
                'itemid' => $finding->itemid,
                'field' => $finding->field,
                'itemlabel' => \core_text::substr((string) $finding->itemlabel, 0, 255),
                'oldcontent' => $result['old'],
                'newcontent' => $result['new'],
                'summary' => json_encode($summary),
                'status' => 'applied',
                'userid' => $userid,
                'timecreated' => time(),
            ]);
            history::add(
                'finding',
                (int) $finding->id,
                (int) $finding->courseid,
                $userid,
                'fixed',
                $finding->state,
                $finding->state,
                self::describe($finding->checkid, $summary)
            );
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        self::after_write($source);
        self::recheck($finding, $result['new']);
        content_fixed::create_from_fix($fixid, $source->context, 'applied')->trigger();
        return $fixid;
    }

    /**
     * Undo a fix, if the field has not been edited since.
     *
     * @param \stdClass $fix Fix record.
     * @param int $userid
     * @return void
     * @throws fix_exception
     */
    public static function undo(\stdClass $fix, int $userid): void {
        global $DB;
        if ($fix->status !== 'applied') {
            throw new fix_exception('alreadyundone');
        }
        $source = self::source($fix);
        if (!$source || !self::enabled()) {
            throw new fix_exception('notsupported');
        }
        if (
            !has_capability('local/accessibilityreview:fix', \context_course::instance((int) $fix->courseid), $userid)
                || !has_capability($source->cap, $source->context, $userid)
        ) {
            throw new fix_exception('nopermission');
        }
        $finding = $fix->findingid ? $DB->get_record(finding_manager::TABLE, ['id' => $fix->findingid]) : null;
        $transaction = $DB->start_delegated_transaction();
        try {
            [$current] = self::read($source);
            if ($current !== (string) $fix->newcontent) {
                throw new fix_exception('undochanged');
            }
            self::write($source, (string) $fix->oldcontent);
            $DB->update_record(self::TABLE, (object) ['id' => $fix->id, 'status' => 'undone', 'undoneby' => $userid,
                'timeundone' => time()]);
            if ($finding) {
                history::add(
                    'finding',
                    (int) $finding->id,
                    (int) $finding->courseid,
                    $userid,
                    'fixundone',
                    $finding->state,
                    $finding->state,
                    null
                );
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        self::after_write($source);
        if ($finding) {
            self::recheck($finding, (string) $fix->oldcontent);
        }
        content_fixed::create_from_fix((int) $fix->id, $source->context, 'undone')->trigger();
    }

    /**
     * Save new field content.
     *
     * @param \stdClass $source
     * @param string $text
     * @return void
     */
    private static function write(\stdClass $source, string $text): void {
        global $DB, $CFG;
        $now = time();
        switch ($source->provider) {
            case 'section_summary':
                require_once($CFG->dirroot . '/course/lib.php');
                $section = $DB->get_record('course_sections', ['id' => $source->id], '*', MUST_EXIST);
                course_update_section($source->courseid, $section, ['summary' => $text]);
                return;
            case 'course_summary':
                $DB->update_record('course', (object) ['id' => $source->id, 'summary' => $text, 'timemodified' => $now]);
                return;
            case 'page_content':
                $revision = (int) $DB->get_field('page', 'revision', ['id' => $source->id]);
                $DB->update_record('page', (object) ['id' => $source->id, 'content' => $text, 'revision' => $revision + 1,
                    'timemodified' => $now]);
                return;
            case 'book_chapter':
                $bookid = (int) $DB->get_field('book_chapters', 'bookid', ['id' => $source->id], MUST_EXIST);
                $DB->update_record('book_chapters', (object) ['id' => $source->id, 'content' => $text,
                    'timemodified' => $now]);
                $DB->execute('UPDATE {book} SET revision = revision + 1 WHERE id = ?', [$bookid]);
                return;
            default:
                $record = (object) ['id' => $source->id, $source->field => $text];
                $columns = $DB->get_columns($source->table);
                if (isset($columns['timemodified'])) {
                    $record->timemodified = $now;
                }
                $DB->update_record($source->table, $record);
        }
    }

    /**
     * Clear caches that may hold the old content.
     *
     * @param \stdClass $source
     * @return void
     */
    private static function after_write(\stdClass $source): void {
        if (in_array($source->provider, ['course_summary', 'activity_intro'], true)) {
            rebuild_course_cache($source->courseid, true);
        }
        if ($source->provider === 'course_summary') {
            \cache_helper::purge_by_event('changesincourse');
        }
    }

    /**
     * Re-check the edited item straight away, so fixed findings resolve without waiting for a scan.
     *
     * @param \stdClass $finding
     * @param string $text New stored content.
     * @return void
     */
    private static function recheck(\stdClass $finding, string $text): void {
        $item = new content_item(
            $finding->provider,
            (int) $finding->courseid,
            (int) $finding->cmid,
            (int) $finding->itemid,
            $finding->field,
            (string) $finding->itemlabel,
            $text,
            0
        );
        finding_manager::sync_item((int) $finding->lastscanid, $item, html_checker::from_config()->check($text));
    }

    /**
     * Plain-text description of a fix for the history log.
     *
     * @param string $checkid
     * @param array $summary
     * @return string
     */
    public static function describe(string $checkid, array $summary): string {
        $p = 'local_accessibilityreview';
        if (!empty($summary['decorative'])) {
            return get_string('fixdesc_decorative', $p);
        }
        if (isset($summary['text'])) {
            return get_string('fixdesc_text', $p, \core_text::substr(trim((string) $summary['text']), 0, 200));
        }
        if (isset($summary['headers'])) {
            return get_string('fixdesc_headers_' . clean_param($summary['headers'], PARAM_ALPHA), $p);
        }
        if (isset($summary['level'])) {
            return get_string('fixdesc_level', $p, (int) $summary['level']);
        }
        if (($summary['mode'] ?? '') === 'label') {
            return get_string('fixdesc_label', $p);
        }
        if (($summary['mode'] ?? '') === 'sametab') {
            return get_string('fixdesc_sametab', $p);
        }
        return get_string('fixdesc_' . clean_param($checkid, PARAM_ALPHAEXT), $p);
    }

    /** @var string[] Checks that can be fixed in bulk from one page. */
    public const BULK_CHECKS = ['img_alt_missing', 'img_alt_suspicious', 'iframe_title_missing', 'iframe_title_generic',
        'object_title_missing', 'link_new_window', 'media_autoplay'];

    /**
     * Prepare several findings for a bulk fix page, reading each content item once.
     *
     * @param \stdClass[] $findings
     * @return array<int, array{issue: issue|null, imageurl: string|null, suggestion: string, error: string|null}>
     */
    public static function prepare(array $findings): array {
        $checker = html_checker::from_config();
        $items = [];
        $out = [];
        foreach ($findings as $finding) {
            $entry = ['issue' => null, 'imageurl' => null, 'suggestion' => '', 'error' => null];
            if (!self::might_fix($finding)) {
                $entry['error'] = self::enabled() ? 'notopen' : 'disabled';
                $out[$finding->id] = $entry;
                continue;
            }
            // Source, permission and content are resolved once per content item.
            $key = $finding->provider . '|' . $finding->itemid . '|' . $finding->field;
            try {
                if (!isset($items[$key])) {
                    $items[$key] = ['error' => null];
                    $source = self::source($finding);
                    if (!$source) {
                        $items[$key]['error'] = 'notsupported';
                    } else if (!has_capability($source->cap, $source->context)) {
                        $items[$key]['error'] = 'nopermission';
                    } else {
                        try {
                            [$html] = self::read($source);
                            $items[$key] += ['source' => $source, 'html' => $html, 'issues' => $checker->check($html)];
                        } catch (fix_exception $e) {
                            $items[$key]['error'] = $e->reason;
                        }
                    }
                }
                $item = $items[$key];
                if ($item['error'] !== null) {
                    throw new fix_exception($item['error']);
                }
                foreach ($item['issues'] as $issue) {
                    if (
                        finding_manager::identity(
                            (int) $finding->courseid,
                            $finding->provider,
                            (int) $finding->itemid,
                            $finding->field,
                            $issue->checkid,
                            $issue->signature
                        ) === $finding->identity
                    ) {
                        $entry['issue'] = $issue;
                        break;
                    }
                }
                if (!$entry['issue']) {
                    throw new fix_exception('changed');
                }
                $entry['imageurl'] = self::image_src($item['source'], $item['html'], $entry['issue']);
                $entry['suggestion'] = self::suggested_title($entry['issue'], $item['html']);
            } catch (fix_exception $e) {
                $entry['error'] = $e->reason;
            }
            $out[$finding->id] = $entry;
        }
        return $out;
    }

    /**
     * Rewrite @@PLUGINFILE@@ links in an HTML excerpt so images can be shown on the fix page.
     *
     * @param \stdClass $source
     * @param string $html
     * @return string
     */
    public static function rewrite_files(\stdClass $source, string $html): string {
        return file_rewrite_pluginfile_urls(
            $html,
            'pluginfile.php',
            $source->context->id,
            $source->component,
            $source->filearea,
            $source->fileitemid
        );
    }

    /**
     * Address of the image an alt-text finding refers to, for a preview.
     *
     * @param \stdClass $finding
     * @param issue $issue
     * @return string|null
     */
    public static function image_url(\stdClass $finding, issue $issue): ?string {
        if ($issue->tag !== 'img') {
            return null;
        }
        $source = self::source($finding);
        try {
            [$html] = $source ? self::read($source) : [null];
        } catch (fix_exception $e) {
            return null;
        }
        return $html !== null ? self::image_src($source, $html, $issue) : null;
    }

    /**
     * Displayable address of the image an issue refers to.
     *
     * @param \stdClass $source
     * @param string $html
     * @param issue $issue
     * @return string|null
     */
    private static function image_src(\stdClass $source, string $html, issue $issue): ?string {
        if ($issue->tag !== 'img') {
            return null;
        }
        $doc = html_checker::load_dom($html);
        $img = $doc ? $doc->getElementsByTagName('img')->item($issue->ordinal) : null;
        if (!$img instanceof \DOMElement) {
            return null;
        }
        $src = $img->getAttribute('src');
        if (str_contains($src, '@@PLUGINFILE@@')) {
            $src = file_rewrite_pluginfile_urls(
                $src,
                'pluginfile.php',
                $source->context->id,
                $source->component,
                $source->filearea,
                $source->fileitemid
            );
        }
        return preg_match('~^(https?:|/)~i', $src) ? $src : null;
    }

    /**
     * Suggested title for an embedded video, from the cached video metadata.
     *
     * @param issue $issue
     * @param string $html
     * @return string
     */
    public static function suggested_title(issue $issue, string $html): string {
        global $DB;
        if (!in_array($issue->tag, ['iframe', 'object', 'embed'], true)) {
            return '';
        }
        $doc = html_checker::load_dom($html);
        $el = $doc ? $doc->getElementsByTagName($issue->tag)->item($issue->ordinal) : null;
        if (!$el instanceof \DOMElement) {
            return '';
        }
        $url = links\link_manager::normalise($el->getAttribute($issue->tag === 'object' ? 'data' : 'src'));
        if ($url === null) {
            return '';
        }
        $metadata = $DB->get_field(links\link_manager::TABLE, 'metadata', ['urlhash' => sha1($url)]);
        $title = trim((string) (json_decode((string) $metadata, true)['title'] ?? ''));
        return $title !== '' ? get_string('suggest_videotitle', 'local_accessibilityreview', $title) : '';
    }
}
