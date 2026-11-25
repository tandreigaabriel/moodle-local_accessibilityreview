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

/**
 * CLI: scan one course synchronously (useful on staging), optionally checking its links.
 *
 * Usage: php local/accessibilityreview/cli/scan.php --courseid=123 [--links]
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_accessibilityreview\local\links\link_manager;
use local_accessibilityreview\local\scanner;

[$options, $unrecognised] = cli_get_params(
    ['courseid' => 0, 'links' => false, 'help' => false],
    ['c' => 'courseid', 'l' => 'links', 'h' => 'help']
);

if ($options['help'] || empty($options['courseid'])) {
    cli_writeln("Scan one course for accessibility issues.\n\nOptions:\n  -c, --courseid=ID  Course id (required)\n" .
        "  -l, --links        Also check links that are due (requires link checking enabled)\n  -h, --help         Help");
    exit(0);
}

if (\local_accessibilityreview\local\license::get_status() !== 'valid') {
    cli_error(get_string('license_unlicensed', 'local_accessibilityreview'));
}

$courseid = (int) $options['courseid'];
$DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);
$result = scanner::queue($courseid, 0);
cli_writeln(($result['created'] ? 'Created' : 'Resuming existing') . ' scan ' . $result['scanid'] . '.');
scanner::run_now($result['scanid']);
$scan = $DB->get_record(scanner::TABLE, ['id' => $result['scanid']]);
cli_writeln("Status: {$scan->status}; items: {$scan->itemsdone}; failed items: {$scan->itemsfailed}; " .
    "open findings: {$scan->findingscount}");
if (trim((string) $scan->errormessage) !== '') {
    cli_writeln("Messages:\n" . $scan->errormessage);
}

if ($options['links']) {
    if (empty(get_config('local_accessibilityreview', 'linkcheck'))) {
        cli_writeln('Link checking is disabled in the plugin settings.');
    } else {
        foreach (link_manager::check_due(1000, 600) as $status => $count) {
            cli_writeln("Links $status: $count");
        }
    }
}
