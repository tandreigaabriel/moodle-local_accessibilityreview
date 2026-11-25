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

namespace local_accessibilityreview\output;

use html_table;
use html_table_cell;
use html_writer;
use moodle_url;
use local_accessibilityreview\local\checks\check_registry;
use local_accessibilityreview\local\content\provider_registry;
use local_accessibilityreview\local\finding_manager;
use local_accessibilityreview\local\findings_filter;
use local_accessibilityreview\local\fixer;
use local_accessibilityreview\local\links\link_classifier;
use local_accessibilityreview\local\links\vimeo;
use local_accessibilityreview\local\resource_manager;

/**
 * Renderer for accessibility reports.
 *
 * All dynamic values are escaped with s(), format_string() or html_writer.
 * Status is always conveyed in text, never by colour alone.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /** @var array<int, string> Cache of user names. */
    private array $usernames = [];

    /**
     * Shorthand for plugin strings.
     *
     * @param string $id
     * @param mixed $a
     * @return string
     */
    private function str(string $id, $a = null): string {
        return get_string($id, 'local_accessibilityreview', $a);
    }

    /**
     * Format a timestamp or "never".
     *
     * @param int $time
     * @return string
     */
    public function time(int $time): string {
        return $time ? userdate($time, get_string('strftimedatetimeshort', 'langconfig')) : $this->str('never');
    }

    /**
     * Name of a user (0 = system).
     *
     * @param int $userid
     * @return string
     */
    public function username(int $userid): string {
        if (!$userid) {
            return $this->str('systemuser');
        }
        if (!isset($this->usernames[$userid])) {
            $user = \core_user::get_user($userid);
            $this->usernames[$userid] = $user ? fullname($user) : $this->str('deleteduser');
        }
        return $this->usernames[$userid];
    }

    /**
     * Text badge.
     *
     * @param string $text
     * @param string $variant Bootstrap variant.
     * @return string
     */
    public function badge(string $text, string $variant = 'secondary'): string {
        return html_writer::span(s($text), 'badge badge-' . $variant . ' bg-' . $variant . ' local-accessibilityreview-badge');
    }

    /**
     * Severity badge.
     *
     * @param string $severity
     * @return string
     */
    public function severity(string $severity): string {
        $variant = ['error' => 'danger', 'warning' => 'warning', 'notice' => 'info'][$severity] ?? 'secondary';
        return $this->badge($this->str('severity_' . $severity), $variant);
    }

    /**
     * Finding state label.
     *
     * @param string $state
     * @return string
     */
    public function state(string $state): string {
        $variant = ['open' => 'danger', 'inprogress' => 'warning', 'resolved' => 'success',
            'exception' => 'secondary'][$state] ?? 'secondary';
        return $this->badge($this->str('state_' . $state), $variant);
    }

    /**
     * The standing disclaimer shown on every report.
     *
     * @return string
     */
    public function disclaimer(): string {
        return $this->notification($this->str('disclaimer'), \core\output\notification::NOTIFY_INFO, false);
    }

    /**
     * Course report tabs.
     *
     * @param int $courseid
     * @param string $current
     * @return string
     */
    public function course_tabs(int $courseid, string $current): string {
        $tabs = [];
        $names = ['overview', 'findings', 'links', 'pdf', 'video', 'scorm'];
        if (fixer::enabled()) {
            $names[] = 'fixes';
        }
        foreach ($names as $tab) {
            $tabs[] = new \tabobject($tab, new moodle_url(
                '/local/accessibilityreview/index.php',
                ['id' => $courseid, 'tab' => $tab]
            ), $this->str('tab_' . $tab));
        }
        return $this->tabtree($tabs, $current);
    }

    /**
     * Scan status panel with progress, last completed scan and failures.
     *
     * @param \stdClass $status From course_status::get().
     * @param int $courseid
     * @param bool $canscan
     * @return string
     */
    public function status_panel(\stdClass $status, int $courseid, bool $canscan): string {
        $out = html_writer::tag('h3', $this->str('scanstatus'));
        $out .= html_writer::tag('p', html_writer::tag('strong', $this->str('status_' . $status->status)) . ' — ' .
            $this->str('status_' . $status->status . '_desc'));
        $rows = [];
        if ($status->lastcomplete) {
            $rows[] = [$this->str('lastcompletedscan'), $this->time((int) $status->lastcomplete->timefinished)];
            $rows[] = [$this->str('itemsscanned'), (int) $status->lastcomplete->itemsdone];
            if ((int) $status->lastcomplete->itemsfailed > 0) {
                $rows[] = [$this->str('itemsfailed'), (int) $status->lastcomplete->itemsfailed];
            }
            $rows[] = [$this->str('openfindings'), (int) $status->open];
            $rows[] = [$this->str('inventoryreviewed'), $this->str(
                'countof',
                (object) ['done' => $status->resourcesreviewed, 'total' => $status->resources]
            )];
        } else {
            $rows[] = [$this->str('lastcompletedscan'), $this->str('never')];
        }
        if ($status->active) {
            $rows[] = [$this->str('currentscan'), $this->str('scanprogress', (object) [
                'status' => $this->str('scanstatus_' . $status->active->status),
                'items' => (int) $status->active->itemsdone,
                'time' => $this->time((int) $status->active->timecreated),
            ])];
        }
        $table = new html_table();
        $table->attributes['class'] = 'generaltable local-accessibilityreview-status';
        $table->caption = $this->str('scanstatus');
        $table->captionhide = true;
        foreach ($rows as $row) {
            $cell = new html_table_cell(s($row[0]));
            $cell->header = true;
            $cell->scope = 'row';
            $table->data[] = [$cell, s((string) $row[1])];
        }
        $out .= html_writer::table($table);

        $failed = $status->latest && $status->latest->status === 'failed' ? $status->latest :
            ($status->lastcomplete && (int) $status->lastcomplete->itemsfailed > 0 ? $status->lastcomplete : null);
        if ($failed && trim((string) $failed->errormessage) !== '') {
            $out .= $this->notification(
                html_writer::tag('p', $this->str('scanerrors')) .
                html_writer::tag('pre', s($failed->errormessage), ['class' => 'local-accessibilityreview-errors']),
                \core\output\notification::NOTIFY_ERROR,
                false
            );
        }

        if ($canscan) {
            if ($status->active) {
                $out .= html_writer::tag('p', $this->str('scanalreadyqueued'));
            } else {
                $url = new moodle_url('/local/accessibilityreview/scan.php', ['id' => $courseid, 'sesskey' => sesskey()]);
                $form = html_writer::input_hidden_params($url);
                $form .= html_writer::tag('button', $this->str('queuescan'), ['type' => 'submit', 'class' => 'btn btn-primary']);
                $out .= html_writer::tag('form', $form, ['method' => 'post', 'action' => $url->out_omit_querystring()]);
                $out .= html_writer::tag('p', $this->str('queuescan_help'), ['class' => 'text-muted small']);
            }
        }
        return html_writer::div($out, 'local-accessibilityreview-statuspanel mb-4');
    }

    /**
     * Open findings by category and severity.
     *
     * @param array $counts [category][severity] => count.
     * @return string
     */
    public function category_summary(array $counts): string {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->caption = $this->str('summarybycategory');
        $table->head = [$this->str('category')];
        foreach (check_registry::severities() as $severity) {
            $table->head[] = $this->str('severity_' . $severity);
        }
        foreach (check_registry::categories() as $category) {
            $cell = new html_table_cell($this->str('category_' . $category));
            $cell->header = true;
            $cell->scope = 'row';
            $row = [$cell];
            foreach (check_registry::severities() as $severity) {
                $row[] = (int) ($counts[$category][$severity] ?? 0);
            }
            $table->data[] = $row;
        }
        return html_writer::table($table);
    }

    /**
     * Simple key/count table.
     *
     * @param string $caption
     * @param array $rows label => count
     * @return string
     */
    public function count_table(string $caption, array $rows): string {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->caption = $caption;
        $table->head = [$this->str('status'), $this->str('count')];
        foreach ($rows as $label => $count) {
            $cell = new html_table_cell(s($label));
            $cell->header = true;
            $cell->scope = 'row';
            $table->data[] = [$cell, (int) $count];
        }
        return html_writer::table($table);
    }

    /**
     * Labelled select element.
     *
     * @param string $name
     * @param string $label
     * @param array $options
     * @param string $selected
     * @return string
     */
    private function filter_select(string $name, string $label, array $options, string $selected): string {
        $id = 'local-accessibilityreview-filter-' . $name;
        return html_writer::div(
            html_writer::label($label, $id, false, ['class' => 'mr-1 me-1']) .
            html_writer::select($options, $name, $selected, false, ['id' => $id, 'class' => 'custom-select form-select']),
            'local-accessibilityreview-filter mr-3 me-3 mb-2'
        );
    }

    /**
     * Findings filter form (GET).
     *
     * @param moodle_url $baseurl
     * @param findings_filter $filter
     * @param bool $sitemode Show course category filter.
     * @return string
     */
    public function findings_filter_form(moodle_url $baseurl, findings_filter $filter, bool $sitemode): string {
        $all = ['' => $this->str('any')];
        $fields = html_writer::input_hidden_params($baseurl);
        if ($sitemode) {
            $categories = $all + \core_course_category::make_categories_list();
            $fields .= $this->filter_select('categoryid', get_string('category'), $categories, (string) $filter->categoryid);
        }
        $categories = $all;
        foreach (check_registry::categories() as $c) {
            $categories[$c] = $this->str('category_' . $c);
        }
        $fields .= $this->filter_select('category', $this->str('category'), $categories, $filter->category);
        $checks = $all;
        foreach (array_keys(check_registry::all()) as $checkid) {
            $checks[$checkid] = $this->str('check_' . $checkid);
        }
        $fields .= $this->filter_select('checkid', $this->str('check'), $checks, $filter->checkid);
        $severities = $all;
        foreach (check_registry::severities() as $s) {
            $severities[$s] = $this->str('severity_' . $s);
        }
        $fields .= $this->filter_select('severity', $this->str('severity'), $severities, $filter->severity);
        $states = ['active' => $this->str('state_active'), 'all' => $this->str('any')];
        foreach (finding_manager::states() as $s) {
            $states[$s] = $this->str('state_' . $s);
        }
        $fields .= $this->filter_select('state', $this->str('reviewstatus'), $states, $filter->state);
        $providers = $all;
        foreach (provider_registry::content_providers() as $name => $provider) {
            $providers[$name] = $provider->get_display_name();
        }
        $fields .= $this->filter_select('provider', $this->str('contenttype'), $providers, $filter->provider);
        $fields .= $this->filter_select('reviewtype', $this->str('findingtype'), $all + [
            'automated' => $this->str('type_automated'),
            'manual' => $this->str('type_manual'),
        ], $filter->reviewtype);
        $fields .= $this->filter_select('includeabsent', $this->str('includeabsent'), [
            0 => get_string('no'), 1 => get_string('yes')], (string) (int) $filter->includeabsent);
        $fields .= html_writer::div(html_writer::tag(
            'button',
            $this->str('applyfilters'),
            ['type' => 'submit', 'class' => 'btn btn-secondary']
        ), 'mb-2');
        $form = html_writer::tag(
            'form',
            html_writer::div($fields, 'd-flex flex-wrap align-items-end'),
            ['method' => 'get', 'action' => $baseurl->out_omit_querystring(), 'role' => 'search',
            'aria-label' => $this->str('filters')]
        );
        return html_writer::div($form, 'local-accessibilityreview-filters mb-3');
    }

    /**
     * View and edit links for a finding's source.
     *
     * @param \stdClass $record Needs courseid, cmid, provider, itemid.
     * @return string
     */
    public function source_links(\stdClass $record): string {
        $provider = provider_registry::get((string) $record->provider);
        if (!$provider) {
            return '';
        }
        $links = [];
        try {
            if ($url = $provider->get_view_url((int) $record->courseid, (int) $record->cmid, (int) $record->itemid)) {
                $links[] = html_writer::link($url, $this->str('viewsource'));
            }
            if ($url = $provider->get_edit_url((int) $record->courseid, (int) $record->cmid, (int) $record->itemid)) {
                $links[] = html_writer::link($url, $this->str('editsource'));
            }
        } catch (\Throwable $e) {
            return '';
        }
        return implode(' | ', $links);
    }

    /**
     * Findings table.
     *
     * @param \stdClass[] $findings
     * @param bool $showcourse
     * @return string
     */
    public function findings_table(array $findings, bool $showcourse): string {
        if (!$findings) {
            return html_writer::tag('p', $this->str('nofindings'));
        }
        $table = new html_table();
        $table->attributes['class'] = 'generaltable local-accessibilityreview-findings';
        $table->caption = $this->str('findings');
        $table->captionhide = true;
        $table->head = [];
        if ($showcourse) {
            $table->head[] = get_string('course');
        }
        $table->head = array_merge($table->head, [$this->str('location'), $this->str('check'), $this->str('severity'),
            $this->str('findingtype'), $this->str('excerpt'), $this->str('reviewstatus'), $this->str('lastseen'),
            $this->str('actions')]);
        foreach ($findings as $f) {
            $row = [];
            if ($showcourse) {
                $row[] = html_writer::link(new moodle_url(
                    '/local/accessibilityreview/index.php',
                    ['id' => $f->courseid, 'tab' => 'findings']
                ), format_string($f->coursename));
            }
            $location = s((string) $f->itemlabel) . html_writer::div(s($this->field_label($f->field)), 'small text-muted');
            $check = s($this->str('check_' . $f->checkid)) . html_writer::div(
                s($this->str('category_' . $f->category)),
                'small text-muted'
            );
            $type = $f->manualreview ? $this->str('type_manual') : $this->str('type_automated');
            $excerpt = html_writer::tag(
                'code',
                s(\core_text::substr((string) $f->excerpt, 0, 160)),
                ['class' => 'local-accessibilityreview-excerpt']
            );
            $state = $this->state($f->state) . (!$f->present ? html_writer::div(
                $this->str('notdetected'),
                'small text-muted'
            ) : '');
            $actions = html_writer::link(
                new moodle_url('/local/accessibilityreview/finding.php', ['id' => $f->id]),
                $this->str('details'),
                ['aria-label' => $this->str('detailsfor', $this->str('check_' . $f->checkid) .
                ' — ' . $f->itemlabel)]
            );
            $actions .= html_writer::div(html_writer::link(new moodle_url(
                '/local/accessibilityreview/guide.php',
                ['check' => $f->checkid]
            ), $this->str('howtofix'), ['aria-label' => $this->str(
                'howtofixfor',
                $this->str('check_' . $f->checkid)
            )]), 'small');
            if (fixer::might_fix($f)) {
                $actions .= html_writer::div(html_writer::link(new moodle_url(
                    '/local/accessibilityreview/fix.php',
                    ['id' => $f->id]
                ), $this->str('fixthis'), ['aria-label' => $this->str(
                    'fixthisfor',
                    $this->str('check_' . $f->checkid) . ' — ' . $f->itemlabel
                )]), 'small');
            }
            $source = $this->source_links($f);
            if ($source) {
                $actions .= html_writer::div($source, 'small');
            }
            $table->data[] = array_merge($row, [$location, $check, $this->severity($f->severity), s($type), $excerpt,
                $state, s($this->time((int) $f->timelastseen)), $actions]);
        }
        return html_writer::div(html_writer::table($table), 'table-responsive');
    }

    /**
     * Label for a source field.
     *
     * @param string $field
     * @return string
     */
    public function field_label(string $field): string {
        $key = 'field_' . $field;
        return get_string_manager()->string_exists($key, 'local_accessibilityreview') ? $this->str($key) : $field;
    }

    /**
     * Display form of a URL (Vimeo privacy hashes redacted, truncated).
     *
     * @param string $url
     * @return string
     */
    public function display_url(string $url): string {
        $text = vimeo::redact_text($url);
        return \core_text::strlen($text) > 90 ? \core_text::substr($text, 0, 87) . '…' : $text;
    }

    /**
     * Link availability table.
     *
     * @param \stdClass[] $links Rows with link fields plus itemlabel, provider, cmid, itemid, courseid.
     * @return string
     */
    public function links_table(array $links): string {
        if (!$links) {
            return html_writer::tag('p', $this->str('nolinks'));
        }
        $table = new html_table();
        $table->attributes['class'] = 'generaltable local-accessibilityreview-links';
        $table->caption = $this->str('tab_links');
        $table->captionhide = true;
        $table->head = [$this->str('url'), $this->str('linkstatus'), $this->str('reason'), $this->str('httpcode'),
            $this->str('lastchecked'), $this->str('usedin')];
        foreach ($links as $l) {
            $url = html_writer::link(
                $l->url,
                s($this->display_url($l->url)),
                ['rel' => 'noopener noreferrer', 'target' => '_blank', 'title' => $this->str('opensnewwindow')]
            );
            $reason = $l->reason !== '' && get_string_manager()->string_exists('reason_' . $l->reason, 'local_accessibilityreview')
                ? $this->str('reason_' . $l->reason) : '';
            $variant = ['available' => 'success', 'unavailable' => 'danger', 'restricted' => 'info',
                'ratelimited' => 'warning'][$l->status] ?? 'secondary';
            $used = s((string) $l->itemlabel);
            $source = $this->source_links($l);
            if ($source) {
                $used .= html_writer::div($source, 'small');
            }
            $table->data[] = [$url, $this->badge($this->str('linkstatus_' . $l->status), $variant), s($reason),
                $l->httpcode ? (int) $l->httpcode : '', s($this->time((int) $l->timechecked)), $used];
        }
        return html_writer::div(html_writer::table($table), 'table-responsive');
    }

    /** @var string|null Cached message about the PDF analysis task, built once per page. */
    private ?string $pdftaskstate = null;

    /**
     * Why a PDF is still waiting: when the "Analyse PDFs" task last ran, or that it has not run or is failing.
     *
     * @return string
     */
    private function pdf_task_state(): string {
        if ($this->pdftaskstate !== null) {
            return $this->pdftaskstate;
        }
        $task = \core\task\manager::get_scheduled_task(\local_accessibilityreview\task\analyse_pdfs::class);
        if (!$task || $task->get_disabled()) {
            $this->pdftaskstate = $this->str('pdf_pending_taskdisabled');
        } else if ($task->get_fail_delay() > 0) {
            $this->pdftaskstate = $this->str('pdf_pending_taskfailing');
        } else if (!$task->get_last_run_time()) {
            $this->pdftaskstate = $this->str('pdf_pending_never');
        } else {
            $this->pdftaskstate = $this->str('pdf_pending', (object) [
                'last' => $this->time((int) $task->get_last_run_time()),
                'next' => $this->time((int) $task->get_next_run_time()),
            ]);
        }
        return $this->pdftaskstate;
    }

    /**
     * Summarise automated evidence for an inventory item.
     *
     * @param \stdClass $r Resource record (may include link status/metadata fields).
     * @return string
     */
    public function automated_summary(\stdClass $r): string {
        $lines = [];
        if ($r->restype === 'pdf') {
            if ($r->source !== 'file') {
                $lines[] = $this->str('pdf_external');
            } else if (in_array($r->analysisstatus, ['none', 'pending'], true)) {
                if (!\local_accessibilityreview\local\pdf\pdf_analyser::enabled()) {
                    $lines[] = $this->str('pdf_analysisoff');
                } else if (!array_filter(\local_accessibilityreview\local\pdf\pdf_analyser::tools())) {
                    $lines[] = $this->str('pdf_notools');
                } else {
                    $lines[] = $this->pdf_task_state();
                }
            } else {
                $a = (array) json_decode((string) $r->analysis, true);
                $performed = array_map(fn($p) => $this->str('performed_' . $p), (array) ($a['performed'] ?? []));
                $lines[] = $this->str('checksperformed', $performed ? implode(', ', $performed) : $this->str('none'));
                if (isset($a['text'])) {
                    $lines[] = $this->str('pdf_text_' . $a['text'], (int) ($a['textpages'] ?? 0));
                }
                if (!empty($a['tagged'])) {
                    $lines[] = $this->str('pdf_tagged_' . $a['tagged']);
                }
                if (!empty($a['language'])) {
                    $lines[] = $this->str('pdf_language_' . $a['language']);
                }
                if (!empty($a['encrypted'])) {
                    $lines[] = $this->str('pdf_encrypted_' . $a['encrypted']);
                }
                foreach ((array) ($a['errors'] ?? []) as $error) {
                    $lines[] = $this->str('pdf_error_' . clean_param($error, PARAM_ALPHA));
                }
            }
        } else if ($r->restype === 'video') {
            if (!empty($r->linkstatus)) {
                $line = $this->str('availability') . ': ' . $this->str('linkstatus_' . $r->linkstatus);
                $reason = (string) ($r->linkreason ?? '');
                if (
                    $reason !== '' && $reason !== 'ok' &&
                        get_string_manager()->string_exists('reason_' . $reason, 'local_accessibilityreview')
                ) {
                    $line .= ' ' . $this->str('reason_' . $reason);
                }
                if (!empty($r->linkchecked)) {
                    $line .= ' (' . $this->str('checkedon', $this->time((int) $r->linkchecked)) . ')';
                }
                $lines[] = $line;
            }
            $meta = (array) json_decode((string) ($r->linkmetadata ?? ''), true);
            $captions = $meta['captions'] ?? 'unknown';
            $lines[] = $this->str('captions_auto_' . (in_array($captions, ['present', 'none'], true) ? $captions : 'unknown'));
        } else if ($r->restype === 'scorm') {
            $a = (array) json_decode((string) $r->analysis, true);
            if (!empty($a['version'])) {
                $lines[] = $this->str('scormversion', s($a['version']));
            }
            $lines[] = $this->str('scorm_noautomated');
        }
        return implode(html_writer::empty_tag('br'), array_map('s', $lines));
    }

    /**
     * Inventory table for one resource type.
     *
     * @param \stdClass[] $resources
     * @param string $restype
     * @return string
     */
    public function resources_table(array $resources, string $restype): string {
        if (!$resources) {
            return html_writer::tag('p', $this->str('noresources_' . $restype));
        }
        $table = new html_table();
        $table->attributes['class'] = 'generaltable local-accessibilityreview-resources';
        $table->caption = $this->str('tab_' . $restype);
        $table->captionhide = true;
        $table->head = [$this->str('name'), $this->str('location'), $this->str('source'), $this->str('size'),
            $this->str('automatedchecks'), $this->str('reviewstatus'), $this->str('actions')];
        foreach ($resources as $r) {
            $name = s($this->resource_name($r));
            if (!$r->present) {
                $name .= html_writer::div($this->str('notdetected'), 'small text-muted');
            }
            $review = s($this->str('review_' . $r->reviewstatus));
            if ($r->reviewerid) {
                $review .= html_writer::div(s($this->username((int) $r->reviewerid) . ', ' .
                    $this->time((int) $r->timereviewed)), 'small text-muted');
            }
            $table->data[] = [
                $name,
                s($r->location),
                s($this->str('source_' . $r->source)),
                $r->filesize ? display_size((int) $r->filesize) : '',
                $this->automated_summary($r),
                $review,
                html_writer::link(
                    new moodle_url('/local/accessibilityreview/resource.php', ['id' => $r->id]),
                    $this->str('reviewaction'),
                    ['aria-label' => $this->str('reviewitem', $this->resource_name($r))]
                ),
            ];
        }
        return html_writer::div(html_writer::table($table), 'table-responsive');
    }

    /**
     * Display name for a resource.
     *
     * @param \stdClass $r
     * @return string
     */
    public function resource_name(\stdClass $r): string {
        $meta = (array) json_decode((string) ($r->linkmetadata ?? ''), true);
        if (!empty($meta['title'])) {
            return $meta['title'];
        }
        return (string) $r->name;
    }

    /**
     * History table.
     *
     * @param \stdClass[] $entries
     * @return string
     */
    public function history_table(array $entries): string {
        if (!$entries) {
            return html_writer::tag('p', $this->str('nohistory'));
        }
        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->caption = $this->str('history');
        $table->captionhide = true;
        $table->head = [$this->str('date'), $this->str('user'), $this->str('action'), $this->str('change'),
            $this->str('notes')];
        foreach ($entries as $h) {
            $change = '';
            if ($h->fromstate !== null || $h->tostate !== null) {
                $change = $this->state_label((string) $h->fromstate) . ' → ' . $this->state_label((string) $h->tostate);
            }
            $table->data[] = [
                s($this->time((int) $h->timecreated)),
                s($this->username((int) $h->userid)),
                s($this->str('action_' . $h->action)),
                s($change),
                html_writer::tag('div', nl2br(s((string) $h->note)), ['class' => 'local-accessibilityreview-note']),
            ];
        }
        return html_writer::div(html_writer::table($table), 'table-responsive');
    }

    /**
     * Label for a finding state or review status.
     *
     * @param string $state
     * @return string
     */
    private function state_label(string $state): string {
        if ($state === '') {
            return '—';
        }
        if (in_array($state, finding_manager::states(), true)) {
            return $this->str('state_' . $state);
        }
        if (in_array($state, resource_manager::REVIEW_STATUSES, true)) {
            return $this->str('review_' . $state);
        }
        return $state;
    }

    /**
     * Definition list of label => value HTML.
     *
     * @param array $items label => HTML value (already escaped).
     * @return string
     */
    public function details_list(array $items): string {
        $out = '';
        foreach ($items as $label => $value) {
            $out .= html_writer::tag('dt', s($label), ['class' => 'col-sm-3']) .
                html_writer::tag('dd', $value === '' ? '—' : $value, ['class' => 'col-sm-9']);
        }
        return html_writer::tag('dl', $out, ['class' => 'row local-accessibilityreview-details']);
    }

    /**
     * WCAG reference text for a check.
     *
     * @param string $checkid
     * @return string
     */
    public function wcag(string $checkid): string {
        $refs = check_registry::get($checkid)['wcag'];
        if (!$refs) {
            return $this->str('wcag_none');
        }
        return $this->str('wcag_refs', implode(', ', $refs));
    }

    /**
     * Link status rows in display order with counts.
     *
     * @param array $counts status => count
     * @return array label => count
     */
    public function link_status_rows(array $counts): array {
        $rows = [];
        foreach (link_classifier::statuses() as $status) {
            $rows[$this->str('linkstatus_' . $status)] = (int) ($counts[$status] ?? 0);
        }
        return $rows;
    }

    /**
     * WCAG references for a check, with level, EN 301 549 clause and a link to the W3C explanation.
     *
     * @param string $checkid
     * @return string HTML
     */
    public function wcag_details(string $checkid): string {
        $refs = check_registry::get($checkid)['wcag'];
        if (!$refs) {
            return s($this->str('wcag_none'));
        }
        $items = [];
        foreach ($refs as $sc) {
            $c = check_registry::criterion($sc);
            $text = $this->str('wcag_criterion', (object) ['sc' => $c['sc'], 'level' => $c['level'],
                'en' => $c['en301549']]);
            $items[] = html_writer::tag('li', $c['url'] !== '' ? html_writer::link(
                $c['url'],
                s($text),
                ['target' => '_blank', 'rel' => 'noopener noreferrer', 'title' => $this->str('opensnewwindow')]
            ) .
                ' ' . html_writer::span($this->str('opensnewwindow_short'), 'sr-only visually-hidden') : s($text));
        }
        return html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled mb-0']);
    }

    /**
     * Progress over time: counts from recent completed scans, oldest change summary first.
     *
     * @param \stdClass[] $snapshots Newest first.
     * @return string
     */
    public function progress_table(array $snapshots): string {
        $out = html_writer::tag('h3', $this->str('progress'));
        if (!$snapshots) {
            return $out . html_writer::tag('p', $this->str('progress_none'));
        }
        $change = \local_accessibilityreview\local\progress::change($snapshots);
        if ($change !== null) {
            $parts = [];
            foreach (['errors', 'warnings', 'notices'] as $key) {
                $delta = $change[$key];
                $parts[] = $this->str(
                    'progress_change_' . ($delta < 0 ? 'down' : ($delta > 0 ? 'up' : 'same')),
                    (object) ['what' => $this->str('severity_' . rtrim($key, 's') . '_plural'), 'n' => abs($delta)]
                );
            }
            $out .= html_writer::tag('p', s($this->str('progress_since', $this->time((int) end($snapshots)->timecreated)) .
                ' ' . implode('; ', $parts) . '.'));
        }
        $table = new html_table();
        $table->attributes['class'] = 'generaltable local-accessibilityreview-progress';
        $table->caption = $this->str('progress_caption');
        $table->head = [$this->str('date'), $this->str('severity_error_plural'), $this->str('severity_warning_plural'),
            $this->str('severity_notice_plural'), $this->str('state_resolved'), $this->str('state_exception'),
            $this->str('linkstatus_unavailable'), $this->str('inventory_unreviewed')];
        foreach ($snapshots as $snap) {
            $table->data[] = [s($this->time((int) $snap->timecreated)), (int) $snap->errors, (int) $snap->warnings,
                (int) $snap->notices, (int) $snap->resolved, (int) $snap->accepted, (int) $snap->linksbroken,
                (int) $snap->unreviewed];
        }
        $out .= html_writer::div(html_writer::table($table), 'table-responsive');
        return $out . html_writer::tag('p', $this->str('progress_noscore'), ['class' => 'small text-muted']);
    }

    /**
     * Fixes applied in a course, with undo buttons.
     *
     * @param \stdClass[] $fixes
     * @param bool $canundo Whether to show undo buttons.
     * @return string
     */
    public function fixes_table(array $fixes, bool $canundo): string {
        if (!$fixes) {
            return html_writer::tag('p', $this->str('nofixes'));
        }
        $table = new html_table();
        $table->attributes['class'] = 'generaltable local-accessibilityreview-fixes';
        $table->caption = $this->str('tab_fixes');
        $table->captionhide = true;
        $table->head = [$this->str('date'), $this->str('user'), $this->str('location'), $this->str('check'),
            $this->str('change'), $this->str('status'), $this->str('actions')];
        foreach ($fixes as $fix) {
            $summary = (array) json_decode((string) $fix->summary, true);
            $status = $fix->status === 'undone' ? $this->str('fixstatus_undone', (object) [
                'user' => $this->username((int) $fix->undoneby), 'time' => $this->time((int) $fix->timeundone)])
                : $this->str('fixstatus_applied');
            $action = '';
            if ($canundo && $fix->status === 'applied') {
                $action = $this->output->single_button(
                    new moodle_url(
                        '/local/accessibilityreview/fix.php',
                        ['undo' => $fix->id, 'sesskey' => sesskey()]
                    ),
                    $this->str('undofix'),
                    'post',
                    ['aria-label' => $this->str('undofixfor', $fix->itemlabel)]
                );
            }
            $finding = $fix->findingid ? html_writer::div(html_writer::link(new moodle_url(
                '/local/accessibilityreview/finding.php',
                ['id' => $fix->findingid]
            ), $this->str('details')), 'small') : '';
            $table->data[] = [
                s($this->time((int) $fix->timecreated)),
                s($this->username((int) $fix->userid)),
                s((string) $fix->itemlabel) . $finding,
                s($this->str('check_' . $fix->checkid)),
                s(fixer::describe($fix->checkid, $summary)),
                s($status),
                $action,
            ];
        }
        return html_writer::div(html_writer::table($table), 'table-responsive');
    }

    /**
     * Guide for one check.
     *
     * @param string $checkid
     * @return string
     */
    public function check_guide(string $checkid): string {
        $def = check_registry::get($checkid);
        $out = html_writer::tag('p', html_writer::tag('strong', s($this->str('category_' . $def['category']))) . ' · ' .
            s($this->str($def['manual'] ? 'type_manual_long' : 'type_automated_long')));
        $out .= html_writer::tag('h3', $this->str('guide_what'));
        $out .= html_writer::tag('p', s($this->str('check_' . $checkid . '_desc')));
        $out .= html_writer::tag('h3', $this->str('guide_who'));
        $out .= html_writer::tag('p', s($this->str('check_' . $checkid . '_why')));
        $out .= html_writer::tag('h3', $this->str('guide_fix'));
        $out .= html_writer::tag('p', s($this->str('check_' . $checkid . '_fix')));
        $steps = array_filter(array_map('trim', explode("\n", $this->str('check_' . $checkid . '_steps'))));
        $out .= html_writer::tag('h4', $this->str('guide_steps'));
        $out .= html_writer::tag('ol', implode('', array_map(fn($step) => html_writer::tag('li', s($step)), $steps)));
        if (in_array($checkid, check_registry::fixable(), true)) {
            $out .= html_writer::tag('p', s($this->str('guide_fixable')), ['class' => 'alert alert-info']);
        }
        $out .= html_writer::tag('h3', $this->str('wcag'));
        $out .= $this->wcag_details($checkid);
        return $out;
    }

    /**
     * Index of all guides, grouped by category.
     *
     * @return string
     */
    public function guide_index(): string {
        $out = html_writer::tag('p', s($this->str('guide_intro')));
        $pages = '';
        foreach (['manual' => 'manual', 'students' => 'studentguide'] as $page => $label) {
            $pages .= html_writer::tag('li', html_writer::link(new moodle_url(
                '/local/accessibilityreview/guide.php',
                ['page' => $page]
            ), $this->str($label)));
        }
        $out .= html_writer::tag('ul', $pages);
        $bycategory = [];
        foreach (check_registry::all() as $checkid => $def) {
            $bycategory[$def['category']][] = $checkid;
        }
        foreach (check_registry::categories() as $category) {
            if (empty($bycategory[$category])) {
                continue;
            }
            $out .= html_writer::tag('h3', s($this->str('category_' . $category)));
            $items = '';
            foreach ($bycategory[$category] as $checkid) {
                $items .= html_writer::tag('li', html_writer::link(new moodle_url(
                    '/local/accessibilityreview/guide.php',
                    ['check' => $checkid]
                ), s($this->str('check_' . $checkid))));
            }
            $out .= html_writer::tag('ul', $items);
        }
        return $out;
    }

    /**
     * User manual: how to use the report, from scanning to recording reviews.
     *
     * In each section's text, every line is a paragraph, and consecutive lines starting
     * with "* " form a bulleted list.
     *
     * @return string
     */
    public function user_manual(): string {
        $sections = ['start', 'overview', 'scans', 'findings', 'workflow', 'fixing', 'files', 'links', 'progress',
            'guides', 'admin', 'limits'];
        $out = html_writer::tag('p', s($this->str('manual_intro')));
        $toc = '';
        foreach ($sections as $section) {
            $toc .= html_writer::tag('li', html_writer::link(
                '#accreview-manual-' . $section,
                s($this->str('manual_' . $section))
            ));
        }
        $out .= html_writer::tag('nav', html_writer::tag('ol', $toc), ['aria-label' => $this->str('manual')]);
        foreach ($sections as $section) {
            $out .= html_writer::tag('h3', s($this->str('manual_' . $section)), ['id' => 'accreview-manual-' . $section]);
            $items = [];
            $lines = array_filter(array_map('trim', explode("\n", $this->str('manual_' . $section . '_text'))));
            foreach ($lines as $line) {
                if (str_starts_with($line, '* ')) {
                    $items[] = html_writer::tag('li', s(substr($line, 2)));
                    continue;
                }
                if ($items) {
                    $out .= html_writer::tag('ul', implode('', $items));
                    $items = [];
                }
                $out .= html_writer::tag('p', s($line));
            }
            if ($items) {
                $out .= html_writer::tag('ul', implode('', $items));
            }
        }
        return $out;
    }

    /**
     * Guide for students on adapting how they read course content.
     *
     * @return string
     */
    public function student_guide(): string {
        $out = html_writer::tag('p', s($this->str('studentguide_intro')));
        foreach (
            ['readermode', 'zoom', 'readaloud', 'contrast', 'keyboard', 'screenreader', 'captions', 'documents',
                'help'] as $section
        ) {
            $out .= html_writer::tag('h3', s($this->str('studentguide_' . $section)));
            foreach (array_filter(array_map('trim', explode("\n", $this->str('studentguide_' . $section . '_text')))) as $p) {
                $out .= html_writer::tag('p', s($p));
            }
        }
        return $out;
    }

    /**
     * Before/after view of the part of the content a fix changes.
     *
     * @param string $old
     * @param string $new
     * @return string
     */
    public function change_preview(string $old, string $new): string {
        $max = min(strlen($old), strlen($new));
        $prefix = 0;
        while ($prefix < $max && $old[$prefix] === $new[$prefix]) {
            $prefix++;
        }
        $suffix = 0;
        while ($suffix < $max - $prefix && $old[strlen($old) - 1 - $suffix] === $new[strlen($new) - 1 - $suffix]) {
            $suffix++;
        }
        $context = 80;
        $start = self::char_boundary($old, max(0, $prefix - $context));
        $oldend = strlen($old) - $suffix;
        $newend = strlen($new) - $suffix;
        $out = '';
        foreach (['fix_before' => [$old, $oldend], 'fix_after' => [$new, $newend]] as $label => [$text, $end]) {
            $stop = self::char_boundary($text, min(strlen($text), $end + $context));
            $excerpt = ($start > 0 ? '…' : '') . substr($text, $start, $stop - $start) . ($stop < strlen($text) ? '…' : '');
            $out .= html_writer::tag('h4', $this->str($label));
            $out .= html_writer::tag('pre', s($excerpt), ['class' => 'local-accessibilityreview-excerpt-block']);
        }
        return $out;
    }

    /**
     * Move a byte offset back to the start of a UTF-8 character.
     *
     * @param string $text
     * @param int $offset
     * @return int
     */
    private static function char_boundary(string $text, int $offset): int {
        while ($offset > 0 && $offset < strlen($text) && (ord($text[$offset]) & 0xC0) === 0x80) {
            $offset--;
        }
        return $offset;
    }
}
