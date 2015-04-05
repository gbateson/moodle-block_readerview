<?php
    require_once('../../config.php');
    require_once($CFG->dirroot.'/mod/reader/lib.php');

    $id             = optional_param('id', 0, PARAM_INT);
    $genre          = optional_param('genre', null, PARAM_CLEAN);
    $fiction        = optional_param('fiction', null, PARAM_CLEAN);
    $publisher      = optional_param('publisher', null, PARAM_CLEAN);
    $coverimages    = optional_param('coverimages', null, PARAM_CLEAN);
    $publisherlevel = optional_param('publisherlevel', null, PARAM_CLEAN);
    $mylevel        = optional_param('mylevel',        null, PARAM_CLEAN);
    $order          = optional_param('order',          null, PARAM_CLEAN);
    $getscript      = optional_param('getscript',      null, PARAM_CLEAN);
    $instanceid     = optional_param('instanceid',     null, PARAM_INT);

    if (! $course = $DB->get_record('course', array('id' => $id))) {
        error('Course id is not valid');
    }

    // Note: there may be more than one reader in a course
    if ($reader = $DB->get_records('reader', array('course' => $course->id))) {
        $reader = reset($reader);
    } else {
        // could display a message here: 'No reader activity has been setup in this course'
        $reader = (object)array('id' => 0, 'bookinstances' => 0);
    }

    $instance = $DB->get_record('block_instances', array('id' => $instanceid));
    if (empty($instance->configdata)) {
        $context  = (object)array('uselanguages' => '', 'allowquiztaken' => 'yes');
    } else {
        $context  = unserialize(base64_decode($instance->configdata));
    }

    require_login($course->id);

    if (function_exists('get_log_manager')) {
        $manager = get_log_manager();
        $manager->legacy_add_to_log($course->id, 'reader', 'reader block view books', 'selectbook.php?id='.$id, $id);
    } else if (function_exists('add_to_log')) {
        add_to_log($course->id, 'reader', 'reader block view books', 'selectbook.php?id='.$id, $id);
    }

    $script_txt = $CFG->dataroot.'/reader/script.txt';
    $old_script_txt = $CFG->dirroot.'/blocks/readerview/script.txt';

    $book_instancesarr = array();

    $genresarray = array(
        'all' => 'All Genres',
        'ad'  => 'Adventure',
        'bi'  => 'Biography',
        'cl'  => 'Classics',
        'ch'  => "Children's literature",
        'co'  => 'Comedy',
        'cu'  => 'Culture',
        'ge'  => 'Geography/Environment',
        'ho'  => 'Horror',
        'hi'  => 'Historical',
        'hu'  => 'Human interest',
        'li'  => 'Literature in Translation',
        'mo'  => 'Movies',
        'mu'  => 'Murder Mystery',
        'ro'  => 'Romance',
        'sc'  => 'Science fiction',
        'sh'  => 'Short stories',
        'te'  => 'Technology & Science',
        'th'  => 'Thriller',
        'ch'  => "Children's literature",
        'yo'  => 'Young life, adventure'
    );

    if ($genre) { // form data was submitted

        if ($fiction=='fn') {
            $fictionsql = '';
        } else {
            $fictionsql = " AND rb.fiction = '$fiction' ";
        }

        if ($publisher == 'all') {
            $publishersql = '';
        } else {
            $publishersql = " AND rb.publisher = '$publisher' ";
        }

        $publisherlevelsql = '';

        if ($mylevel == 'my') {
            $books = $DB->get_record('reader_levels', array('userid' => $USER->id));
            $userlevelssql = ($books->currentlevel - 1).','.$books->currentlevel.','.($books->currentlevel + 1);
            $publisherlevelsql = " AND rb.difficulty IN({$userlevelssql}) ";
        } else if (is_numeric($mylevel)) {
            $publisherlevelsql = " AND rb.difficulty='{$mylevel}' ";
        }

        if (! $allcourses = $DB->get_records_sql("SELECT id FROM {course}")) {
            $allcourses = array();
        }

        $allcount = -1;
        $alllimit = 6;

        if ($genre == 'all') {
            $genresql = "rb.genre LIKE '%%'";
        } else {
            $genresql = "(rb.genre='{$genre}' OR rb.genre LIKE '{$genre},%' OR rb.genre LIKE '%,{$genre},%' OR rb.genre LIKE '%,{$genre}')";
        }

        if ($order == 'rb.name' || $order == 'rb.difficulty') {
            $sort = 'ASC';
        } else {
            $sort = '';
        }

        if (empty($reader->bookinstances)) {
            $select = 'rb.id, rb.name, rb.publisher, rb.image, rb.difficulty, rb.words, rb.genre, '.
                      're.evalcount, re.evaltotal, re.evalaverage';
            $from   = '{reader_books} rb '.
                      'LEFT JOIN {readerview_evaluations} re ON re.bookid = rb.id';
            $where  = 'rb.private = 0 AND rb.hidden != 1 '.
                      "AND {$genresql} {$fictionsql} {$publishersql} {$publisherlevelsql}";
            if (! $books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order $sort")) {
                $books = array();
            }
        } else {
            $select = 'rb.id, rb.name, rb.publisher, rb.image, rb.difficulty, rb.words, rb.genre, '.
                      're.evalcount, re.evaltotal, re.evalaverage';
            $from   = '{reader_book_instances} rbi'.
                      'LEFT JOIN {reader_books} rb ON rbi.bookid=rb.id'.
                      'LEFT JOIN {readerview_evaluations} re ON rb.id = re.bookid';
            $where  = 'rbi.reader = :readerid '.
                      'AND rb.private = 0 AND rb.hidden != 1 '.
                      "AND {$genresql} {$fictionsql} {$publishersql} {$publisherlevelsql}";
            $params = array('readerid' => $reader->id);
            if (! $books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order $sort")) {
                $books = array();
            }
        }

        $table = new html_table();
        $table->head  = array('Title', 'Publisher', 'Genre', 'Level', 'Words', 'Evaluation');
        $table->align = array('left', 'left', 'left', 'center', 'center', 'center');
        $table->width = '95%';

        if (empty($getscript)) {
            echo '<table><tr>';
        }

        if (! empty($books)) {
            $bookimages = array();
            foreach ($books as $bookid => $book) {
                $allcount++;
                if ($allcount >= $alllimit) {
                    if ($coverimages==1 && empty($getscript)) {
                        echo '</tr><tr>';
                    }
                    $allcount = 0;
                }

                if ($coverimages==1 && empty($getscript)) {
                    echo '<td width="180px" valign="top">';
                }
                if (empty($getscript)) {
                    echo '<script type="text/javascript" charset="utf-8">'."\n";
                    echo "function showEval (id) {\n";
                    echo '    $.modal('."'".'<div id="showevaldata"><img width="16" height="16" src="'.$CFG->wwwroot.'/blocks/readerview/img/zoomloader.gif" alt="" /></div>'."');\n";
                    echo '    $('."'#showevaldata').load('selector_bookinfo.php?id=' + id);\n";
                    echo "}\n";
                    echo '</script>';
                }

                if ($coverimages==1) {
                    if (is_file("{$CFG->dataroot}/reader/images/{$book->image}")) {
                        if ($CFG->slasharguments) {
                            $bookimage = new moodle_url('/mod/reader/images.php/reader/images/'.$book->image);
                        } else {
                            $params = array('file' => '/reader/images/'.$book->image);
                            $bookimage = new moodle_url('/mod/reader/images.php', $params);
                        }
                        $bookimages[$bookid] = $bookimage;
                        if (empty($getscript)) {
                            echo '<div>';

                            //if ($book->evalaverage > 0) {
                            //    echo '<a href="#" onclick="showEval('.$book->id.');return false;">';
                            //}

                            echo '<img src="'.$CFG->wwwroot.'/blocks/readerview/img/loading-image.png" border="0" alt="'.$book->name.'" height="150" width="100" id="bookavatar-'.$book->id.'" style="background: none repeat scroll 0 0 ';
                            // echo $book->evalaverage > 0 ? '#F08080' : '#F2E9E2';
                            echo '; border: 1px solid #DDDDDD; display:inline; margin: 0 10px 10px 0; padding: 5px;" />';

                            //if ($book->evalaverage > 0) {
                            //    echo '</a>';
                            //}

                            echo '</div>';
                        }
                    }
                }

                if ($book->evalaverage == 0 || $book->evalcount <= 3) {
                    $book->evalaverage = '--';
                }

                if ($coverimages==1) {
                    if (empty($getscript)) {
                        echo '<div><strong>'.$book->name.'</strong></div>';
                        echo '<div style="float:left; margin-right:10px;">RL: '.$book->difficulty.'</div>';
                        if ($context->allowquiztaken == 'yes' && $book->evalaverage != '--') {
                            echo '<div> eval: '.$book->evalaverage.' ('.$book->evalcount.')</div>';
                        }
                        echo '<div style="clear:both;"></div>';
                    }

                    //if ($genre == 'all') {

                    if (empty($getscript)) {
                        if (! strstr($book->genre, ',')) {
                            if (isset($genresarray[$book->genre])) {
                                echo '<div>'.$genresarray[$book->genre].'</div>';
                            }
                        }
                    }

                    if (strstr($book->genre, ',')) {
                        if (empty($getscript)) {
                            echo '<div>';
                        }
                        $strgenere = array();
                        $generes = explode(',', $book->genre);
                        foreach ($generes as $genere) {
                            if (array_key_exists($genere, $genresarray)) {
                                $strgenere[] = $genresarray[$genere];
                            }
                        }
                        $strgenere = array_filter($strgenere); // remove blanks
                        $strgenere = implode(', ', $strgenere); // convert to string

                        if (empty($getscript)) {
                            echo $strgenere;
                            echo '</div>';
                        }
                    }
                    //}
                    if (empty($getscript)) {
                        echo '</td>';
                    }
                } else {
                    $generetext = array();

                    $generes = explode(',', $book->genre);
                    foreach ($generes as $genere) {
                        if (array_key_exists($genere, $genresarray)) {
                            $generetext[] = $genresarray[$genere];
                        }
                    }
                    $generetext = array_filter($generetext); // remove blanks
                    $generetext = implode(', ', $generetext); // convert to string

                    if ($context->allowquiztaken == 'yes' && $book->evalaverage != '--') {
                        $evaltext = "$book->evalaverage ($book->evalcount)";
                    } else {
                        $evaltext = '';
                    }

                    $row = new html_table_row();
                    $row->cells[] = new html_table_cell($book->name);
                    $row->cells[] = new html_table_cell($book->publisher);
                    $row->cells[] = new html_table_cell($generetext);
                    $row->cells[] = new html_table_cell($book->difficulty);
                    $row->cells[] = new html_table_cell($book->words);
                    $row->cells[] = new html_table_cell($evaltext);
                    $table->data[] = $row;
                }
            }
        } else {
            if (empty($getscript)) {
                echo '<td>No books</td>';
            }
        }

        if (empty($getscript)) {
            if (! empty($table->data)) {
                echo html_writer::table($table);
            }
        }

        if (! isset($bookimages)) {
            $bookimages = array();
        }

        $tm = 0;
        $script = '';
        foreach ($bookimages as $bookid => $bookimage) {
            $tm = $tm + 170;
            $script .= 'setTimeout('."'".'$("#bookavatar-'.$bookid.'").attr("src","'.$bookimage.'");'."', $tm);\n";
        }

        $fp = false;

        make_upload_directory('reader');
        if (file_exists($script_txt)) {
            if (is_writable($script_txt)) {
                $fp = @fopen($script_txt, 'w+');
            }
        } else {
            if (is_writable(dirname($script_txt))) {
                $fp = @fopen($script_txt, 'w');
            }
        }

        if ($fp) {
            fwrite($fp, $script);
            fclose($fp);
        } else {
            $link = $CFG->wwwroot.'/course/view.php?id='.$id;
            print_error('cannotopenforwrit', 'error', $link, $script_txt);
        }

        die;
    }

    $PAGE->set_url('/blocks/readerview/selectbook.php', array('id' => $id, 'instanceid' => $instanceid));

    $title = $course->shortname . ': View Books';
    $PAGE->set_title($title);
    $PAGE->set_heading($course->fullname);

    if ($CFG->slasharguments) {
        $images_php = 'images.php';
    } else {
        $images_php = 'images.php?file=';
    }

    echo $OUTPUT->header();

    echo '<script type="text/javascript" src="'.$CFG->wwwroot.'/blocks/readerview/js/jquery-1.4.1.min.js"></script>  ';
    echo '<script type="text/javascript" src="'.$CFG->wwwroot.'/blocks/readerview/js/jquery.form.js"></script>  ';
    echo '<script type="text/javascript" src="'.$CFG->wwwroot.'/blocks/readerview/js/jquery.simplemodal-1.3.3.min.js"></script>  ';

    echo '<script type="text/javascript">'."\n";
    echo '$(document).ready(function() {'."\n";
    echo '    $("#searchform").ajaxForm({beforeSubmit: showRequest, target: "#searchresult", success: showScript});'."\n";
    echo '    function showRequest () {'."\n";
    echo '        $("#searchresult").html('."'".'<img width="16" height="16" src="'.$CFG->wwwroot.'/blocks/readerview/img/zoomloader.gif" alt="" />'."'".');'."\n";
    echo '    }'."\n";
    echo '    function showScript () {'."\n";
    echo '        $.post("'.$CFG->wwwroot.'/mod/reader/'.$images_php.'/reader/script.txt", function(data) {'."\n";
    echo '            eval(data);'."\n";
    echo '        });'."\n";
    echo '    }'."\n";
    echo '    function showEval (id) {'."\n";
    echo '        $.modal('."'".'<div id="showevaldata"><img width="16" height="16" src="'.$CFG->wwwroot.'/blocks/readerview/img/zoomloader.gif" alt="" /></div>'."'".');'."\n";
    echo '        $("#showevaldata").load("selector_bookinfo.php?id=" + id);'."\n";
    echo '    }'."\n";
    echo '});'."\n";
    echo '</script>';

    echo '<style media="screen" type="text/css">'."\n";
    echo '#simplemodal-container {'."\n";
    echo '    background-color: #ffffff;'."\n";
    echo '    border      : 4px solid #444444;'."\n";
    echo '    height      : 450px;'."\n";
    echo '    padding     : 12px;'."\n";
    echo '    width       : 600px;'."\n";
    echo '}'."\n";
    echo '#simplemodal-overlay {'."\n";
    echo '    background-color: #000000;'."\n";
    echo '    cursor      : wait;'."\n";
    echo '}'."\n";
    echo 'a.modalCloseImg {'."\n";
    echo '    background : url('.$CFG->wwwroot.'/blocks/readerview/img/x.png) no-repeat; /* adjust url as required */'."\n";
    echo '    width      : 25px;'."\n";
    echo '    height     : 29px;'."\n";
    echo '    display    : inline;'."\n";
    echo '    z-index    : 3200;'."\n";
    echo '    position   : absolute;'."\n";
    echo '    top        : -15px;'."\n";
    echo '    right      : -18px;'."\n";
    echo '    cursor     : pointer;'."\n";
    echo '}'."\n";
    echo '</style>';

    echo '<!--[if lt IE 7]>'."\n";
    echo '<style type="text/css">'."\n";
    echo 'a.modalCloseImg {'."\n";
    echo '    background : none;'."\n";
    echo '    right      : -14px;'."\n";
    echo '    width      : 22px;'."\n";
    echo '    height     : 26px;'."\n";
    echo '    filter     : progid:DXImageTransform.Microsoft.AlphaImageLoader('."\n";
    echo '        src="'.$CFG->wwwroot.'/blocks/readerview/img/x.png", sizingMethod="scale"'."\n";
    echo '    );'."\n";
    echo '}'."\n";
    echo '</style>'."\n";
    echo '<![endif]-->'."\n";

    echo $OUTPUT->box_start('generalbox');

    echo '<h1>'.get_string('viewbooks', 'block_readerview').'</h1>';

    echo '<form action="selectbook.php?id='.$id.'&instanceid='.$instanceid.'" method="post" id="searchform">';
    echo '<div>'; // start top row

    // =============================
    // genre
    // =============================
    //
    echo get_string('genre', 'block_readerview').' ';
    echo '<select name="genre">';
    foreach ($genresarray as $bookid => $value) {
        echo '<option value="'.$bookid.'">'.s($value).'</option>';
    }
    echo '</select> ';

    // =============================
    // publishers
    // =============================
    //
    echo get_string('publisher', 'block_readerview').' ';
    echo '<select name="publisher">';
    echo '<option value="all">'.get_string('all').'</option>';
    $select = 'rb.publisher, COUNT(*) AS countbooks';
    if (empty($reader->bookinstances)) {
        $from   = '{reader_books} rb';
        $where  = 'rb.hidden = ? AND rb.publisher <> ?';
        $params = array(0, 'Extra Points');
    } else {
        $from   = '{reader_books} rb JOIN {reader_book_instances} ri ON ri.bookid = rb.id';
        $where  = 'ri.readerid = ? AND rb.hidden = ? AND rb.publisher <> ?';
        $params = array($reader->id, 0, 'Extra Points');
    }
    $where .= ' GROUP BY rb.publisher';
    $where .= ' ORDER BY rb.publisher';
    if ($publishers = $DB->get_records_sql_menu("SELECT $select FROM $from WHERE $where", $params)) {
        foreach ($publishers as $publisher => $countbooks) {
            echo '<option value="'.s($publisher).'">'.s($publisher)." ($countbooks books)</option>";
        }
    }
    echo '</select>';

    // =============================
    // fiction / non-fiction
    // =============================
    //
    echo ' '.get_string('fiction', 'block_readerview').' ';
    echo '<select name="fiction"><option value="fn">All</option>';
    echo '<option value="f">Fiction</option>';
    echo '<option value="n">Non-fiction</option>';
    echo '</select>';

    // =============================
    // book covers / text
    // =============================
    //
    echo ' '.get_string('showcoverimages', 'block_readerview').' ';
    echo '<select name="coverimages">';
    echo '<option value="1">Images</option>';
    echo '<option value="0">Words</option>';
    echo '</select>';

    echo '</div>'; // finish top row

    // =============================
    // reading levels
    // =============================
    //
    echo '<div style="padding-top: 20px; float: left;margin-right: 40px;margin-left: 40px;">';
    echo ' '.get_string('readinglevel', 'block_readerview').' <select name="mylevel">';
    echo '<option value="all">'.get_string('alllevels', 'block_readerview').'</option>';
    echo '<option value="my">'.get_string('mylevel', 'block_readerview').'</option>';
    foreach (range(0, 14) as $value) {
        echo '<option value="'.$value.'">'.$value.'</option>';
    }
    echo '</select>';
    echo '</div>';

    // =============================
    // order by
    // =============================
    //
    echo '<div style="padding-top: 20px; margin-left: 40px; float:left;margin-right:40px;">';
    echo get_string('orderby', 'block_readerview').' ';
    echo '<select name="order">';
    echo '<option value="rb.name">'.get_string('alphabetically', 'block_readerview').'</option>';
    echo '<option value="rb.difficulty">'.get_string('bylevel', 'block_readerview').'</option>';
    echo '</select>';
    echo '</div>';

    // =============================
    // search button
    // =============================
    //
    echo '<div style="padding-top:10px;">';
    echo '<input type="submit" name="sub" value="'.get_string('search', 'block_readerview').'" style="font-size: 14pt;">';
    echo '</div>';

    echo '</form>';

    echo '<div style="clear:both;"></div>';
    echo '<div id="searchresult"></div>';
    echo '<div id="showscript"></div>';

    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
