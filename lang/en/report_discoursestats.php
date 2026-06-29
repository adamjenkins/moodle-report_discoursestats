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
 * Language strings for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['actions'] = 'Actions';
$string['addgradingschedule'] = 'Add grading schedule';
$string['alldatabases'] = 'All databases in course';
$string['allmygroups'] = 'All of my groups';
$string['audios'] = 'Audios';
$string['automatedgrades'] = 'Automatically generated grades';
$string['completereport'] = 'Complete Report';
$string['createdby'] = 'Created by';
$string['dbcomments'] = 'Database comments';
$string['dbentries'] = 'Database entries';
$string['dbinstances'] = 'Database modules';
$string['dbinstances_help'] = 'Select database module instances to include entry and comment counts. Leave empty to skip database module metrics.';
$string['delete'] = 'Delete';
$string['deleteconfirmation_description'] = 'Are you sure you want to delete this report?';
$string['deleteconfirmation_title'] = 'Report Delete Confirmation';
$string['discoursestats:getinstantreport'] = 'Get report results instantly without being scheduled';
$string['discoursestats:pushgrades'] = 'Push grades to the gradebook from a discourse stats report';
$string['discoursestats:view'] = 'View discourse stats report';
$string['discoursestats:viewothergroups'] = 'View discourse stats report data for users from other groups';
$string['el1'] = '1st Engagement';
$string['el2'] = '2nd Engagement';
$string['el3'] = '3rd Engagement';
$string['el4up'] = '4th+ Engagement';
$string['elavg'] = 'Average Engagement Level';
$string['elmax'] = 'Maximum Engagement Level';
$string['emptyresults'] = 'This report request has no results. Please try again with a different configuration.';
$string['engagement_admin_defaultmethod'] = 'Default Engagement Calculation Method';
$string['engagement_international'] = 'International engagement only';
$string['engagement_method'] = 'Engagement method';
$string['engagement_method_help'] = '<p>Engagement Calculation Method</p><strong>Person-to-Person Engagement:</strong> The engagement level increases each time a user replies to the same user in the same thread.<br><strong>Thread Total Count Engagement:</strong> The engagement level increases each time a user participates in the same thread.<br><strong>Thread Engagement:</strong> The engagement level increases each time a user participates in a reply where they already participated in the parent posts.';
$string['engagement_persontoperson'] = 'Person-to-Person Engagement';
$string['engagement_persontoperson_description'] = 'The engagement level increases each time a user replies to the same user in the same thread.';
$string['engagement_threadengagement'] = 'Thread Engagement';
$string['engagement_threadengagement_description'] = 'The engagement level increases each time a user participates in a reply where they already participated in the parent posts.';
$string['engagement_threadtotalcount'] = 'Thread Total Count Engagement';
$string['engagement_threadtotalcount_description'] = 'The engagement level increases each time a user participates in the same thread.';
$string['executionschedule'] = 'Execution Schedule';
$string['executionschedule_help'] = 'Numbers of time in 24-hour format to execute schedule, separated with comma (,). For example, "3, 15" for 3AM and 3PM of every day.';
$string['firstpost'] = 'First Post';
$string['formulaplaceholders'] = 'View available field names';
$string['forums'] = 'Forums';
$string['forums_help'] = 'Select one or more forums to include in the report. Leave empty to include all forums in the course.';
$string['getinstantreport'] = 'Get instant report result (without being scheduled)';
$string['gradesettings'] = 'Grade settings';
$string['gradingcategory'] = 'Grade category';
$string['gradingcategory_help'] = 'The grade category in which the grade item will be placed. Leave as "Course" to use the top-level grade category.';
$string['gradingfeedback'] = 'Feedback template';
$string['gradingfeedback_help'] = 'A template for the grade feedback. Use {fieldname} placeholders to insert values from the report, e.g. "You wrote {replies} replies and {posts} new posts."';
$string['gradingformula'] = 'Grade formula';
$string['gradingformula_help'] = 'A mathematical formula to calculate the grade. Use field names directly or wrapped in {braces}: posts, replies, views, wordcount, multimedia, images, videos, audios, links, engagement1, engagement2, engagement3, engagement4, averageengagement, maximumengagement, stalereply, selfreply, repliestoseed, dbentries, dbcomments, uniquedaysactive, uniquedaysviewed. Supported functions: min(), max(), round(), ceil(), floor(), abs(). Example: min(replies * 2 + posts * 5, 100)';
$string['gradinghidden'] = 'Hide grade from students';
$string['gradinghidden_help'] = 'When enabled, the grade item is hidden in the gradebook so students cannot see it.';
$string['gradingmax'] = 'Maximum grade';
$string['gradingname'] = 'Grade item name';
$string['gradingname_help'] = 'The name of the grade item in the gradebook. When this is set and the report end date has passed, grades will be automatically calculated and pushed to the gradebook.';
$string['gradingscheduleadded'] = 'Grading schedule added successfully.';
$string['gradingsection'] = 'Automated grading';
$string['images'] = 'Images';
$string['includedbinstances'] = 'Include database module activity';
$string['lastpost'] = 'Last Post';
$string['links'] = 'Links';
$string['multimedia'] = 'Multimedia';
$string['multimedia_audio'] = 'Audios';
$string['multimedia_image'] = 'Images';
$string['multimedia_link'] = 'Links';
$string['multimedia_video'] = 'Videos';
$string['myrequestedreports'] = 'My Requested Reports';
$string['nodata'] = 'There are no data to display.';
$string['perpage'] = 'No. of records per page';
$string['pluginname'] = 'Discourse Stats';
$string['privacy:metadata:discoursestats_results'] = 'Report results containing per-user forum statistics.';
$string['privacy:metadata:discoursestats_results:firstname'] = 'The first name of the user.';
$string['privacy:metadata:discoursestats_results:lastname'] = 'The last name of the user.';
$string['privacy:metadata:discoursestats_results:posts'] = 'The number of posts made by the user.';
$string['privacy:metadata:discoursestats_results:replies'] = 'The number of replies made by the user.';
$string['privacy:metadata:discoursestats_results:userid'] = 'The ID of the user whose data was captured.';
$string['privacy:metadata:discoursestats_results:username'] = 'The username of the user.';
$string['privacy:metadata:discoursestats_schedules'] = 'Report schedule requests created by users.';
$string['privacy:metadata:discoursestats_schedules:course'] = 'The course for which the report was requested.';
$string['privacy:metadata:discoursestats_schedules:createdtime'] = 'The time the report was requested.';
$string['privacy:metadata:discoursestats_schedules:userid'] = 'The ID of the user who created the report request.';
$string['reactionsgiven'] = 'Reactions Given';
$string['reactionsreceived'] = 'Reactions Received';
$string['replies'] = 'Replies';
$string['repliestoseed'] = 'Direct replies to original post';
$string['reportend'] = 'End';
$string['reportfilter'] = 'Report filter';
$string['reportschedule'] = 'Report schedule';
$string['reportsettings'] = 'Report settings';
$string['reportstart'] = 'Start';
$string['requestedtime'] = 'Requested Time';
$string['requestnewreport'] = 'Request a new report';
$string['scheduledtime'] = 'Scheduled Time';
$string['selfreply'] = 'Self-replies';
$string['showreport'] = 'Show report';
$string['stalereply'] = 'Stale replies';
$string['stalethreshold'] = 'Stale reply threshold (days)';
$string['stalethreshold_help'] = 'Replies posted this many days or more after the parent post are counted as stale replies.';
$string['status'] = 'Status';
$string['status_error'] = 'Failed';
$string['status_executing'] = 'Running…';
$string['status_finish'] = 'Finished';
$string['status_manual'] = 'Manual Run';
$string['status_scheduled'] = 'Scheduled';
$string['uniqueactive'] = 'Unique days active';
$string['uniqueview'] = 'Unique days viewed';
$string['videos'] = 'Videos';
$string['view'] = 'View';
$string['views'] = 'Views';
$string['wordcount'] = 'Word count';
