<?php

include_once('../../config.php');
require_once('lib.php');

$select = 'id,name,publisher,image,difficulty,genre';
$from   = '{reader_books}';
$where  = 'hidden != ?';
$params = array(1);

if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY id", $params)) {
	foreach ($books as $book) {

		$evalcount   = 0; // count of number of evaluations
		$evaltotal   = 0; // sum total of evaluation ratings
		$evalaverage = 0; // average of evaluations

		if ($attempts = $DB->get_records('reader_attempts', array('quizid' => $book->id))) {
			foreach($attempts as $attempt) {
				if ($attempt->bookrating) {
					$evalcount++;
					$evaltotal += $attempt->bookrating * 3.33;
				}
			}
		}

		if ($evaltotal && $evalcount) {
			$evalaverage = round($evaltotal / $evalcount, 1);
		}

		if (! $eval = $DB->get_record('readerview_evaluations', array('bookid' => $book->id))) {
			$eval = new stdClass();
			$eval->bookid = $book->id;
		}
		$eval->evalcount   = $evalcount;
		$eval->evaltotal   = $evaltotal;
		$eval->evalaverage = $evalaverage;

		if (isset($eval->id)) {
			$DB->update_record('readerview_evaluations', $eval);
		} else {
			$DB->insert_record('readerview_evaluations', $eval);
		}
	}
}

echo 'Done'
