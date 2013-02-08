<?php

$genresarray = array(
    'all' => "All Genres",
    'ad' => "Adventure",
    'bi' => "Biography",
    'cl' => "Classics",
    'ch' => "Children's literature",
    'co' => "Comedy",
    'cu' => "Culture",
    'ge' => "Geography/Environment",
    'ho' => "Horror",
    'hi' => "Historical",
    'hu' => "Human interest",
    'li' => "Literature in Translation",
    'mo' => "Movies",
    'mu' => "Murder Mystery",
    'ro' => "Romance",
    'sc' => "Science fiction",
    'sh' => "Short stories",
    'te' => "Technology & Science",
    'th' => "Thriller",
    'ch' => "Children's literature",
    'yo' => "Young life, adventure"
);

$fictionarray = array(
    'f' => "fiction",
    'n' => "non-fiction"
);

$avaiablelang = array(
    'en' => "English",
    'jp' => "Japanese",
    'ko' => "Korean"
);

$ratearray = array(
    '5' => get_string('bestbook',"block_readerview"),
    '4' => get_string('interestingbook',"block_readerview"),
    '3' => get_string('alright',"block_readerview"),
    '2' => get_string('littleboring',"block_readerview"),
    '1' => get_string('avoidbook',"block_readerview")
);

function readerview_postrequest($url, $post) {
    $postdata = "";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, 1);

        foreach ($post as $key => $value) {
          if (! is_array($value)) {
              $postdata .= $key.'='.urlencode($value).'&';
          } else {
            foreach ($value as $key2 => $value2) {
                if (! is_array($value2)) {
                    $postdata .= $key.'['.$key2.']='.$value2.'&';
                } else {
                    foreach ($value2 as $key3 => $value3) {
                        $postdata .= $key.'['.$key2.']['.$key3.']='.$value3.'&';
                    }
                }
            }
          }
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    }
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

function readerview_sort_table_data ($data, $titlesarray, $orderby, $sort) {

    global $USER, $CFG;

    $j = 0;
    if ($sort) {
        foreach ($titlesarray as $titlesarray_) {
            if ($titlesarray_ == $sort) {
                $orderkey = $j;
            }
            $j++;
        }
    } else {
        $orderkey = 0;
    }

    $i = 0;

    foreach ($data as $datakey => $datavalue) {
        if (! is_array($datavalue[$orderkey])) {
            $key = $datavalue[$orderkey];
        } else {
            $key = $datavalue[$orderkey][1];
        }

        for ($j=0; $j < count($datavalue); $j++) {
            if (! is_array($datavalue[$j])) {
                $newarray[(string)$key][$i][$j] = $datavalue[$j];
            } else {
                $newarray[(string)$key][$i][$j] = $datavalue[$j][0];
            }
        }

        $i ++;
    }

    if (empty($orderby) || $orderby == "ASC") {
        ksort ($newarray);
    } else {
        krsort ($newarray);
    }

    reset($newarray);

    foreach ($newarray as $newarray_) {
        foreach ($newarray_ as $newarray__) {
            $newarraynew = array();
            foreach ($newarray__ as $newarray___) {
                $newarraynew[] = $newarray___;
            }
            $finaldata[] = $newarraynew;
        }
    }

    return $finaldata;
}
