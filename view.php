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
 * View/download/delete a report schedule result.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/forms/deleteconfirm_form.php');

use report_discoursestats\engagement;
use report_discoursestats\forms\deleteconfirm_form;

$id     = required_param('id', PARAM_INT);
$action = optional_param('action', 'view', PARAM_ALPHA);

$schedule = $DB->get_record('report_discoursestats_schedules', ['id' => $id], '*', MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $schedule->course], '*', MUST_EXIST);

require_login($course);
$coursecontext = \core\context\course::instance($course->id);
require_capability('report/discoursestats:view', $coursecontext);

if ($USER->id != $schedule->userid) {
    throw new \moodle_exception('nopermissions', 'error', '', 'view this report');
}

// Download CSV.
if ($action === 'download') {
    if ($schedule->status != DISCOURSESTATS_STATUS_FINISH) {
        throw new \moodle_exception('error');
    }
    require_once($CFG->libdir . '/csvlib.class.php');
    $csv      = new \csv_export_writer();
    $csv->set_filename('discoursestats_' . $schedule->course . '_' . date('Ymd', $schedule->processedtime));
    $reporttype = (int)($schedule->reporttype ?? DISCOURSESTATS_REPORTTYPE_STUDENT);
    if ($reporttype === DISCOURSESTATS_REPORTTYPE_STUDENT) {
        $visiblekeys = discoursestats_getvisiblecolumnkeys($schedule);
        $allheaders  = discoursestats_getresultsheader();
        $csv->add_data(array_values(array_intersect_key($allheaders, array_flip($visiblekeys))));
        $sanitizecsv = static function (array $row): array {
            return array_map(static function ($v) {
                $v = (string)$v;
                return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
            }, $row);
        };
        foreach ($DB->get_records('report_discoursestats_results', ['schedule' => $schedule->id]) as $result) {
            $csv->add_data($sanitizecsv(discoursestats_getresultsrow($result, $visiblekeys)));
        }
    } else {
        $sanitizecsv = static function (array $row): array {
            return array_map(static function ($v) {
                $v = (string)$v;
                return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
            }, $row);
        };
        $aggheaders  = discoursestats_getaggregateresultsheader($reporttype, $schedule);
        $visiblekeys = array_keys($aggheaders);
        $csv->add_data(array_values($aggheaders));
        foreach (
            $DB->get_records_select(
                'report_discoursestats_aggregate_results',
                'schedule = :s AND reporttype = :t',
                ['s' => $schedule->id, 't' => $reporttype]
            ) as $result
        ) {
            $csv->add_data($sanitizecsv(discoursestats_getaggregateresultsrow($result, $visiblekeys)));
        }
    }
    $csv->download_file();
    exit;
}

// Delete.
$deleteform = $action === 'delete' ? new deleteconfirm_form($schedule->id) : null;

if ($action === 'delete' && $deleteform->is_submitted()) {
    if ($deleteform->is_cancelled()) {
        redirect(new \moodle_url('/report/discoursestats/view.php', ['id' => $schedule->id]));
        exit;
    }
    if ($deleteform->get_data()) {
        discoursestats_removeschedule($schedule->id);
        redirect(new \moodle_url('/report/discoursestats/index.php', ['id' => $course->id]));
        exit;
    }
}

// Page setup.
$PAGE->set_pagelayout('incourse');
$PAGE->set_url(new \moodle_url('/report/discoursestats/view.php', ['id' => $schedule->id]));
$PAGE->navbar->add(
    get_string('pluginname', 'report_discoursestats'),
    new \moodle_url('/report/discoursestats/index.php', ['id' => $course->id])
);
$PAGE->navbar->add(get_string('reportschedule', 'report_discoursestats'));
$PAGE->set_heading(get_string('reportschedule', 'report_discoursestats'));
$PAGE->set_title(get_string('reportschedule', 'report_discoursestats'));

echo $OUTPUT->header();

