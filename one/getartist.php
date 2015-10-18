<?php
require_once '../DataInFile.php';

// $alreadyIsset = scandir('../output');

// $alreadyIsset = scandir('../output');
// unset($alreadyIsset[0]);
// unset($alreadyIsset[1]);
// $alreadyIsset = array_map(function($i) {return str_replace('.csv', '', $i); }, $alreadyIsset);

$DataInFile = new DataInFile('../conf', 'saved_list');
$alreadyIsset = $DataInFile->getSavedAsArray();
$artists = $DataInFile->initPath('../conf/input', 'in')->getSavedAsArray();
 
// $artists = include('../conf/input/artists.php');
// exit;
// $alreadyIsset = array_map(function($i) {return str_replace("\n", '', $i); }, $alreadyIsset);

$_out = array();

// $a = $alreadyIsset[0];
// $b = $artists[0];
// var_dump($a);
// var_dump($b);
// var_dump($a == $b);
// exit;

$is = array_flip($alreadyIsset);

foreach($artists as $artist) {
	if(!isset($is[$artist])) {
		$_out[] = $artist;
	}

    // if(!in_array($artist, $is)) {
    //     $_out[] = $artist;
    // }
}
asort($_out);
$out = isset($_out[0]) ? $_out[0] : '';
echo json_encode(array('for_url' => urlencode($out), 'origin' => $out));
