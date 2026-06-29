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
 * Report filter / schedule form for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats\forms;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');
require_once(__DIR__ . '/../../lib.php');

use report_discoursestats\engagement;

/**
 * Form for configuring a new report schedule.
 */
class report_form extends \moodleform {
    /** @var int Course ID. */
    private $courseid;
    /** @var \core\context\course Course context. */
    private $coursecontext;
    /** @var bool Whether the filter header is expanded by default. */
    private $expanded;

    /**
     * Constructor.
     *
     * @param int $courseid
     * @param bool $expanded
     */
    public function __construct(int $courseid, bool $expanded = true) {
        $this->courseid      = $courseid;
        $this->coursecontext = \core\context\course::instance($courseid);
        $this->expanded      = $expanded;
        parent::__construct(new \moodle_url('/report/discoursestats/index.php', ['id' => $courseid]));
    }

    /**
     * Form definition.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('header', 'filter', get_string('requestnewreport', 'report_discoursestats'));
        $mform->setExpanded('filter', $this->expanded);

        // Engagement method (report type).
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

        // Date range — enabled by default with sensible defaults.
        $dateoptions = ['optional' => true, 'startyear' => 2000, 'stopyear' => (int)date('Y') + 1, 'step' => 5];
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

        // Instant report checkbox (only shown to users with the capability).
        if (has_capability('report/discoursestats:getinstantreport', $this->coursecontext)) {
            $mform->addElement('checkbox', 'instant', get_string('getinstantreport', 'report_discoursestats'));
            $mform->setDefault('instant', 1);
        }

        // Hidden course ID.
        $mform->addElement('hidden', 'course', $this->courseid);
        $mform->setType('course', PARAM_INT);

        // Submit.
        $mform->addElement('submit', 'submit', get_string('showreport', 'report_discoursestats'));
    }
}
