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
 * Adhoc task for pushing grades after a report end date has passed.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats\task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

/**
 * Evaluates a grade formula for each student result and pushes to the gradebook.
 */
class grading_task extends \core\task\adhoc_task {
    /**
     * Executes the adhoc grading task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        if (empty($data->scheduleid)) {
            mtrace('report_discoursestats grading_task: no scheduleid in custom data.');
            return;
        }
        mtrace('report_discoursestats: pushing grades for schedule ID ' . $data->scheduleid);
        discoursestats_push_grades((int)$data->scheduleid);
        mtrace('report_discoursestats: grading complete for schedule ID ' . $data->scheduleid);
    }
}
