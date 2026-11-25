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

use local_accessibilityreview\local\checks\html_checker;
use local_accessibilityreview\local\content\content_item;
use local_accessibilityreview\local\content\provider_registry;
use local_accessibilityreview\local\links\link_manager;
use local_accessibilityreview\local\links\vimeo;
use local_accessibilityreview\task\scan_course;

/**
 * Queues and runs incremental, resumable course scans.
 *
 * A scan walks through each provider in order using keyset paging. Progress
 * is saved after every batch, so a scan interrupted by the time limit (or a
 * crash) resumes from the last saved position. A per-course lock prevents two
 * processes from scanning the same course at once, and queue() never creates a
 * second active scan for a course.
 *
 * The scan is read-only with respect to course content.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scanner {
    /** @var string Table name. */
    public const TABLE = 'local_accessibilityreview_scan';

    /** @var string Queued. */
    public const STATUS_QUEUED = 'queued';
    /** @var string Running. */
    public const STATUS_RUNNING = 'running';
    /** @var string Complete. */
    public const STATUS_COMPLETE = 'complete';
    /** @var string Failed. */
    public const STATUS_FAILED = 'failed';

    /** @var int Maximum stored error message length. */
    private const MAX_ERROR = 2000;

    /**
     * Active (queued or running) scan for a course.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function active_scan(int $courseid): ?\stdClass {
        global $DB;
        $records = $DB->get_records_select(
            self::TABLE,
            'courseid = :courseid AND status IN (:q, :r)',
            ['courseid' => $courseid, 'q' => self::STATUS_QUEUED, 'r' => self::STATUS_RUNNING],
            'id DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * Most recent scan for a course (any status).
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function latest_scan(int $courseid): ?\stdClass {
        global $DB;
        $records = $DB->get_records(self::TABLE, ['courseid' => $courseid], 'id DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Most recent completed scan for a course.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function latest_complete_scan(int $courseid): ?\stdClass {
        global $DB;
        $records = $DB->get_records(
            self::TABLE,
            ['courseid' => $courseid, 'status' => self::STATUS_COMPLETE],
            'id DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * Queue a scan unless one is already queued or running.
     *
     * @param int $courseid
     * @param int $userid 0 for scheduled and automatic scans.
     * @param int $delay Seconds before the scan may start (used to batch several edits into one scan).
     * @return array{scanid: int, created: bool}
     */
    public static function queue(int $courseid, int $userid, int $delay = 0): array {
        global $DB;
        $active = self::active_scan($courseid);
        if ($active) {
            return ['scanid' => (int) $active->id, 'created' => false];
        }
        $now = time();
        $scanid = (int) $DB->insert_record(self::TABLE, (object) [
            'courseid' => $courseid,
            'status' => self::STATUS_QUEUED,
            'requestedby' => $userid,
            'resumestate' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        self::queue_task($scanid, $delay);
        return ['scanid' => $scanid, 'created' => true];
    }

    /**
     * Queue the ad-hoc task that processes a scan.
     *
     * @param int $scanid
     * @param int $delay Seconds before the task may run.
     * @return void
     */
    public static function queue_task(int $scanid, int $delay): void {
        $task = new scan_course();
        $task->set_component('local_accessibilityreview');
        $task->set_custom_data(['scanid' => $scanid]);
        if ($delay > 0) {
            $task->set_next_run_time(time() + $delay);
        }
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Process a scan for up to $timelimit seconds.
     *
     * @param int $scanid
     * @param int $timelimit Seconds; 0 means no limit.
     * @return bool|null True when finished (complete or failed), false when more work remains,
     *                   null when the course is locked by another process.
     */
    public static function process(int $scanid, int $timelimit = 0): ?bool {
        global $DB;
        $scan = $DB->get_record(self::TABLE, ['id' => $scanid]);
        if (!$scan || !in_array($scan->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true)) {
            return true;
        }
        $factory = \core\lock\lock_config::get_lock_factory('local_accessibilityreview_scan');
        $lock = $factory->get_lock('course_' . $scan->courseid, 0, 2 * HOURSECS);
        if (!$lock) {
            return null;
        }
        try {
            return self::process_locked($scan, $timelimit);
        } catch (\Throwable $e) {
            self::fail($scan, get_string(
                'error_scanexception',
                'local_accessibilityreview',
                self::safe_message($e->getMessage())
            ));
            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Process while holding the course lock.
     *
     * @param \stdClass $scan
     * @param int $timelimit
     * @return bool
     */
    private static function process_locked(\stdClass $scan, int $timelimit): bool {
        global $DB;
        $course = $DB->get_record('course', ['id' => $scan->courseid]);
        if (!$course) {
            self::fail($scan, get_string('error_coursemissing', 'local_accessibilityreview'));
            return true;
        }
        $now = time();
        if ($scan->status === self::STATUS_QUEUED) {
            $scan->status = self::STATUS_RUNNING;
            $scan->timestarted = $now;
        }
        $state = json_decode((string) $scan->resumestate, true);
        if (!is_array($state)) {
            $state = ['step' => 0, 'after' => 0, 'completed' => []];
        }
        $steps = provider_registry::steps();
        $content = provider_registry::content_providers();
        $inventory = provider_registry::inventory_providers();
        $checker = html_checker::from_config();
        $batch = max(5, min(500, (int) (get_config('local_accessibilityreview', 'batchsize') ?: 50)));
        $deadline = $timelimit > 0 ? $now + $timelimit : 0;

        while ($state['step'] < count($steps)) {
            $name = $steps[$state['step']];
            if (isset($content[$name])) {
                $items = $content[$name]->get_items($course, (int) $state['after'], $batch);
                foreach ($items as $item) {
                    self::process_item($scan, $item, $checker);
                    $state['after'] = $item->sortkey;
                    $scan->itemsdone++;
                }
                $done = count($items) < $batch;
            } else {
                $result = $inventory[$name]->process($course, (int) $state['after'], $batch, (int) $scan->id);
                $state['after'] = $result['last'];
                $scan->itemsdone += $result['count'];
                $done = $result['done'];
            }
            if ($done) {
                $state['completed'][] = $name;
                $state['step']++;
                $state['after'] = 0;
            }
            $scan->resumestate = json_encode($state);
            $scan->timemodified = time();
            $DB->update_record(self::TABLE, $scan);
            if ($deadline && time() >= $deadline && $state['step'] < count($steps)) {
                return false;
            }
        }

        // All steps completed: reconcile items that disappeared since the last scan.
        $completedcontent = array_values(array_intersect($state['completed'], array_keys($content)));
        finding_manager::finalise((int) $course->id, (int) $scan->id, $completedcontent);
        link_manager::finalise((int) $course->id, (int) $scan->id, $completedcontent);
        resource_manager::finalise((int) $course->id, (int) $scan->id);

        $scan->findingscount = $DB->count_records_select(
            finding_manager::TABLE,
            'courseid = :courseid AND present = 1 AND state IN (:open, :inprogress)',
            ['courseid' => $course->id, 'open' => finding_manager::STATE_OPEN,
            'inprogress' => finding_manager::STATE_INPROGRESS]
        );
        $scan->status = self::STATUS_COMPLETE;
        $scan->timefinished = time();
        $scan->timemodified = time();
        $DB->update_record(self::TABLE, $scan);
        progress::snapshot((int) $course->id, (int) $scan->id);
        return true;
    }

    /**
     * Check one content item.
     *
     * @param \stdClass $scan Scan record (counters updated in place).
     * @param content_item $item
     * @param html_checker $checker
     * @return void
     */
    private static function process_item(\stdClass $scan, content_item $item, html_checker $checker): void {
        try {
            if ($item->html !== null && strlen($item->html) > html_checker::MAX_HTML_BYTES) {
                $scan->itemsfailed++;
                self::add_error($scan, get_string('error_toolarge', 'local_accessibilityreview', $item->label));
                return;
            }
            $issues = $item->html !== null ? $checker->check($item->html) : [];
            finding_manager::sync_item((int) $scan->id, $item, $issues);

            $urls = [];
            foreach ($item->urls as $url) {
                $normalised = link_manager::normalise($url);
                if ($normalised !== null) {
                    $urls[] = $normalised;
                }
            }
            $urls = array_values(array_unique(array_merge($urls, link_manager::extract_urls($item->html))));
            link_manager::record_uses((int) $scan->id, $item, $urls);
        } catch (\Throwable $e) {
            $scan->itemsfailed++;
            self::add_error($scan, get_string('error_item', 'local_accessibilityreview', (object) [
                'item' => $item->label,
                'message' => self::safe_message($e->getMessage()),
            ]));
        }
    }

    /**
     * Append an error message (bounded).
     *
     * @param \stdClass $scan
     * @param string $message
     * @return void
     */
    private static function add_error(\stdClass $scan, string $message): void {
        $current = (string) $scan->errormessage;
        if (\core_text::strlen($current) >= self::MAX_ERROR) {
            return;
        }
        $scan->errormessage = \core_text::substr(trim($current . "\n" . $message), 0, self::MAX_ERROR);
    }

    /**
     * Strip file system paths and private video hashes from exception text.
     *
     * @param string $message
     * @return string
     */
    public static function safe_message(string $message): string {
        global $CFG;
        $message = str_replace([$CFG->dirroot, $CFG->dataroot], ['[dirroot]', '[dataroot]'], $message);
        $message = vimeo::redact_text($message);
        return \core_text::substr($message, 0, 500);
    }

    /**
     * Mark a scan as failed.
     *
     * @param \stdClass $scan
     * @param string $message
     * @return void
     */
    public static function fail(\stdClass $scan, string $message): void {
        global $DB;
        $DB->update_record(self::TABLE, (object) [
            'id' => $scan->id,
            'status' => self::STATUS_FAILED,
            'errormessage' => \core_text::substr(trim((string) $scan->errormessage . "\n" . $message), 0, self::MAX_ERROR),
            'timefinished' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Run a scan to completion synchronously (CLI and unit tests).
     *
     * @param int $scanid
     * @return void
     */
    public static function run_now(int $scanid): void {
        while (self::process($scanid, 0) === false) {
            continue;
        }
    }
}
