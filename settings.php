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
 * Admin settings and site report registration.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Site report: visible to anyone with the view capability at system level.
$ADMIN->add('reports', new admin_externalpage(
    'local_accessibilityreview_site',
    get_string('sitereport', 'local_accessibilityreview'),
    new moodle_url('/local/accessibilityreview/site.php'),
    'local/accessibilityreview:view'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_accessibilityreview', get_string('pluginname', 'local_accessibilityreview'));
    $ADMIN->add('localplugins', $settings);
    $p = 'local_accessibilityreview';

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            "$p/licenseheading",
            get_string('licenseheading', $p),
            html_writer::link(new moodle_url('/local/accessibilityreview/license.php'), get_string('licensesettings', $p))
        ));
        // Scanning.
        $settings->add(new admin_setting_heading(
            "$p/scanheading",
            get_string('settings_scan', $p),
            get_string('settings_scan_desc', $p)
        ));
        $settings->add(new admin_setting_configtext(
            "$p/batchsize",
            get_string('batchsize', $p),
            get_string('batchsize_desc', $p),
            50,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/maxruntime",
            get_string('maxruntime', $p),
            get_string('maxruntime_desc', $p),
            120,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configcheckbox(
            "$p/autoscan",
            get_string('autoscan', $p),
            get_string('autoscan_desc', $p),
            0
        ));
        $settings->add(new admin_setting_configtext(
            "$p/autoscandays",
            get_string('autoscandays', $p),
            get_string('autoscandays_desc', $p),
            7,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/autoscancourses",
            get_string('autoscancourses', $p),
            get_string('autoscancourses_desc', $p),
            20,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/bulkqueuelimit",
            get_string('bulkqueuelimit', $p),
            get_string('bulkqueuelimit_desc', $p),
            500,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configcheckbox(
            "$p/rescanonedit",
            get_string('rescanonedit', $p),
            get_string('rescanonedit_desc', $p),
            1
        ));
        $settings->add(new admin_setting_configtext(
            "$p/rescandelay",
            get_string('rescandelay', $p),
            get_string('rescandelay_desc', $p),
            10,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/retentiondays",
            get_string('retentiondays', $p),
            get_string('retentiondays_desc', $p),
            365,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtextarea(
            "$p/unclearlinktext",
            get_string('unclearlinktext', $p),
            get_string('unclearlinktext_desc', $p),
            implode("\n", \local_accessibilityreview\local\checks\html_checker::DEFAULT_UNCLEAR),
            PARAM_TEXT
        ));
        $settings->add(new admin_setting_configtextarea(
            "$p/genericalttext",
            get_string('genericalttext', $p),
            get_string('genericalttext_desc', $p),
            implode("\n", \local_accessibilityreview\local\checks\html_checker::DEFAULT_GENERIC_ALT),
            PARAM_TEXT
        ));

        // Fixing content.
        $settings->add(new admin_setting_heading(
            "$p/fixheading",
            get_string('settings_fixes', $p),
            get_string('settings_fixes_desc', $p)
        ));
        $settings->add(new admin_setting_configcheckbox(
            "$p/enablefixes",
            get_string('enablefixes', $p),
            get_string('enablefixes_desc', $p),
            0
        ));

        // Link checking.
        $settings->add(new admin_setting_heading(
            "$p/linkheading",
            get_string('settings_links', $p),
            get_string('settings_links_desc', $p)
        ));
        $settings->add(new admin_setting_configcheckbox(
            "$p/linkcheck",
            get_string('linkcheck', $p),
            get_string('linkcheck_desc', $p),
            0
        ));
        $settings->add(new admin_setting_configtext(
            "$p/linksperrun",
            get_string('linksperrun', $p),
            get_string('linksperrun_desc', $p),
            100,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/linktimelimit",
            get_string('linktimelimit', $p),
            get_string('linktimelimit_desc', $p),
            300,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/linktimeout",
            get_string('linktimeout', $p),
            get_string('linktimeout_desc', $p),
            10,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/linkhostdelay",
            get_string('linkhostdelay', $p),
            get_string('linkhostdelay_desc', $p),
            1000,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/maxredirects",
            get_string('maxredirects', $p),
            get_string('maxredirects_desc', $p),
            5,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/linkrecheckdays",
            get_string('linkrecheckdays', $p),
            get_string('linkrecheckdays_desc', $p),
            7,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/linkconfirmattempts",
            get_string('linkconfirmattempts', $p),
            get_string('linkconfirmattempts_desc', $p),
            2,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/useragent",
            get_string('useragent', $p),
            get_string('useragent_desc', $p),
            'MoodleAccessibilityLinkChecker/1.0',
            PARAM_TEXT
        ));
        $settings->add(new admin_setting_configpasswordunmask(
            "$p/vimeotoken",
            get_string('vimeotoken', $p),
            get_string('vimeotoken_desc', $p),
            ''
        ));

        // PDF analysis.
        $settings->add(new admin_setting_heading(
            "$p/pdfheading",
            get_string('settings_pdf', $p),
            get_string('settings_pdf_desc', $p)
        ));
        $settings->add(new admin_setting_configcheckbox(
            "$p/pdfanalysis",
            get_string('pdfanalysis', $p),
            get_string('pdfanalysis_desc', $p),
            0
        ));
        $tools = \local_accessibilityreview\local\pdf\pdf_analyser::tools();
        $found = [];
        foreach ($tools as $tool => $path) {
            $found[] = $tool . ': ' . ($path !== '' ? $path : get_string('pdftoolnotfound', $p));
        }
        $settings->add(new admin_setting_heading(
            "$p/pdftoolsdetected",
            get_string('pdftoolsdetected', $p),
            s(implode(' | ', $found))
        ));
        $settings->add(new admin_setting_configexecutable(
            "$p/pathpdfinfo",
            get_string('pathpdfinfo', $p),
            get_string('pathpdfinfo_desc', $p),
            '/usr/bin/pdfinfo'
        ));
        $settings->add(new admin_setting_configexecutable(
            "$p/pathpdftotext",
            get_string('pathpdftotext', $p),
            get_string('pathpdftotext_desc', $p),
            '/usr/bin/pdftotext'
        ));
        $settings->add(new admin_setting_configtext(
            "$p/pdftimeout",
            get_string('pdftimeout', $p),
            get_string('pdftimeout_desc', $p),
            30,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/pdfmaxsize",
            get_string('pdfmaxsize', $p),
            get_string('pdfmaxsize_desc', $p),
            50,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/pdfmaxpages",
            get_string('pdfmaxpages', $p),
            get_string('pdfmaxpages_desc', $p),
            5,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/pdfperrun",
            get_string('pdfperrun', $p),
            get_string('pdfperrun_desc', $p),
            20,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            "$p/pdftasktimelimit",
            get_string('pdftasktimelimit', $p),
            get_string('pdftasktimelimit_desc', $p),
            300,
            PARAM_INT
        ));
    }
}