// View.
if ($action === 'view') {
    $status         = discoursestats_getstatus($schedule->status);
    $scheduledtime  = $schedule->status === DISCOURSESTATS_STATUS_SCHEDULED
        ? discoursestats_getnextscheduledtime()
        : $schedule->processedtime;

    // Build forum names display.
    $forumids   = discoursestats_getforumids($schedule);
    $forumnames = [];
    foreach ($forumids as $fid) {
        $f = $DB->get_record('forum', ['id' => $fid], 'name');
        if ($f) {
            $forumnames[] = $f->name;
        }
    }
    $forumsdisplay = count($forumnames) > 0 ? implode(', ', $forumnames) : get_string('all');

    // DB instance names.
    $dbnames = [];
    if (!empty($schedule->dbinstances)) {
        foreach (json_decode($schedule->dbinstances, true) as $did) {
            $d = $DB->get_record('data', ['id' => $did], 'name');
            if ($d) {
                $dbnames[] = $d->name;
            }
        }
    }

    $countries = get_string_manager()->get_list_of_countries();

    echo $OUTPUT->render_from_template('report_discoursestats/scheduleinfo', [
        'createdby'             => fullname($DB->get_record('user', ['id' => $schedule->userid], '*', MUST_EXIST)),
        'requestedtime'         => userdate($schedule->createdtime, get_string('strftimedaydatetime', 'langconfig')),
        'scheduledtime'         => $scheduledtime ? userdate($scheduledtime, get_string('strftimedaydatetime', 'langconfig')) : '-',
        'status'                => $status[0],
        'statusclass'           => $status[1],
        'message'               => $schedule->message,
        'forums'                => $forumsdisplay,
        'stalethreshold'        => $schedule->stalethreshold ?? 7,
        'dbinstances'           => count($dbnames) > 0 ? implode(', ', $dbnames) : '-',
        'country'               => ($schedule->country && $schedule->country !== '0')
            ? ($countries[$schedule->country] ?? $schedule->country)
            : get_string('all'),
        'group'                 => $schedule->groupid
            ? $DB->get_record('groups', ['id' => $schedule->groupid], '*', MUST_EXIST)->name
            : get_string('all'),
        'starttime'             => $schedule->starttime
            ? userdate($schedule->starttime, get_string('strftimedaydatetime', 'langconfig'))
            : '-',
        'endtime'               => $schedule->endtime
            ? userdate($schedule->endtime, get_string('strftimedaydatetime', 'langconfig'))
            : '-',
        'engagementmethod'      => engagement::getname($schedule->engagementmethod ?? 1),
        'engagementinternational' => $schedule->engagementinternational ? get_string('yes') : get_string('no'),
        'gradingname'           => $schedule->gradingname ?? '',
        'downloadurl'           => discoursestats_getdownloadurl($schedule),
        'deleteurl'             => discoursestats_getdeleteurl($schedule),
    ]);

    if ($schedule->status == DISCOURSESTATS_STATUS_FINISH) {
        $sortname   = optional_param('sn', null, PARAM_ALPHANUMEXT);
        $sorttype   = optional_param('sd', 'asc', PARAM_ALPHA);
        $reporttype = (int)($schedule->reporttype ?? DISCOURSESTATS_REPORTTYPE_STUDENT);

        if ($reporttype === DISCOURSESTATS_REPORTTYPE_STUDENT) {
            $visiblekeys = discoursestats_getvisiblecolumnkeys($schedule);
            $sort        = discoursestats_getsort($sortname, $sorttype);
            $results     = $DB->get_records('report_discoursestats_results', ['schedule' => $schedule->id], $sort);
            $rows        = [];
            foreach ($results as $result) {
                $rows[] = [
                    'records'   => discoursestats_getresultsrow($result, $visiblekeys),
                    'reporturl' => new \moodle_url('/report/outline/user.php', [
                        'id'     => $result->userid,
                        'course' => $schedule->course,
                        'mode'   => 'complete',
                    ]),
                ];
            }
            echo $OUTPUT->render_from_template('report_discoursestats/results', [
                'headers'        => discoursestats_getresultsheadercontext($schedule->id, $visiblekeys, $sortname, $sorttype),
                'rows'           => $rows,
                'empty'          => count($rows) === 0,
                'showreportlink' => true,
            ]);
        } else {
            $aggheaders  = discoursestats_getaggregateresultsheader($reporttype, $schedule);
            $visiblekeys = array_keys($aggheaders);
            $sort        = discoursestats_getaggregatesort($sortname, $sorttype, $reporttype, $schedule);
            $results     = $DB->get_records_select(
                'report_discoursestats_aggregate_results',
                'schedule = :s AND reporttype = :t',
                ['s' => $schedule->id, 't' => $reporttype],
                $sort
            );
            $rows = [];
            foreach ($results as $result) {
                $rows[] = [
                    'records'   => discoursestats_getaggregateresultsrow($result, $visiblekeys),
                    'reporturl' => null,
                ];
            }
            echo $OUTPUT->render_from_template('report_discoursestats/results', [
                'headers'        => discoursestats_getaggregateresultsheadercontext(
                    $schedule->id,
                    $visiblekeys,
                    $aggheaders,
                    $sortname,
                    $sorttype
                ),
                'rows'           => $rows,
                'empty'          => count($rows) === 0,
                'showreportlink' => false,
            ]);
        }
    }
} else if ($action === 'delete' && $deleteform) {
    $deleteform->display();
    echo \html_writer::empty_tag('hr');
}

echo $OUTPUT->footer();
