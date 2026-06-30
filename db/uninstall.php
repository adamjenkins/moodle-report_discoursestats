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
 * Uninstall hook for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove manual grade items created by this plugin from all course gradebooks.
 *
 * Grade items are identified by the 'discoursestats_<scheduleid>' idnumber prefix
 * that discoursestats_push_grades() assigns.  Because itemtype='manual' items are
 * not linked to the plugin via itemmodule, Moodle does not clean them up automatically
 * when the plugin tables are dropped.
 *
 * @return bool
 */
function xmldb_report_discoursestats_uninstall() {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $items = grade_item::fetch_all([
        'itemtype' => 'manual',
    ]);

    if ($items) {
        foreach ($items as $item) {
            if (strpos((string)$item->idnumber, 'discoursestats_') === 0) {
                $item->delete('report/discoursestats');
            }
        }
    }

    return true;
}
