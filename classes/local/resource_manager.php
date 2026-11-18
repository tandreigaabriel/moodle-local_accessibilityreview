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

/**
 * PDF / video / SCORM inventory records and their manual review checklists.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_manager {
    /** @var string Table name. */
    public const TABLE = 'local_accessibilityreview_res';

    /** @var string[] Review statuses. */
    public const REVIEW_STATUSES = ['notreviewed', 'inprogress', 'reviewed', 'issuesfound', 'notapplicable'];

    /** @var string[] Answers for each checklist question. */
    public const ANSWERS = ['notchecked', 'yes', 'no', 'na'];

    /** @var array<string, string[]> Manual checklist questions per resource type. */
    private const CHECKLISTS = [
        'pdf' => ['pdf_realtext', 'pdf_tagged', 'pdf_readingorder', 'pdf_headings', 'pdf_images', 'pdf_tables',
            'pdf_language', 'pdf_usable'],
        'video' => ['video_captions', 'video_captionsaccurate', 'video_transcript', 'video_audiodesc'],
        'scorm' => ['scorm_keyboard', 'scorm_focus', 'scorm_screenreader', 'scorm_textalt', 'scorm_captions',
            'scorm_contrast'],
    ];

    /**
     * Checklist question keys for a resource type.
     *
     * @param string $restype
     * @return string[]
     */
    public static function checklist(string $restype): array {
        return self::CHECKLISTS[$restype] ?? [];
    }

    /**
     * Resource types.
     *
     * @return string[]
     */
    public static function types(): array {
        return array_keys(self::CHECKLISTS);
    }

    /**
     * Insert or refresh an inventory record seen during a scan.
     *
     * If the underlying file content changed since it was reviewed, the review
     * is reopened and PDF analysis is queued again.
     *
     * @param int $scanid
     * @param array $data Keys: identity, courseid, cmid, restype, source, name, location, provider, itemid,
     *                    fileid, filesize, filehash, url, linkid, analysis (array).
     * @return int Record id.
     */
    public static function upsert(int $scanid, array $data): int {
        global $DB;
        $now = time();
        $existing = $DB->get_record(self::TABLE, ['identity' => $data['identity']]);
        $fields = [
            'courseid' => $data['courseid'],
            'cmid' => $data['cmid'] ?? 0,
            'restype' => $data['restype'],
            'source' => $data['source'],
            'name' => \core_text::substr((string) ($data['name'] ?? ''), 0, 255),
            'location' => \core_text::substr((string) ($data['location'] ?? ''), 0, 255),
            'provider' => \core_text::substr((string) ($data['provider'] ?? ''), 0, 50),
            'itemid' => $data['itemid'] ?? 0,
            'fileid' => $data['fileid'] ?? 0,
            'filesize' => $data['filesize'] ?? 0,
            'filehash' => $data['filehash'] ?? '',
            'url' => $data['url'] ?? null,
            'linkid' => $data['linkid'] ?? 0,
            'present' => 1,
            'lastscanid' => $scanid,
            'timelastseen' => $now,
        ];
        $needsanalysis = $data['restype'] === 'pdf' && $data['source'] === 'file';
        if (!$existing) {
            $record = (object) $fields;
            $record->identity = $data['identity'];
            $record->analysisstatus = $needsanalysis ? 'pending' : 'none';
            $record->analysis = isset($data['analysis']) ? json_encode($data['analysis']) : null;
            $record->reviewstatus = 'notreviewed';
            $record->timefirstseen = $now;
            try {
                return (int) $DB->insert_record(self::TABLE, $record);
            } catch (\dml_write_exception $e) {
                // Inserted concurrently: fall through to update.
                $existing = $DB->get_record(self::TABLE, ['identity' => $data['identity']], '*', MUST_EXIST);
            }
        }
        $update = (object) $fields;
        $update->id = $existing->id;
        if (isset($data['analysis'])) {
            $update->analysis = json_encode($data['analysis']);
        }
        $changed = $existing->filehash !== '' && $fields['filehash'] !== '' && $existing->filehash !== $fields['filehash'];
        if ($changed) {
            if ($needsanalysis) {
                $update->analysisstatus = 'pending';
                $update->analysis = null;
                $update->timeanalysed = 0;
            }
            if (in_array($existing->reviewstatus, ['reviewed', 'issuesfound', 'notapplicable'], true)) {
                $update->reviewstatus = 'notreviewed';
                history::add(
                    'resource',
                    (int) $existing->id,
                    (int) $existing->courseid,
                    0,
                    'reopened',
                    $existing->reviewstatus,
                    'notreviewed',
                    get_string('history_filechanged', 'local_accessibilityreview')
                );
            }
        } else if (!$existing->present && in_array($existing->reviewstatus, ['reviewed', 'notapplicable'], true)) {
            history::add(
                'resource',
                (int) $existing->id,
                (int) $existing->courseid,
                0,
                'reappeared',
                $existing->reviewstatus,
                $existing->reviewstatus,
                null
            );
        }
        $DB->update_record(self::TABLE, $update);
        return (int) $existing->id;
    }

    /**
     * Mark records not seen in the given scan as no longer present.
     *
     * @param int $courseid
     * @param int $scanid
     * @param string[]|null $sources Limit to these sources (null = all).
     * @return void
     */
    public static function finalise(int $courseid, int $scanid, ?array $sources = null): void {
        global $DB;
        $select = 'courseid = :courseid AND lastscanid <> :scanid AND present = 1';
        $params = ['courseid' => $courseid, 'scanid' => $scanid];
        if ($sources) {
            [$insql, $inparams] = $DB->get_in_or_equal($sources, SQL_PARAMS_NAMED, 'src');
            $select .= ' AND source ' . $insql;
            $params += $inparams;
        }
        $DB->set_field_select(self::TABLE, 'present', 0, $select, $params);
    }

    /**
     * Save a manual review.
     *
     * @param \stdClass $resource Existing record.
     * @param array $answers Question key => answer.
     * @param string $status Review status.
     * @param string $notes Notes (plain text).
     * @param int $userid Reviewer.
     * @return void
     */
    public static function save_review(
        \stdClass $resource,
        array $answers,
        string $status,
        string $notes,
        int $userid
    ): void {
        global $DB;
        if (!in_array($status, self::REVIEW_STATUSES, true)) {
            throw new \invalid_parameter_exception('Invalid review status');
        }
        $clean = [];
        foreach (self::checklist($resource->restype) as $key) {
            $value = $answers[$key] ?? 'notchecked';
            $clean[$key] = in_array($value, self::ANSWERS, true) ? $value : 'notchecked';
        }
        $old = (array) json_decode((string) $resource->checklist, true);
        $changes = [];
        foreach ($clean as $key => $value) {
            if (($old[$key] ?? 'notchecked') !== $value) {
                $changes[] = get_string('checklist_' . $key, 'local_accessibilityreview') . ': ' .
                    get_string('answer_' . $value, 'local_accessibilityreview');
            }
        }
        $update = (object) [
            'id' => $resource->id,
            'checklist' => json_encode($clean),
            'reviewstatus' => $status,
            'reviewnotes' => $notes,
            'reviewerid' => $userid,
            'timereviewed' => time(),
        ];
        $DB->update_record(self::TABLE, $update);
        $note = trim(implode("\n", $changes) . ($notes !== '' && $notes !== (string) $resource->reviewnotes ?
            "\n" . $notes : ''));
        history::add(
            'resource',
            (int) $resource->id,
            (int) $resource->courseid,
            $userid,
            'review',
            $resource->reviewstatus,
            $status,
            $note !== '' ? $note : null
        );
    }
}
