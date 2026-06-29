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
 * Event observer for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats\event;

/**
 * Listens for course lifecycle events that require report data cleanup.
 */
class observer {
    /**
     * Purge all report data for a course when the teacher resets all forum posts.
     *
     * Moodle's course reset form only calls _reset_userdata hooks for mod_ plugins.
     * Report plugins must use events instead. We delete our data whenever
     * reset_forum_all is selected, because all underlying post data is now gone.
     *
     * @param \core\event\course_reset_ended $event
     */
    public static function course_reset_ended(\core\event\course_reset_ended $event): void {
        global $DB;

        $options = $event->other['reset_options'] ?? [];
        if (empty($options['reset_forum_all'])) {
            return;
        }

        $courseid = $event->courseid;
        $scheduleids = $DB->get_fieldset_select(
            'discoursestats_schedules',
            'id',
            'course = :course',
            ['course' => $courseid]
        );
        if (!$scheduleids) {
            return;
        }
        $DB->delete_records_list('discoursestats_results', 'schedule', $scheduleids);
        $DB->delete_records_list('discoursestats_aggregate_results', 'schedule', $scheduleids);
        $DB->delete_records_list('discoursestats_grading_log', 'scheduleid', $scheduleids);
        $DB->delete_records_list('discoursestats_schedules', 'id', $scheduleids);
    }
}
