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
 * Scheduled task for collecting evaluations on Reader books.
 *
 * @package   block_readerview
 * @author    Gordon Bateson 2023
 * @copyright Gordon Bateson 2023
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_readerview\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task for collecting evaluations on Reader books.
 *
 * @package   block_readerview
 * @author    Gordon Bateson 2023
 * @copyright Gordon Bateson 2023
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluations extends \core\task\scheduled_task {

    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('evaluationstask', 'block_readerview');
    }

    /**
     * Remove old entries from table block_readerview
     */
    public function execute() {
        global $DB;

        mtrace( 'Updating reader block evaluations ... ');

        $select = 'rb.id, rb.quizid, '.
                  'SUM(ra.bookrating) AS bookratingsum, '.
                  'COUNT(ra.bookrating) AS bookratingcount';
        $from   = '{reader_books} rb '.
                  'RIGHT JOIN {reader_attempts} ra ON rb.quizid = ra.quizid';
        $where  = 'hidden = ?'; // i.e. book is visible
        $params = array(0);

        $sql = "SELECT $select FROM $from WHERE $where GROUP BY rb.id";

        $updates = 0;
        if ($books = $DB->get_records_sql($sql, $params)) {
            foreach ($books as $book) {
                if ($eval = $DB->get_record('readerview_evaluations', array('bookid' => $book->id))) {
                    $update = false;
                } else {
                    $eval = new stdClass();
                    $eval->evalaverage = 0;
                    $eval->bookid = $book->id;
                    $update = true;
                }
                $eval->evalcount = $book->bookratingcount;
                $eval->evaltotal = ($book->bookratingsum * 10 / 3);
                if ($eval->evalcount == 0 || $eval->evaltotal == 0) {
                    $evalaverage = 0;
                } else {
                    $evalaverage = round($eval->evalcount / $eval->evaltotal, 1);
                }
                if ($eval->evalaverage != $evalaverage) {
                    $eval->evalaverage = $evalaverage;
                    $update = true;
                }
                if ($update) {
                    if (isset($eval->id)) {
                        $DB->update_record('readerview_evaluations', $eval);
                    } else {
                        $DB->insert_record('readerview_evaluations', $eval);
                    }
                    $updates ++;
                }
            }
        }
        
        mtrace("... $updates evaluations were updated");

        // No return value is required.
    }
}
