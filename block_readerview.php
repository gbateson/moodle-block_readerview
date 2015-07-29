<?php

class block_readerview extends block_base {

    function init() {
        $this->title = get_string('title', 'block_readerview');
        $this->version = 2010021005;
        $this->cron = 86400;
    }

    function get_content() {
        if ($this->content===null) {
            $params = array('id' => $this->page->course->id, 'instance' => $this->instance->id);
            $url = new moodle_url('/blocks/readerview/selectbook.php', $params);

            $params = array('href' => $url, 'style' => 'font-size: 16px; margin-left: 14px;');
            $this->content = (object)array(
                'text' => html_writer::tag('a', get_string('selectbook','block_readerview'), $params),
                'footer' => ''
            );
        }
        return $this->content;
    }

    function instance_allow_multiple() {
        return false;
    }

    function instance_config_save($data, $nolongerused=false) {
        // Clean the data if we have to
        if (empty($data->uselanguages)) {
            $data->uselanguages = '';
        } else if (is_array($data->uselanguages)) {
            // remove blanks and convert to string
            $data->uselanguages = array_filter($data->uselanguages);
            $data->uselanguages = implode(',', $data->uselanguages);
        }

        return parent::instance_config_save($data, $nolongerused);
    }

    function has_config() {
      return false;
    }

    function instance_allow_config() {
      return true;
    }

    function applicable_formats() {
        return array('course' => true);
    }

    function cron(){
        global $DB;

        mtrace( 'Updating reader block evaluations ...');

        $select = 'rb.id, rb.quizid, '.
                  'SUM(ra.bookrating) AS bookratingsum, '.
                  'COUNT(ra.bookrating) AS bookratingcount';
        $from   = '{reader_books} rb RIGHT JOIN {reader_attempts} ra ON rb.quizid = ra.quizid';
        $where  = 'hidden = ?'; // i.e. book is visible
        $params = array(0);

        if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY rb.id", $params)) {
            foreach ($books as $book) {
                if (! $eval = $DB->get_record('readerview_evaluations', array('bookid' => $book->id))) {
                    $eval = new stdClass();
                    $eval->bookid = $book->id;
                }

                $eval->evalcount   = $book->bookratingcount;
                $eval->evaltotal   = $book->bookratingsum * 10 / 3;
                if ($eval->evalcount==0 || $eval->evaltotal==0) {
                    $eval->evalaverage = 0;
                } else {
                    $eval->evalaverage = round($eval->evalcount / $eval->evaltotal, 1);
                }

                if (isset($eval->id)) {
                    $DB->update_record('readerview_evaluations', $eval);
                } else {
                    $DB->insert_record('readerview_evaluations', $eval);
                }
            }
        }

        mtrace('Done');
        return true;
    }
}
