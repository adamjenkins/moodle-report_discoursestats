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
 * Grading schedule form for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats\forms;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');
require_once($GLOBALS['CFG']->libdir . '/gradelib.php');
require_once(__DIR__ . '/../../lib.php');

use report_discoursestats\engagement;

/**
 * Form for configuring an automated grading schedule.
 */
class grading_form extends \moodleform {
    /** @var int Course ID. */
    private $courseid;
    /** @var \core\context\course Course context. */
    private $coursecontext;

    /**
     * Constructor.
     *
     * @param int $courseid
     */
    public function __construct(int $courseid) {
        $this->courseid      = $courseid;
        $this->coursecontext = \core\context\course::instance($courseid);
        parent::__construct();
    }

    /**
     * Form definition.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;

        // Grade item settings.
        $mform->addElement('header', 'gradesettings', get_string('gradesettings', 'report_discoursestats'));
        $mform->setExpanded('gradesettings', true);

        $mform->addElement('text', 'gradingname', get_string('gradingname', 'report_discoursestats'));
        $mform->setType('gradingname', PARAM_TEXT);
        $mform->addHelpButton('gradingname', 'gradingname', 'report_discoursestats');
        $mform->addRule('gradingname', get_string('required'), 'required', null, 'client');

        // Grade category.
        $catoptions = [0 => get_string('course')];
        $categories = \grade_category::fetch_all(['courseid' => $this->courseid]);
        if ($categories) {
            foreach ($categories as $cat) {
                if (!$cat->is_course_category()) {
                    $catoptions[$cat->id] = $cat->get_name();
                }
            }
        }
        $mform->addElement('select', 'gradingcategory', get_string('gradingcategory', 'report_discoursestats'), $catoptions);
        $mform->addHelpButton('gradingcategory', 'gradingcategory', 'report_discoursestats');
        $mform->setDefault('gradingcategory', 0);

        // Formula with placeholder reference.
        $mform->addElement(
            'textarea',
            'gradingformula',
            get_string('gradingformula', 'report_discoursestats'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('gradingformula', PARAM_TEXT);
        $mform->addHelpButton('gradingformula', 'gradingformula', 'report_discoursestats');
        $mform->addElement('html', $this->get_placeholder_html('id_gradingformula'));

        // Feedback template with placeholder reference.
        $mform->addElement(
            'textarea',
            'gradingfeedback',
            get_string('gradingfeedback', 'report_discoursestats'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('gradingfeedback', PARAM_TEXT);
        $mform->addHelpButton('gradingfeedback', 'gradingfeedback', 'report_discoursestats');
        $mform->addElement('html', $this->get_placeholder_html('id_gradingfeedback'));

        $mform->addElement('text', 'gradingmax', get_string('gradingmax', 'report_discoursestats'), ['size' => 6]);
        $mform->setType('gradingmax', PARAM_FLOAT);
        $mform->setDefault('gradingmax', 100);

        $mform->addElement('checkbox', 'gradinghidden', get_string('gradinghidden', 'report_discoursestats'));
        $mform->addHelpButton('gradinghidden', 'gradinghidden', 'report_discoursestats');
        $mform->setDefault('gradinghidden', 0);

        // Report filter settings.
        $mform->addElement('header', 'reportsettings', get_string('reportsettings', 'report_discoursestats'));
        $mform->setExpanded('reportsettings', true);

        engagement::addtoform($mform, 'engagementmethod', null, 'engagementinternational', true);

        // Forum multi-select.
        $forumrecords = $DB->get_records('forum', ['course' => $this->courseid], 'name', 'id,name');
        $forumoptions = [];
        foreach ($forumrecords as $forum) {
            $forumoptions[$forum->id] = $forum->name;
        }
        $mform->addElement(
            'autocomplete',
            'forums',
            get_string('forums', 'report_discoursestats'),
            $forumoptions,
            ['multiple' => true, 'noselectionstring' => get_string('all')]
        );
        $mform->addHelpButton('forums', 'forums', 'report_discoursestats');

        // Database module — checkbox enable + conditional selector.
        $mform->addElement('checkbox', 'includedbinstances', get_string('includedbinstances', 'report_discoursestats'));
        $mform->setDefault('includedbinstances', 0);

        $datarecords = $DB->get_records('data', ['course' => $this->courseid], 'name', 'id,name');
        $dataoptions = [];
        foreach ($datarecords as $data) {
            $dataoptions[$data->id] = $data->name;
        }
        $mform->addElement(
            'autocomplete',
            'dbinstances',
            get_string('dbinstances', 'report_discoursestats'),
            $dataoptions,
            ['multiple' => true, 'noselectionstring' => get_string('alldatabases', 'report_discoursestats')]
        );
        $mform->addHelpButton('dbinstances', 'dbinstances', 'report_discoursestats');
        $mform->hideIf('dbinstances', 'includedbinstances', 'notchecked');

        // Group filter.
        $groupoptions = [];
        if (has_capability('report/discoursestats:viewothergroups', $this->coursecontext)) {
            $allgroups = groups_get_all_groups($this->courseid);
            if (count($allgroups)) {
                $groupoptions[0] = get_string('allgroups');
                foreach ($allgroups as $group) {
                    $groupoptions[$group->id] = $group->name;
                }
            }
        } else {
            $mygroups = groups_get_user_groups($this->courseid);
            $groupoptions[0] = get_string('allmygroups', 'report_discoursestats');
            foreach ($mygroups[0] as $mygroupid) {
                $groupoptions[$mygroupid] = groups_get_group_name($mygroupid);
            }
        }
        if (!empty($groupoptions)) {
            $mform->addElement('select', 'group', get_string('group'), $groupoptions);
        }

        // Country filter.
        $countrychoices = ['0' => get_string('all')] + get_string_manager()->get_list_of_countries();
        $mform->addElement('select', 'country', get_string('country'), $countrychoices);
        $mform->setDefault('country', '0');

        // Date range — end date required for grading schedules.
        $dateoptions = ['optional' => true, 'startyear' => 2000, 'stopyear' => (int)date('Y') + 2, 'step' => 5];
        $mform->addElement('date_time_selector', 'starttime', get_string('reportstart', 'report_discoursestats'), $dateoptions);
        $mform->setDefault('starttime', usergetmidnight(time() - 14 * DAYSECS));
        $mform->addElement('date_time_selector', 'endtime', get_string('reportend', 'report_discoursestats'), $dateoptions);
        $mform->setDefault('endtime', usergetmidnight(time()));

        // Stale reply threshold.
        $staledays = [];
        for ($i = 1; $i <= 14; $i++) {
            $staledays[$i] = $i;
        }
        $staledays[21] = 21;
        $staledays[28] = 28;
        $mform->addElement('select', 'stalethreshold', get_string('stalethreshold', 'report_discoursestats'), $staledays);
        $mform->addHelpButton('stalethreshold', 'stalethreshold', 'report_discoursestats');
        $mform->setDefault('stalethreshold', 3);

        // Hidden course ID.
        $mform->addElement('hidden', 'course', $this->courseid);
        $mform->setType('course', PARAM_INT);

        $mform->addElement('submit', 'submit', get_string('addgradingschedule', 'report_discoursestats'));

        global $PAGE;
        $PAGE->requires->js_call_amd('report_discoursestats/placeholder_picker', 'init');
    }

    /**
     * Build an expandable placeholder reference panel.
     *
     * @param string $targetid The id attribute of the target textarea element.
     * @return string
     */
    private function get_placeholder_html(string $targetid): string {
        $fields  = discoursestats_formula_fields();
        $liitems = '';
        foreach ($fields as $f) {
            $text     = '{' . $f . '}';
            $liitems .= '<li><code data-insert="' . $text . '" style="cursor:pointer" title="'
                . get_string('formulaplaceholders', 'report_discoursestats') . '">'
                . $text . '</code></li>';
        }
        $summary = get_string('formulaplaceholders', 'report_discoursestats');
        return '<div class="form-group row fitem">'
            . '<div class="col-md-3 col-form-label d-flex pb-0 pr-md-0"></div>'
            . '<div class="col-md-9 felement">'
            . '<details data-placeholdertarget="' . $targetid . '">'
            . '<summary class="text-muted small">' . $summary . '</summary>'
            . '<ul class="list-unstyled text-muted small mt-1">' . $liitems . '</ul>'
            . '</details></div></div>';
    }

    /**
     * Validate the submitted form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['endtime'])) {
            $errors['endtime'] = get_string('required');
        }

        return $errors;
    }
}
