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
 * Upgrade steps for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_report_discoursestats_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026062901) {
        $table = new xmldb_table('discoursestats_schedules');
        $field = new xmldb_field('gradingcategory', XMLDB_TYPE_INTEGER, '10', null, false, false, '0', 'gradingmax');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026062901, 'report', 'discoursestats');
    }

    if ($oldversion < 2026062902) {
        $table = new xmldb_table('discoursestats_schedules');
        $field = new xmldb_field('gradinghidden', XMLDB_TYPE_INTEGER, '1', null, false, false, '0', 'gradingcategory');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026062902, 'report', 'discoursestats');
    }

    if ($oldversion < 2026063002) {
        $table = new xmldb_table('discoursestats_schedules');

        $field = new xmldb_field('reporttype', XMLDB_TYPE_INTEGER, '2', null, false, false, '1', 'gradingtriggered');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('hiddencolumns', XMLDB_TYPE_TEXT, null, null, false, false, null, 'reporttype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $aggtable = new xmldb_table('discoursestats_aggregate_results');
        if (!$dbman->table_exists($aggtable)) {
            $aggtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $aggtable->add_field('schedule', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $aggtable->add_field('reporttype', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '2');
            $aggtable->add_field('rowid', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('rowname', XMLDB_TYPE_CHAR, '255', null, false);
            $aggtable->add_field('activemembers', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('inactivemembers', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('nationalities', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('posts', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('replies', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('stalereply', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('selfreply', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('repliestoseed', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('uniquedaysactive', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('views', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('uniquedaysviewed', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('wordcount', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('multimedia', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('images', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('videos', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('audios', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('links', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('dbentries', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('dbcomments', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('engagement1', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('engagement2', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('engagement3', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('engagement4', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('averageengagement', XMLDB_TYPE_FLOAT, null, null, false);
            $aggtable->add_field('maximumengagement', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('reactionsgiven', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_field('reactionsreceived', XMLDB_TYPE_INTEGER, '10', null, false);
            $aggtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $aggtable->add_index('schedule_idx', XMLDB_INDEX_NOTUNIQUE, ['schedule']);
            $dbman->create_table($aggtable);
        }

        upgrade_plugin_savepoint(true, 2026063002, 'report', 'discoursestats');
    }

    if ($oldversion < 2026063006) {
        $renames = [
            'discoursestats_schedules'         => 'report_discoursestats_schedules',
            'discoursestats_results'           => 'report_discoursestats_results',
            'discoursestats_grading_log'       => 'report_discoursestats_grading_log',
            'discoursestats_aggregate_results' => 'report_discoursestats_aggregate_results',
        ];
        foreach ($renames as $oldname => $newname) {
            $oldtable = new xmldb_table($oldname);
            if ($dbman->table_exists($oldtable)) {
                $dbman->rename_table($oldtable, $newname);
            }
        }
        upgrade_plugin_savepoint(true, 2026063006, 'report', 'discoursestats');
    }

    if ($oldversion < 2026063007) {
        // Security and quality fixes (no schema changes).
        upgrade_plugin_savepoint(true, 2026063007, 'report', 'discoursestats');
    }

    return true;
}
