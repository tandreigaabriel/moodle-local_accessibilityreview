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
 * Licence tests.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_accessibilityreview;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/fixtures/fake_license.php');

use local_accessibilityreview\local\license;

/**
 * Licence state tests using Moodle's mocked HTTP transport.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibilityreview\local\license
 */
final class license_test extends \advanced_testcase {
    /**
     * Verify a perpetual licence remains usable after support ends.
     *
     * @return void
     */
    public function test_perpetual_activation_and_network_failure(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/filelib.php');
        \curl::mock_response(json_encode(['status' => 'ok', 'plan' => 'perpetual',
            'expires_at' => time() - 100, 'support_until' => time() - 100]));
        $this->assertTrue(license::activate('TEST-KEY'));
        $this->assertSame('valid', license::get_status());
        \curl::mock_response('');
        $this->assertFalse(license::refresh());
        $this->assertSame('valid', license::get_status());
    }

    /**
     * Verify the trial expires without another request.
     *
     * @return void
     */
    public function test_trial_expiry(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/filelib.php');
        \curl::mock_response(json_encode(['status' => 'ok', 'plan' => 'trial', 'expires_at' => time() + 100]));
        $this->assertTrue(license::activate('TRIAL-KEY'));
        $this->assertSame('valid', license::get_status());
        $info = license::get_info();
        $info->expires_at = time() - 1;
        set_config('license_token', json_encode($info), 'local_accessibilityreview');
        $this->assertSame('trial_expired', license::get_status());
    }

    /**
     * Verify the site host is lowercased and does not include the scheme or path.
     *
     * @return void
     */
    public function test_domain(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->wwwroot = 'https://EXAMPLE.test:8443/moodle/';
        $this->assertSame('example.test', license::domain());
    }
    /**
     * Revocation or an unknown key invalidates the stored licence.
     *
     * @return void
     */
    public function test_explicit_rejection_invalidates(): void {
        $this->resetAfterTest();
        foreach ([403, 404] as $code) {
            fake_license::$reply = [200, json_encode(['status' => 'ok', 'plan' => 'perpetual',
                'expires_at' => time() + 100, 'support_until' => time() + 100]), 0];
            $this->assertTrue(fake_license::activate('TEST-KEY'));
            fake_license::$reply = [$code, '', 0];
            $this->assertFalse(fake_license::refresh());
            $this->assertSame('unlicensed', license::get_status());
        }
    }

    /**
     * A transport failure preserves a previously activated perpetual licence.
     *
     * @return void
     */
    public function test_transport_failure_preserves_licence(): void {
        $this->resetAfterTest();
        fake_license::$reply = [200, json_encode(['status' => 'ok', 'plan' => 'perpetual',
            'expires_at' => time() + 100, 'support_until' => time() + 100]), 0];
        $this->assertTrue(fake_license::activate('TEST-KEY'));
        fake_license::$reply = [0, '', 7];
        $this->assertFalse(fake_license::refresh());
        $this->assertSame('valid', license::get_status());
    }

    /**
     * A scheduled scan does not queue courses without a licence.
     *
     * @return void
     */
    public function test_scheduled_scan_does_not_queue_without_licence(): void {
        global $DB;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_course();
        set_config('autoscan', 1, 'local_accessibilityreview');
        (new \local_accessibilityreview\task\scheduled_scan())->execute();
        $this->assertSame(0, $DB->count_records('local_accessibilityreview_scan'));
    }
}
