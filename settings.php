<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('report_discoursestats', get_string('pluginname', 'report_discoursestats'));

    $settings->add(new admin_setting_configtext(
        'report_discoursestats/executionschedule',
        get_string('executionschedule', 'report_discoursestats'),
        get_string('executionschedule_help', 'report_discoursestats'),
        '3',
        PARAM_TEXT
    ));

    require_once(__DIR__ . '/classes/engagement.php');
    $settings->add(new admin_setting_configselect(
        'report_discoursestats/defaultengagementmethod',
        get_string('engagement_admin_defaultmethod', 'report_discoursestats'),
        '',
        \report_discoursestats\engagement::PERSON_TO_PERSON,
        \report_discoursestats\engagement::getselectoptions()
    ));
}
