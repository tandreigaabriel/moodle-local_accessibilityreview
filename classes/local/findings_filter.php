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
use local_accessibilityreview\local\content\provider_registry;

/**
 * Validated report filters and the SQL they produce.
 *
 * Every value is checked against an allow-list; unknown values are ignored.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class findings_filter {
    /** @var int Course id (0 = all, site report only). */
    public int $courseid = 0;
    /** @var int Course category id (0 = all). */
    public int $categoryid = 0;
    /** @var string Check category. */
    public string $category = '';
    /** @var string Check id. */
    public string $checkid = '';
    /** @var string Severity. */
    public string $severity = '';
    /** @var string Finding state, or 'active' for open + in progress. */
    public string $state = 'active';
    /** @var string Content provider. */
    public string $provider = '';
    /** @var string automated or manual. */
    public string $reviewtype = '';
    /** @var bool Include findings no longer detected. */
    public bool $includeabsent = false;

    /**
     * Build from request parameters.
     *
     * @param int $courseid Fixed course id for course reports (0 for site report).
     * @return self
     */
    public static function from_request(int $courseid = 0): self {
        $f = new self();
        $f->courseid = $courseid ?: optional_param('courseid', 0, PARAM_INT);
        $f->categoryid = $courseid ? 0 : optional_param('categoryid', 0, PARAM_INT);
        $category = optional_param('category', '', PARAM_ALPHANUMEXT);
        $f->category = in_array($category, check_registry::categories(), true) ? $category : '';
        $checkid = optional_param('checkid', '', PARAM_ALPHANUMEXT);
        $f->checkid = check_registry::exists($checkid) ? $checkid : '';
        $severity = optional_param('severity', '', PARAM_ALPHA);
        $f->severity = in_array($severity, check_registry::severities(), true) ? $severity : '';
        $state = optional_param('state', 'active', PARAM_ALPHA);
        $f->state = in_array($state, array_merge(['active', 'all'], finding_manager::states()), true) ? $state : 'active';
        $provider = optional_param('provider', '', PARAM_ALPHANUMEXT);
        $f->provider = provider_registry::get($provider) ? $provider : '';
        $reviewtype = optional_param('reviewtype', '', PARAM_ALPHA);
        $f->reviewtype = in_array($reviewtype, ['automated', 'manual'], true) ? $reviewtype : '';
        $f->includeabsent = optional_param('includeabsent', 0, PARAM_BOOL);
        return $f;
    }

    /**
     * URL parameters representing this filter.
     *
     * @return array
     */
    public function to_params(): array {
        $params = [
            'category' => $this->category,
            'checkid' => $this->checkid,
            'severity' => $this->severity,
            'state' => $this->state,
            'provider' => $this->provider,
            'reviewtype' => $this->reviewtype,
            'includeabsent' => $this->includeabsent ? 1 : 0,
        ];
        if ($this->categoryid) {
            $params['categoryid'] = $this->categoryid;
        }
        return array_filter($params, fn($v) => $v !== '' && $v !== 0);
    }

    /**
     * WHERE clause (table alias f, course alias c) and parameters.
     *
     * @return array{0: string, 1: array}
     */
    public function sql(): array {
        global $DB;
        $where = ['1 = 1'];
        $params = [];
        if ($this->courseid) {
            $where[] = 'f.courseid = :fcourseid';
            $params['fcourseid'] = $this->courseid;
        }
        if ($this->categoryid) {
            $category = \core_course_category::get($this->categoryid, IGNORE_MISSING, true);
            $ids = $category ? array_merge([$category->id], $category->get_all_children_ids()) : [-1];
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'fcat');
            $where[] = "c.category $insql";
            $params += $inparams;
        }
        foreach (['category', 'checkid', 'severity', 'provider'] as $field) {
            if ($this->$field !== '') {
                $where[] = "f.$field = :f$field";
                $params['f' . $field] = $this->$field;
            }
        }
        if ($this->state === 'active') {
            $where[] = 'f.state IN (:fopen, :finprogress)';
            $params['fopen'] = finding_manager::STATE_OPEN;
            $params['finprogress'] = finding_manager::STATE_INPROGRESS;
        } else if ($this->state !== 'all') {
            $where[] = 'f.state = :fstate';
            $params['fstate'] = $this->state;
        }
        if ($this->reviewtype !== '') {
            $where[] = 'f.manualreview = :fmanual';
            $params['fmanual'] = $this->reviewtype === 'manual' ? 1 : 0;
        }
        if (!$this->includeabsent) {
            $where[] = 'f.present = 1';
        }
        // Quiz question findings contain question text: only list them for people who can see questions.
        $context = $this->courseid ? \context_course::instance($this->courseid) : \context_system::instance();
        if (!has_capability('moodle/question:viewall', $context)) {
            $where[] = 'f.provider <> :fquizquestion';
            $params['fquizquestion'] = 'quiz_question';
        }
        return [implode(' AND ', $where), $params];
    }

    /**
     * Count matching findings.
     *
     * @return int
     */
    public function count(): int {
        global $DB;
        [$where, $params] = $this->sql();
        return $DB->count_records_sql("SELECT COUNT(1) FROM {" . finding_manager::TABLE . "} f
            JOIN {course} c ON c.id = f.courseid WHERE $where", $params);
    }

    /**
     * Matching findings, most severe first.
     *
     * @param int $from
     * @param int $limit
     * @return \stdClass[]
     */
    public function get(int $from = 0, int $limit = 0): array {
        global $DB;
        [$where, $params] = $this->sql();
        $sql = "SELECT f.*, c.fullname AS coursename
                  FROM {" . finding_manager::TABLE . "} f
                  JOIN {course} c ON c.id = f.courseid
                 WHERE $where
              ORDER BY CASE f.severity WHEN 'error' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END, f.courseid, f.cmid,
                       f.itemlabel, f.id";
        return $DB->get_records_sql($sql, $params, $from, $limit);
    }

    /**
     * Recordset of matching findings (exports).
     *
     * @return \moodle_recordset
     */
    public function recordset(): \moodle_recordset {
        global $DB;
        [$where, $params] = $this->sql();
        $sql = "SELECT f.*, c.fullname AS coursename, c.shortname AS courseshortname
                  FROM {" . finding_manager::TABLE . "} f
                  JOIN {course} c ON c.id = f.courseid
                 WHERE $where
              ORDER BY f.courseid, f.id";
        return $DB->get_recordset_sql($sql, $params);
    }
}
