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
 * Scheduled task for processing queued discourse stats reports.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats\task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

/**
 * Processes all queued report schedules.
 */
class schedule_task extends \core\task\scheduled_task {
    /**
     * Returns the localised task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'report_discoursestats') . ' – Schedule Task';
    }

    /**
     * Runs all queued report schedules.
     *
     * Grading schedules whose end date has already passed are processed immediately on
     * every task run, before the hour-based guard is checked.  Regular report schedules
     * (and future-dated grading schedules) only run at the configured execution hours.
     */
    public function execute() {
        global $DB;

        // Process grading schedules whose endtime has already passed.  These are run
        // regardless of the configured execution-hour guard so that grades are pushed as
        // soon as the 5-minute cron fires after the end date.
        $overduegradings = $DB->get_records_select(
            'report_discoursestats_schedules',
            'status = :status
             AND gradingname IS NOT NULL AND gradingname != :empty
             AND endtime IS NOT NULL AND endtime < :now',
            [
                'status' => DISCOURSESTATS_STATUS_SCHEDULED,
                'empty'  => '',
                'now'    => time(),
            ]
        );
        foreach ($overduegradings as $schedule) {
            mtrace('Executing overdue grading schedule ID: ' . $schedule->id);
            $success = discoursestats_executeschedule($schedule);
            mtrace($success ? 'Success' : 'Failed');
        }

        // Regular report schedules only run at the configured execution hours.
        if (discoursestats_getnextscheduledtime() > time()) {
            return;
        }

        // Fetch SCHEDULED records, excluding future-dated grading schedules so that they
        // remain SCHEDULED until their end date passes and the overdue path picks them up.
        $schedules = $DB->get_records_select(
            'report_discoursestats_schedules',
            'status = :status
             AND (gradingname IS NULL OR gradingname = :empty
                  OR endtime IS NULL OR endtime < :now)',
            [
                'status' => DISCOURSESTATS_STATUS_SCHEDULED,
                'empty'  => '',
                'now'    => time(),
            ]
        );

        foreach ($schedules as $schedule) {
            mtrace('Executing discourse stats schedule ID: ' . $schedule->id);
            $success = discoursestats_executeschedule($schedule);
            mtrace($success ? 'Success' : 'Failed');
        }

        set_config('lastexecution', time(), 'report_discoursestats');
    }
}
