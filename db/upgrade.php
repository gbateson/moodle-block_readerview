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
 * This file keeps track of upgrades to the readerview block
 *
 * Sometimes, changes between versions involve alterations to database structures
 * and other major things that may break installations.
 *
 * The upgrade function in this file will attempt to perform all the necessary
 * actions to upgrade your older installation to the current version.
 *
 * If there's something it cannot do itself, it will tell you what you need to do.
 *
 * The commands in here will all be database-neutral, using the methods of
 * database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @since 2.0
 * @package blocks
 * @copyright 2010 Jerome Mouneyrac
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 *
 * @param int $oldversion
 * @param object $block
 */
function xmldb_block_readerview_upgrade($oldversion, $block) {
    global $CFG, $DB;
    $result = true;

    $dbman = $DB->get_manager();

    // Moodle v2.3.0 release upgrade line
    // Put any upgrade step following this

    $newversion = 2012011912;
    if ($result && $oldversion < $newversion) {

        // rename readerview evaulations table
    	$tablename = 'readerview_evaluations';

        $oldnewtablenames = array('readerview_current_evaluation', 'readerview_current_eval');
        foreach ($oldnewtablenames as $oldnewtablename) {
            if ($dbman->table_exists($oldnewtablename)) {
                $table = new xmldb_table($oldnewtablename);
                if ($dbman->table_exists($tablename)) {
                    $dbman->drop_table($table);
                } else {
                    $dbman->rename_table($table, $tablename);
                }
            }
        }

        // rename readerview evaulations fields
        $table = new xmldb_table($tablename);
        $fields = array(
            // $newfieldname => $field (with old name)
            'bookid' => new xmldb_field('pid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id'),
            'evalcount' => new xmldb_field('eval_no', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'pid'),
            'evaltotal' => new xmldb_field('total_eval', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'eval_no'),
            'evalaverage' => new xmldb_field('avg_eval', XMLDB_TYPE_NUMBER, '6, 1', null, XMLDB_NOTNULL, null, '0', 'total_eval')
        );

        foreach ($fields as $newfieldname => $field) {
            if ($dbman->field_exists($table, $field)) {
                xmldb_readerview_fix_previous_field($dbman, $table, $field);

                $oldfieldname = $field->getName();
                $DB->set_field_select($tablename, $oldfieldname, '', "$oldfieldname IS NULL");

                $dbman->change_field_type($table, $field);
                if ($oldfieldname != $newfieldname) {
                    $dbman->rename_field($table, $field, $newfieldname);
                }
            }
        }

        upgrade_block_savepoint($result, "$newversion", 'readerview');
    }

    $newversion = 2012011913;
    if ($result && $oldversion < $newversion) {
        update_capabilities('block/readerview');
        upgrade_block_savepoint($result, "$newversion", 'readerview');
    }

    return $result;
}

/**
 * xmldb_reader_fix_previous_field
 *
 * @param xxx $dbman
 * @param xmldb_table $table
 * @param xmldb_field $field (passed by reference)
 * @return void, but may update $field->previous
 */
function xmldb_readerview_fix_previous_field($dbman, $table, &$field) {
    $previous = $field->getPrevious();
    if (empty($previous) || $dbman->field_exists($table, $previous)) {
        // $previous field exists - do nothing
    } else {
        // $previous field does not exist, so remove it
        $field->setPrevious(null);
    }
}
