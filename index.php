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
 * Main index page for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/forms/report_form.php');

use report_discoursestats\forms\report_form;
use report_discoursestats\forms\grading_form;

$courseid = required_param('id', PARAM_INT);
$course   = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);

$coursecontext = \core\context\course::instance($courseid);
require_capability('report/discoursestats:view', $coursecontext);

$haspushgrades = has_capability('report/discoursestats:pushgrades', $coursecontext);

$hasreportinqueue = $DB->count_records_select(
    'discoursestats_schedules',
    'userid = :userid AND status = :status AND (gradingname IS NULL OR gradingname = :empty)',
    ['userid' => $USER->id, 'status' => DISCOURSESTATS_STATUS_SCHEDULED, 'empty' => '']
) > 0;

$form = new report_form($courseid, !$hasreportinqueue);

// Report form cancellation.
if ($form->is_cancelled()) {
    redirect(new \moodle_url('/course/view.php', ['id' => $courseid]));
    exit;
}

// Report form submission.
if ($formdata = $form->get_data()) {
    discoursestats_removeexistingschedule();
    $scheduleid = discoursestats_addschedule($formdata, $coursecontext);
    if (!empty($formdata->instant)) {
        redirect(new \moodle_url('/report/discoursestats/view.php', ['id' => $scheduleid]));
    } else {
        redirect(new \moodle_url('/report/discoursestats/index.php', ['id' => $courseid]));
    }
    exit;
}

// Grading form — only for users with the pushgrades capability.
$gradingform = null;
if ($haspushgrades) {
    require_once(__DIR__ . '/classes/forms/grading_form.php');
    $gradingform = new grading_form($courseid);

    if ($gradingformdata = $gradingform->get_data()) {
        discoursestats_addschedule($gradingformdata, $coursecontext);
        redirect(
            new \moodle_url('/report/discoursestats/index.php', ['id' => $courseid]),
            get_string('gradingscheduleadded', 'report_discoursestats')
        );
        exit;
    }
}

$PAGE->set_pagelayout('incourse');
$PAGE->set_url(new \moodle_url('/report/discoursestats/index.php', ['id' => $courseid]));
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'report_discoursestats'));

echo $OUTPUT->header();

// Report filter form.
echo $form->render();

// My requested reports.
echo $OUTPUT->render_from_template(
    'report_discoursestats/myreports',
    discoursestats_getreportscontext($USER->id)
);

// Automated grading sections — capability-gated.
if ($haspushgrades && $gradingform) {
    // Show "Automated Grading" section with collapsible "Add grading schedule" form.
    $gradingformopen = $gradingform->is_submitted() && !$gradingform->get_data();
    $collapseshow    = $gradingformopen ? ' show' : '';
    $ariaexpanded    = $gradingformopen ? 'true' : 'false';

    echo \html_writer::start_div('mt-4');
    echo $OUTPUT->heading(get_string('gradingsection', 'report_discoursestats'), 3);
    echo \html_writer::tag(
        'button',
        get_string('addgradingschedule', 'report_discoursestats'),
        [
            'class'          => 'btn btn-secondary mb-3',
            'type'           => 'button',
            'data-bs-toggle' => 'collapse',
            'data-bs-target' => '#discoursestats-grading-form',
            'aria-expanded'  => $ariaexpanded,
            'aria-controls'  => 'discoursestats-grading-form',
        ]
    );
    echo \html_writer::start_div('collapse' . $collapseshow, ['id' => 'discoursestats-grading-form']);
    echo $gradingform->render();
    echo \html_writer::end_div();
    echo \html_writer::end_div();

    // Automatically generated grades section.
    echo \html_writer::start_div('mt-4');
    echo $OUTPUT->render_from_template(
        'report_discoursestats/gradingschedules',
        discoursestats_getgradingschedulescontext($courseid)
    );
    echo \html_writer::end_div();
}

echo $OUTPUT->footer();
