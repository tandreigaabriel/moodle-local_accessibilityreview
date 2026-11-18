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

namespace local_accessibilityreview\task;

use local_accessibilityreview\local\pdf\pdf_analyser;

/**
 * Optional local PDF analysis using Poppler utilities. Disabled by default.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analyse_pdfs extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_analysepdfs', 'local_accessibilityreview');
    }

    /**
     * Execute.
     *
     * @return void
     */
    public function execute() {
        if (!pdf_analyser::enabled()) {
            mtrace('PDF analysis is disabled.');
            return;
        }
        $tools = pdf_analyser::tools();
        if (!$tools['pdfinfo'] && !$tools['pdftotext']) {
            mtrace('PDF analysis is enabled but no usable pdfinfo/pdftotext executable is configured.');
            return;
        }
        $config = get_config('local_accessibilityreview');
        $max = max(1, min(500, (int) ($config->pdfperrun ?? 20)));
        $timelimit = max(10, (int) ($config->pdftasktimelimit ?? 300));
        $done = pdf_analyser::run($max, $timelimit);
        mtrace('Analysed ' . $done . ' PDF file(s).');
    }
}
