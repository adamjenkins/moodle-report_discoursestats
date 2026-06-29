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
 * Delete confirmation form for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats\forms;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Confirmation form before deleting a schedule.
 */
class deleteconfirm_form extends \moodleform {
    /** @var int The schedule to confirm deletion of. */
    private $scheduleid;

    /**
     * Constructor.
     *
     * @param int $scheduleid
     */
    public function __construct(int $scheduleid) {
        $this->scheduleid = $scheduleid;
        parent::__construct();
    }

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'confirm', get_string('deleteconfirmation_title', 'report_discoursestats'));
        $mform->addElement('static', 'description', '', get_string('deleteconfirmation_description', 'report_discoursestats'));

        $mform->addElement('hidden', 'id', $this->scheduleid);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'delete');
        $mform->setType('action', PARAM_ALPHA);

        $buttonarray = [
            $mform->createElement('submit', 'submitbutton', get_string('delete', 'report_discoursestats')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }
}
