<?php
$is = scandir('output');
unset($is[0]);
unset($is[1]);
$is = array_map(function($i) {return str_replace('.csv', '', $i); }, $is);
$artists = include('input/artists.php');
$end = array();
foreach($artists as $artist) {
    if(!in_array($artist, $is)) {
        $end[] = $artist;
    }
}
asort($end);
echo count($end); ?><br />
<pre><?php print_r($end); ?></pre>
