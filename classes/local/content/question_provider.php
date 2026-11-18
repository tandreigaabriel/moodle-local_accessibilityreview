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

namespace local_accessibilityreview\local\content;

/**
 * Question text of questions placed directly in this course's quizzes.
 *
 * Uses the Moodle 4.x question references: for each quiz slot, the version
 * the slot points to (or the latest non-draft version when "always latest").
 * Questions drawn at random from a category are not included, because the
 * questions a student sees are only known at attempt time.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_provider extends module_provider_base {
    /**
     * Provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'quiz_question';
    }

    /**
     * Display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('provider_quiz_question', 'local_accessibilityreview');
    }

    /**
     * Items, keyed by question id. A question used in several quizzes is reported once.
     *
     * @param \stdClass $course
     * @param int $after
     * @param int $limit
     * @return content_item[]
     */
    public function get_items(\stdClass $course, int $after, int $limit): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('question_references')) {
            return [];
        }
        $sql = "SELECT qv.questionid, MIN(cm.id) AS cmid
                  FROM {quiz} quiz
                  JOIN {modules} m ON m.name = 'quiz'
                  JOIN {course_modules} cm ON cm.instance = quiz.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :ctxlevel
                  JOIN {question_references} qr ON qr.usingcontextid = ctx.id AND qr.component = 'mod_quiz'
                                               AND qr.questionarea = 'slot'
                  JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
                 WHERE quiz.course = :course AND cm.course = :course2 AND cm.deletioninprogress = 0
                       AND qv.questionid > :after
                       AND qv.version = COALESCE(qr.version, (
                           SELECT MAX(v2.version)
                             FROM {question_versions} v2
                            WHERE v2.questionbankentryid = qr.questionbankentryid AND v2.status <> 'draft'))
              GROUP BY qv.questionid
              ORDER BY qv.questionid ASC";
        $refs = $DB->get_records_sql($sql, ['ctxlevel' => CONTEXT_MODULE, 'course' => $course->id,
            'course2' => $course->id, 'after' => $after], 0, $limit);
        if (!$refs) {
            return [];
        }
        $questions = $DB->get_records_list(
            'question',
            'id',
            array_keys($refs),
            '',
            'id, name, qtype, questiontext, questiontextformat'
        );
        $quiznames = $DB->get_records_sql_menu(
            "SELECT cm.id, quiz.name
                                                  FROM {course_modules} cm
                                                  JOIN {quiz} quiz ON quiz.id = cm.instance
                                                 WHERE cm.course = :course AND cm.module = (
                                                       SELECT id FROM {modules} WHERE name = 'quiz')",
            ['course' => $course->id]
        );
        $items = [];
        foreach ($refs as $questionid => $ref) {
            $question = $questions[$questionid] ?? null;
            $html = null;
            $label = '';
            if ($question && $question->qtype !== 'random') {
                $a = (object) [
                    'quiz' => $this->activity_label((string) ($quiznames[$ref->cmid] ?? ''), 'quiz'),
                    'question' => format_string($question->name, true, ['escape' => false]),
                ];
                $label = get_string('provider_question_label', 'local_accessibilityreview', $a);
                $html = content_item::to_html($question->questiontext, (int) $question->questiontextformat);
            }
            $items[] = new content_item(
                $this->get_name(),
                (int) $course->id,
                (int) $ref->cmid,
                (int) $questionid,
                'questiontext',
                $label,
                $html,
                (int) $questionid
            );
        }
        return $items;
    }

    /**
     * Preview URL for a question.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_view_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        $context = \context_module::instance($cmid, IGNORE_MISSING);
        if (!$context || !has_capability('moodle/question:viewall', $context)) {
            return null;
        }
        return new \moodle_url('/question/bank/previewquestion/preview.php', ['id' => $itemid, 'cmid' => $cmid]);
    }

    /**
     * Edit URL for a question.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $itemid
     * @return \moodle_url|null
     */
    public function get_edit_url(int $courseid, int $cmid, int $itemid): ?\moodle_url {
        $context = \context_module::instance($cmid, IGNORE_MISSING);
        if (!$context || !has_capability('moodle/question:editall', $context)) {
            return null;
        }
        return new \moodle_url('/question/bank/editquestion/question.php', ['id' => $itemid, 'cmid' => $cmid]);
    }
}
