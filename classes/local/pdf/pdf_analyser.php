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

namespace local_accessibilityreview\local\pdf;

use local_accessibilityreview\local\resource_manager;

/**
 * Optional, local-only PDF analysis with Poppler's pdfinfo and pdftotext.
 *
 * Files are read through the Moodle File API (so ObjectFS/S3 storage works),
 * copied to a private temp file, analysed by local executables with a time
 * limit, then the temp file is deleted. Documents never leave the server.
 *
 * Results are signals only: text, tags and metadata do not establish that a
 * PDF is accessible. Absence of a /Lang entry in the raw bytes is reported as
 * "not determined" because it may be inside a compressed object stream.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_analyser {
    /**
     * Whether analysis is enabled by the administrator.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return !empty(get_config('local_accessibilityreview', 'pdfanalysis'));
    }

    /**
     * Whether proc_open can be used.
     *
     * @return bool
     */
    private static function can_execute(): bool {
        if (!function_exists('proc_open') || !function_exists('proc_get_status')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('proc_open', $disabled, true);
    }

    /** @var string[] Standard install locations checked when no path is configured. */
    public const STANDARD_DIRS = ['/usr/bin', '/usr/local/bin', '/bin', '/opt/homebrew/bin'];

    /**
     * Executable paths to use, or empty strings when unusable.
     *
     * A configured path wins. When none is configured (for example because
     * $CFG->preventexecpath locks the admin field), only the fixed standard
     * system locations above are tried; no search of PATH or user input is used.
     *
     * @return array{pdfinfo: string, pdftotext: string}
     */
    public static function tools(): array {
        $out = ['pdfinfo' => '', 'pdftotext' => ''];
        if (!self::can_execute()) {
            return $out;
        }
        foreach (array_keys($out) as $tool) {
            $configured = trim((string) get_config('local_accessibilityreview', 'path' . $tool));
            $candidates = $configured !== '' ? [$configured] :
                array_map(fn($dir) => $dir . '/' . $tool, self::STANDARD_DIRS);
            foreach ($candidates as $path) {
                if (@is_file($path) && @is_executable($path)) {
                    $out[$tool] = $path;
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Analyse pending PDFs.
     *
     * @param int $max
     * @param int $timelimit Seconds.
     * @return int Number processed.
     */
    public static function run(int $max, int $timelimit): int {
        global $DB;
        $start = time();
        $records = $DB->get_records_select(
            resource_manager::TABLE,
            "restype = 'pdf' AND source = 'file' AND present = 1 AND analysisstatus = 'pending'",
            null,
            'id ASC',
            '*',
            0,
            $max
        );
        $count = 0;
        foreach ($records as $record) {
            if (time() - $start >= $timelimit) {
                break;
            }
            try {
                [$status, $result] = self::analyse($record);
            } catch (\Throwable $e) {
                // One bad file must not stop the others, or block the task on every run.
                mtrace('PDF analysis failed for resource ' . $record->id . ': ' . $e->getMessage());
                [$status, $result] = ['failed', ['performed' => [], 'errors' => ['internal']]];
            }
            $DB->update_record(resource_manager::TABLE, (object) [
                'id' => $record->id,
                'analysisstatus' => $status,
                'analysis' => json_encode($result),
                'timeanalysed' => time(),
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * Analyse one inventory record.
     *
     * @param \stdClass $record
     * @return array{0: string, 1: array} [status, result]
     */
    public static function analyse(\stdClass $record): array {
        $config = get_config('local_accessibilityreview');
        $maxbytes = max(1, (int) ($config->pdfmaxsize ?? 50)) * 1024 * 1024;
        $timeout = max(2, min(300, (int) ($config->pdftimeout ?? 30)));
        $maxpages = max(1, min(50, (int) ($config->pdfmaxpages ?? 5)));
        $result = ['performed' => [], 'errors' => []];

        $fs = get_file_storage();
        $file = $record->fileid ? $fs->get_file_by_id($record->fileid) : false;
        if (!$file || $file->is_directory()) {
            $result['errors'][] = 'filemissing';
            return ['failed', $result];
        }
        if ($file->get_filesize() > $maxbytes) {
            $result['errors'][] = 'toolarge';
            return ['skipped', $result];
        }
        $tools = self::tools();
        $temp = null;
        try {
            $temp = $file->copy_content_to_temp('local_accessibilityreview', 'pdf_');
            if (!$temp) {
                $result['errors'][] = 'tempcopy';
                return ['failed', $result];
            }

            if ($tools['pdfinfo']) {
                $result['performed'][] = 'pdfinfo';
                $run = self::run_command([$tools['pdfinfo'], $temp], $timeout);
                if ($run['timedout']) {
                    $result['errors'][] = 'timeout';
                } else if ($run['exitcode'] !== 0) {
                    $result['errors'][] = self::classify_error($run['stderr']);
                } else {
                    $info = self::parse_pdfinfo($run['stdout']);
                    $result['pages'] = $info['pages'];
                    $result['tagged'] = $info['tagged'];
                    $result['encrypted'] = $info['encrypted'];
                }
            }

            if ($tools['pdftotext'] && !in_array('encrypted', $result['errors'], true)) {
                $result['performed'][] = 'pdftotext';
                $run = self::run_command([$tools['pdftotext'], '-q', '-f', '1', '-l', (string) $maxpages, '-enc', 'UTF-8',
                    $temp, '-'], $timeout);
                if ($run['timedout']) {
                    $result['errors'][] = 'timeout';
                } else if ($run['exitcode'] !== 0) {
                    $result['errors'][] = self::classify_error($run['stderr']);
                } else {
                    $chars = preg_match_all('/[^\s\x{FEFF}]/u', $run['stdout']);
                    $result['textchars'] = (int) $chars;
                    $result['textpages'] = $maxpages;
                    $result['text'] = $chars >= 20 ? 'present' : ($chars > 0 ? 'minimal' : 'none');
                }
            }

            $result['performed'][] = 'langscan';
            $result['language'] = self::scan_for_lang($temp) ? 'present' : 'notdetermined';
        } finally {
            if ($temp && is_file($temp)) {
                @unlink($temp);
            }
        }
        $status = $result['errors'] ? 'failed' : 'done';
        if ($status === 'failed' && count($result['performed']) > 1) {
            $status = 'done'; // Partial results are still useful; errors are listed.
        }
        return [$status, $result];
    }

    /**
     * Parse pdfinfo output.
     *
     * @param string $output
     * @return array{pages: int|null, tagged: string|null, encrypted: string|null}
     */
    public static function parse_pdfinfo(string $output): array {
        $out = ['pages' => null, 'tagged' => null, 'encrypted' => null];
        foreach (preg_split('/\R/', $output) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);
            if ($key === 'pages') {
                $out['pages'] = (int) $value;
            } else if ($key === 'tagged') {
                $out['tagged'] = str_starts_with(strtolower($value), 'yes') ? 'yes' : 'no';
            } else if ($key === 'encrypted') {
                $out['encrypted'] = str_starts_with(strtolower($value), 'yes') ? 'yes' : 'no';
            }
        }
        return $out;
    }

    /**
     * Map tool stderr to an error code (never stores raw paths).
     *
     * @param string $stderr
     * @return string
     */
    private static function classify_error(string $stderr): string {
        $lower = strtolower($stderr);
        if (str_contains($lower, 'password') || str_contains($lower, 'encrypt')) {
            return 'encrypted';
        }
        return 'parseerror';
    }

    /**
     * Look for a document language entry in the first 2MB and last 1MB.
     *
     * @param string $path
     * @return bool
     */
    private static function scan_for_lang(string $path): bool {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return false;
        }
        $found = false;
        $head = (string) fread($handle, 2 * 1024 * 1024);
        if (preg_match('~/Lang\s*\(\s*[A-Za-z]{2,3}~', $head) || preg_match('~/Lang\s*<\s*[0-9A-Fa-f]{4,}~', $head)) {
            $found = true;
        }
        if (!$found) {
            $size = filesize($path);
            if ($size > 2 * 1024 * 1024) {
                fseek($handle, max(0, $size - 1024 * 1024));
                $tail = (string) fread($handle, 1024 * 1024);
                $found = (bool) preg_match('~/Lang\s*\(\s*[A-Za-z]{2,3}~', $tail);
            }
        }
        fclose($handle);
        return $found;
    }

    /**
     * Run an executable (no shell) with a timeout, capturing output via temp files.
     *
     * @param string[] $command Executable and arguments.
     * @param int $timeout Seconds.
     * @param int $maxoutput Maximum bytes read from stdout.
     * @return array{exitcode: int, stdout: string, stderr: string, timedout: bool}
     */
    public static function run_command(array $command, int $timeout, int $maxoutput = 524288): array {
        $dir = make_request_directory();
        $outfile = $dir . '/stdout';
        $errfile = $dir . '/stderr';
        $descriptors = [
            0 => ['file', (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null', 'r'],
            1 => ['file', $outfile, 'w'],
            2 => ['file', $errfile, 'w'],
        ];
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            return ['exitcode' => -1, 'stdout' => '', 'stderr' => 'proc_open failed', 'timedout' => false];
        }
        $start = microtime(true);
        $exitcode = -1;
        $timedout = false;
        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitcode = (int) $status['exitcode'];
                break;
            }
            if (microtime(true) - $start > $timeout) {
                proc_terminate($process, 9);
                $timedout = true;
                break;
            }
            usleep(100000);
        }
        proc_close($process);
        $stdout = is_file($outfile) ? (string) file_get_contents($outfile, false, null, 0, $maxoutput) : '';
        $stderr = is_file($errfile) ? (string) file_get_contents($errfile, false, null, 0, 4096) : '';
        return ['exitcode' => $exitcode, 'stdout' => $stdout, 'stderr' => $stderr, 'timedout' => $timedout];
    }
}
