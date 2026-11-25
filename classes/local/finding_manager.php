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

use local_accessibilityreview\local\checks\check_registry;
use local_accessibilityreview\local\checks\issue;
use local_accessibilityreview\local\content\content_item;

/**
 * Finding lifecycle: stable identities, rescans, reopening and review states.
 *
 * Lifecycle rules:
 *  - New issue: inserted as "open".
 *  - Issue still detected, source unchanged: state kept (including reviewer decisions).
 *  - Issue still detected, source changed: "resolved" and "exception" are reopened,
 *    because the reviewer's decision was about different content.
 *  - Issue no longer detected: marked not present; "open"/"in progress" become
 *    "resolved" by the system; accepted exceptions are kept.
 *  - Previously absent issue detected again: reopened if it was resolved.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finding_manager {
    /** @var string Table name. */
    public const TABLE = 'local_accessibilityreview_finding';

    /** @var string Open. */
    public const STATE_OPEN = 'open';
    /** @var string In progress. */
    public const STATE_INPROGRESS = 'inprogress';
    /** @var string Resolved. */
    public const STATE_RESOLVED = 'resolved';
    /** @var string Accepted exception / not applicable. */
    public const STATE_EXCEPTION = 'exception';

    /**
     * All states.
     *
     * @return string[]
     */
    public static function states(): array {
        return [self::STATE_OPEN, self::STATE_INPROGRESS, self::STATE_RESOLVED, self::STATE_EXCEPTION];
    }

    /**
     * Stable identity for an issue in a source field.
     *
     * @param int $courseid
     * @param string $provider
     * @param int $itemid
     * @param string $field
     * @param string $checkid
     * @param string $signature
     * @return string
     */
    public static function identity(
        int $courseid,
        string $provider,
        int $itemid,
        string $field,
        string $checkid,
        string $signature
    ): string {
        return sha1(implode('|', [$courseid, $provider, $itemid, $field, $checkid, $signature]));
    }

    /**
     * Synchronise detected issues for one content item.
     *
     * @param int $scanid
     * @param content_item $item
     * @param issue[] $issues
     * @return int Number of issues currently present in this item.
     */
    public static function sync_item(int $scanid, content_item $item, array $issues): int {
        global $DB;
        $now = time();
        $contenthash = sha1((string) $item->html);
        $existing = [];
        $restored = [];
        $records = $DB->get_records(self::TABLE, ['courseid' => $item->courseid, 'provider' => $item->provider,
            'itemid' => $item->itemid, 'field' => $item->field]);
        foreach ($records as $record) {
            $existing[$record->identity] = $record;
            if (self::is_restored($record)) {
                // Restored from a course backup made before signatures were stored: the identity
                // could not be rebuilt, so it is matched on the check and excerpt instead.
                $restored[$record->checkid . '|' . (string) $record->excerpt] = $record;
            }
        }

        $seen = [];
        foreach ($issues as $issue) {
            $def = check_registry::get($issue->checkid);
            $identity = self::identity(
                $item->courseid,
                $item->provider,
                $item->itemid,
                $item->field,
                $issue->checkid,
                $issue->signature
            );
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            $signature = \core_text::substr($issue->signature, 0, 255);
            if (!isset($existing[$identity]) && isset($restored[$issue->checkid . '|' . $issue->excerpt])) {
                $adopted = $restored[$issue->checkid . '|' . $issue->excerpt];
                unset($restored[$issue->checkid . '|' . $issue->excerpt], $existing[$adopted->identity]);
                $DB->update_record(self::TABLE, (object) ['id' => $adopted->id, 'identity' => $identity,
                    'signature' => $signature, 'contenthash' => $contenthash]);
                $adopted->identity = $identity;
                $adopted->signature = $signature;
                $adopted->contenthash = $contenthash;
                $existing[$identity] = $adopted;
            }
            $common = [
                'cmid' => $item->cmid,
                'signature' => $signature,
                'itemlabel' => \core_text::substr($item->label, 0, 255),
                'category' => $def['category'],
                'severity' => $def['severity'],
                'manualreview' => $def['manual'] ? 1 : 0,
                'excerpt' => $issue->excerpt,
                'details' => json_encode($issue->params),
                'present' => 1,
                'lastscanid' => $scanid,
                'timelastseen' => $now,
                'timemodified' => $now,
            ];
            if (!isset($existing[$identity])) {
                $record = (object) ($common + [
                    'identity' => $identity,
                    'courseid' => $item->courseid,
                    'provider' => $item->provider,
                    'itemid' => $item->itemid,
                    'field' => $item->field,
                    'checkid' => $issue->checkid,
                    'contenthash' => $contenthash,
                    'state' => self::STATE_OPEN,
                    'timefirstseen' => $now,
                ]);
                $record->id = $DB->insert_record(self::TABLE, $record);
                history::add('finding', (int) $record->id, $item->courseid, 0, 'detected', null, self::STATE_OPEN, null);
                continue;
            }
            $old = $existing[$identity];
            $update = (object) ($common + ['id' => $old->id, 'contenthash' => $contenthash]);
            $reopen = null;
            if (self::is_restored($old)) {
                // First scan after a course restore: keep the reviewed state.
                $old->contenthash = $contenthash;
                $old->present = 1;
            }
            if (!$old->present && $old->state === self::STATE_RESOLVED) {
                $reopen = 'reappeared';
            } else if (
                $old->contenthash !== $contenthash
                    && in_array($old->state, [self::STATE_RESOLVED, self::STATE_EXCEPTION], true)
            ) {
                $reopen = 'reopened';
            }
            if ($reopen) {
                $update->state = self::STATE_OPEN;
                history::add(
                    'finding',
                    (int) $old->id,
                    $item->courseid,
                    0,
                    $reopen,
                    $old->state,
                    self::STATE_OPEN,
                    get_string('history_' . $reopen . '_note', 'local_accessibilityreview')
                );
            }
            $DB->update_record(self::TABLE, $update);
        }

        // Issues that were present before but are not detected now.
        foreach ($existing as $identity => $old) {
            if (isset($seen[$identity]) || !$old->present) {
                continue;
            }
            self::mark_absent($old, $scanid, 'notdetected');
        }
        return count($seen);
    }

    /**
     * Whether a finding was restored from a course backup and not yet seen by a scan on this site.
     *
     * @param \stdClass $record
     * @return bool
     */
    public static function is_restored(\stdClass $record): bool {
        return (int) $record->lastscanid === 0 && (int) $record->present === 0;
    }

    /**
     * Mark a finding as no longer detected.
     *
     * @param \stdClass $old
     * @param int $scanid
     * @param string $action notdetected or sourceremoved.
     * @return void
     */
    private static function mark_absent(\stdClass $old, int $scanid, string $action): void {
        global $DB;
        $update = (object) ['id' => $old->id, 'present' => 0, 'lastscanid' => $scanid, 'timemodified' => time()];
        if (in_array($old->state, [self::STATE_OPEN, self::STATE_INPROGRESS], true)) {
            $update->state = self::STATE_RESOLVED;
            history::add(
                'finding',
                (int) $old->id,
                (int) $old->courseid,
                0,
                $action,
                $old->state,
                self::STATE_RESOLVED,
                get_string('history_' . $action . '_note', 'local_accessibilityreview')
            );
        }
        $DB->update_record(self::TABLE, $update);
    }

    /**
     * After a complete scan: findings whose source item no longer exists.
     *
     * @param int $courseid
     * @param int $scanid
     * @param string[] $providers Providers that ran to completion in this scan.
     * @return void
     */
    public static function finalise(int $courseid, int $scanid, array $providers): void {
        global $DB;
        if (!$providers) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($providers, SQL_PARAMS_NAMED, 'prov');
        $params += ['courseid' => $courseid, 'scanid' => $scanid];
        $rs = $DB->get_recordset_select(
            self::TABLE,
            "courseid = :courseid AND present = 1 AND lastscanid <> :scanid AND provider $insql",
            $params
        );
        foreach ($rs as $old) {
            self::mark_absent($old, $scanid, 'sourceremoved');
        }
        $rs->close();
    }

    /**
     * Change a finding's state and/or add a note.
     *
     * @param \stdClass $finding
     * @param string $state New state.
     * @param string $note Note (plain text, may be empty).
     * @param int $userid Reviewer.
     * @return void
     */
    public static function set_state(\stdClass $finding, string $state, string $note, int $userid): void {
        global $DB;
        if (!in_array($state, self::states(), true)) {
            throw new \invalid_parameter_exception('Invalid finding state');
        }
        $note = trim($note);
        if ($state === $finding->state && $note === '') {
            return;
        }
        $DB->update_record(self::TABLE, (object) [
            'id' => $finding->id,
            'state' => $state,
            'reviewerid' => $userid,
            'timereviewed' => time(),
            'timemodified' => time(),
        ]);
        history::add(
            'finding',
            (int) $finding->id,
            (int) $finding->courseid,
            $userid,
            $state === $finding->state ? 'note' : 'statechange',
            $finding->state,
            $state,
            $note !== '' ? $note : null
        );
    }
}
