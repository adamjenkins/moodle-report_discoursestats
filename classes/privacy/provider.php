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
 * Privacy provider for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the discourse stats report plugin.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about the data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('report_discoursestats_schedules', [
            'userid'      => 'privacy:metadata:discoursestats_schedules:userid',
            'course'      => 'privacy:metadata:discoursestats_schedules:course',
            'createdtime' => 'privacy:metadata:discoursestats_schedules:createdtime',
        ], 'privacy:metadata:discoursestats_schedules');

        $collection->add_database_table('report_discoursestats_results', [
            'userid'    => 'privacy:metadata:discoursestats_results:userid',
            'username'  => 'privacy:metadata:discoursestats_results:username',
            'firstname' => 'privacy:metadata:discoursestats_results:firstname',
            'lastname'  => 'privacy:metadata:discoursestats_results:lastname',
            'posts'     => 'privacy:metadata:discoursestats_results:posts',
            'replies'   => 'privacy:metadata:discoursestats_results:replies',
        ], 'privacy:metadata:discoursestats_results');

        $collection->add_database_table('report_discoursestats_grading_log', [
            'userid'   => 'privacy:metadata:discoursestats_grading_log:userid',
            'rawgrade' => 'privacy:metadata:discoursestats_grading_log:rawgrade',
            'feedback' => 'privacy:metadata:discoursestats_grading_log:feedback',
        ], 'privacy:metadata:discoursestats_grading_log');

        return $collection;
    }

    /**
     * Returns the contexts that contain personal data for the given userid.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {discoursestats_schedules} s ON s.course = ctx.instanceid
                  WHERE ctx.contextlevel = :ctxlevel
                    AND s.userid = :userid";
        $contextlist->add_from_sql($sql, ['ctxlevel' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Returns the list of users who have personal data in the given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $sql = "SELECT userid FROM {discoursestats_schedules} WHERE course = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
        $sql = "SELECT r.userid
                  FROM {discoursestats_results} r
                  JOIN {discoursestats_schedules} s ON r.schedule = s.id
                 WHERE s.course = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Export all personal data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $schedules = $DB->get_records('report_discoursestats_schedules', ['userid' => $userid, 'course' => $context->instanceid]);
            foreach ($schedules as $schedule) {
                $results = $DB->get_records('report_discoursestats_results', ['schedule' => $schedule->id, 'userid' => $userid]);
                writer::with_context($context)->export_data(
                    ['report_discoursestats', 'schedule_' . $schedule->id],
                    (object)['schedule' => $schedule, 'results' => array_values($results)]
                );
            }
        }
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $scheduleids = $DB->get_fieldset_select(
            'report_discoursestats_schedules',
            'id',
            'course = :course',
            ['course' => $context->instanceid]
        );
        if ($scheduleids) {
            [$sql, $params] = $DB->get_in_or_equal($scheduleids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('report_discoursestats_results', "schedule $sql", $params);
            $DB->delete_records_select('report_discoursestats_aggregate_results', "schedule $sql", $params);
            $DB->delete_records_select('report_discoursestats_grading_log', "scheduleid $sql", $params);
            $DB->delete_records('report_discoursestats_schedules', ['course' => $context->instanceid]);
        }
    }

    /**
     * Delete personal data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $scheduleids = $DB->get_fieldset_select(
                'report_discoursestats_schedules',
                'id',
                'course = :course AND userid = :userid',
                ['course' => $context->instanceid, 'userid' => $userid]
            );
            if ($scheduleids) {
                [$sql, $params] = $DB->get_in_or_equal($scheduleids, SQL_PARAMS_NAMED);
                $DB->delete_records_select('report_discoursestats_results', "schedule $sql", $params);
                $DB->delete_records_select('report_discoursestats_aggregate_results', "schedule $sql", $params);
                $DB->delete_records_select('report_discoursestats_grading_log', "scheduleid $sql", $params);
            }
            $DB->delete_records('report_discoursestats_schedules', ['course' => $context->instanceid, 'userid' => $userid]);
        }
    }

    /**
     * Delete personal data for multiple users within a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid_');
        $scheduleids = $DB->get_fieldset_select(
            'report_discoursestats_schedules',
            'id',
            "course = :course AND userid $usersql",
            array_merge(['course' => $context->instanceid], $userparams)
        );
        if ($scheduleids) {
            [$sql, $params] = $DB->get_in_or_equal($scheduleids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('report_discoursestats_results', "schedule $sql", $params);
            $DB->delete_records_select('report_discoursestats_aggregate_results', "schedule $sql", $params);
            $DB->delete_records_select('report_discoursestats_grading_log', "scheduleid $sql", $params);
        }
        $DB->delete_records_select(
            'report_discoursestats_schedules',
            "course = :course AND userid $usersql",
            array_merge(['course' => $context->instanceid], $userparams)
        );
    }
}
