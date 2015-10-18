<?php
set_time_limit(86400);
ini_set('memory_limit', '3G');
require_once '../SimpleHtml/main.php';
require_once '../utils/Helper.php';
require_once '../utils/TimeHelper.php';
require_once '../DataInFile.php';

/**
 * Class AllMusicParser
 */
class AllMusicParser
{
    const DS = DIRECTORY_SEPARATOR;
    public $artistsNames = array();
    public $discographyTypes = array('', 'compilations', 'singles', /*'video', 'others'*/);
    public $outputDir = '../output';
    public $inputDir = '../input';
    public $inputDataFile = 'artists.php';
    protected $simpleHmtl;
    private $baseUrl = 'http://www.allmusic.com';
    private $searchUrl = 'http://www.allmusic.com/search/all/';

    private $dataInFile;
    private $confDir = '../conf';
    private $savedList = 'saved_list';
    private $listToSave = 'list_to_save';

    public function __construct($dir = null) {
        $this->init($dir);
    }

    private function init($dir) {
        $this->setOutputDir($dir);
        $this->simpleHmtl = SimpleHtmlDom::getInstance();
        $this->dataInFile = new DataInFile();
        $this->artistsNames = $this->getArtistsNames();
    }

    /**
     * @param null $dir
     */
    public function setOutputDir($dir = null) {
        $t = date('Ymd_His');
        $this->outputDir = is_null($dir) ? $this->outputDir . self::DS . $t : $dir;
        $pathDir = __DIR__ . self::DS . $this->outputDir;
        if (!is_dir($pathDir)) {
            mkdir($pathDir, 650, true);
        }
    }

    /**
     * @return mixed
     */
    function getArtistsNames() {
        // return include $this->inputDir.self::DS.$this->inputDataFile;
        $out = $this->dataInFile($this->confDir, $this->listToSave)->getSavedAsArray();
        return $out;
    }

    /**
     * @param null $dir
     * @return array
     */
    function getArtistNamesFromFileNames($dir = null) {
        $dir = is_null($dir) ? $this->outputDir : $dir;
        $out = array();
        $files = scandir($dir);
        if (!empty($files)) {
            unset($files[0]);
            unset($files[1]);
            foreach ($files as $f_name) {
                if (stripos($f_name, 'csv') !== false) {
                    $f_from_file = substr($f_name, 0, strlen($f_name) - 4);
                    $out[] = $f_from_file;
                }
            }
        }
        return $out;
    }

    /**
     * @param $artist_name
     * @return array
     * @throws Exception
     */
    function getArtistInfo($artist_name) {
        $types = $this->discographyTypes;
        $artistInfo = array();
        $albumsAndTracks = array();
        $artistPageLink = $this->search($artist_name);
        // $csv = 'test';
        // $this->dataInFile->save(trim($artist_name));
        // file_put_contents($this->outputDir.self::DS.$f_csv, $csv);
        // return $out;
        if (isset($artistPageLink[0])) {
            $artistPageLink = $artistPageLink[0]; // get the first artist in search result
            foreach ($types as $type) {
                $tmp = $this->getAlbums($artistPageLink . '/discography', $type);
                if ($tmp) {
                    foreach ($tmp as $k => $album) {
                        $tracks = $this->getTracks($album['album_link']);
                        $tmp[$k]['tracks'] = $tracks;
                        $tmp[$k]['artist_name'] = $artist_name;
                    }
                    $albumsAndTracks[] = $tmp;
                }
            }
        }
        if ($albumsAndTracks) {
            $artistInfo = $this->formatOutput($albumsAndTracks);
        }
        $csv = Helper::getCsvFromArray($artistInfo);
        return array('info' => $artistInfo, 'csv' => $csv, 'name' => $artist_name);
        // $this->dataInFile->save($artist_name);
        // file_put_contents($this->outputDir.self::DS.$f_csv, $csv);
        // return $out;
    }

    /**
     * @param $searchQuery
     * @return array
     */
    public function search($searchQuery) {
        $hrefs = array();
        if ($searchQuery) {
            $url = $this->searchUrl . urlencode($searchQuery);
            $data = $this->simpleHmtl->getHtml($url);
            if ($data->innertext != '') {
                foreach ($data->find('.artist .name') as $el) {
                    $searchQuery = $el->outertext();
                    $searchQuery = str_replace('&quot;', "&#34;", $searchQuery);
                    $values = Helper::getXmlStruct($searchQuery);
                    foreach ($values as $val) {
                        if (strtolower($val['tag']) == 'a' && isset($val['attributes']['HREF'])) {
                            $hrefs[] = $val['attributes']['HREF'];
                            //$out = getAlbum($val['attributes']['HREF'].'/discography');
                        }
                    }
                }
            }
        }
        return $hrefs;
    }

    /**
     * @param $url
     * @param string $discography_type
     * @return array
     */
    public function getAlbums($url, $discography_type = '') {
        $out = array();
        $data = $this->simpleHmtl->getHtml($url . '/' . $discography_type);
        $base_url = $this->baseUrl;
        if ($data->innertext != '') {
            $year = $album_name = $release_date = $label = $album_link = $img = null;
            foreach ($data->find('.discography table>tbody td') as $el) {
                $s = Helper::clearHtml($el->outertext());
                $values = Helper::getXmlStruct($s);
                foreach ($values as $val) {
                    if (strtolower($val['tag']) == 'td' && (isset($val['attributes']['CLASS']) && strtolower($val['attributes']['CLASS']) == 'year')) {
                        $year = $this->getElValue($val);
                    }
                    if (strtolower($val['tag']) == 'td' && $val['type'] == 'complete') {
                        if (isset($val['attributes']['CLASS']) && $val['attributes']['CLASS'] == 'year' && isset($val['attributes']['DATA-SORT-VALUE'])) {
                            $album_info = $val['attributes']['DATA-SORT-VALUE'];
                            $release_date = substr($album_info, 0, 10); // release_date
                            $album_name = substr($album_info, 11, strlen($album_info) - 11); // album_name
                        }
                    }
                    if (strtolower($val['tag']) == 'td' && (isset($val['attributes']['CLASS']) && strtolower($val['attributes']['CLASS']) == 'label')) {
                        $label = $this->getElValue($val); // label
                    }
                    if (strtolower($val['tag']) == 'img' && (isset($val['attributes']['DATA-ORIGINAL']))) {
                        $img = str_replace(array('JPG_75', '?partner=allrovi.com'), array('JPG_500', ''), $val['attributes']['DATA-ORIGINAL']); // img
                    }
                    if (strtolower($val['tag']) == 'a' && (isset($val['attributes']['HREF']) && stripos($val['attributes']['HREF'], 'album') !== false)) {
                        $href = $val['attributes']['HREF'];
                        if ($href[0] == '/') {
                            $href = $base_url . $href;
                        }
                        $album_link = $href; // album_link
                    }
                    $name = isset($val['attributes']) && isset($val['attributes']['CLASS']) ? $val['attributes']['CLASS'] : '';
                    $is_rating = preg_match('/(allmusic-rating rating-allmusic-)(\d)/i', $name, $t);
                    $is_unrated = stripos('allmusic-rating rating-unrated', $name) !== false;
                    if ($is_rating || $is_unrated) {
                        $rating = isset($t[2]) ? $t[2] : null;
                        $out[] = array('year' => $year, 'album_name' => $album_name, 'release_date' => $release_date, 'label' => $label, 'rating' => $rating, 'album_link' => $album_link, 'img' => $img, 'discography_type' => $discography_type ?: 'albums',);
                    }
                }
            }
        }
        return $out;
    }

    /**
     * @param $el
     * @return null|string
     */
    protected function getElValue($el) {
        $out = null;
        if (isset($el['value']) && $value = trim($el['value'])) {
            //$name = isset($el['attributes']) && isset($el['attributes']['CLASS']) ? $el['attributes']['CLASS'] : '';
            $out = $value;
        }
        return $out;
    }

    /**
     * @param $url
     * @return array
     */
    public function getTracks($url) {
        $out = array();
        if ($url) {
            //$url = 'http://www.allmusic.com/album/wednesday-morning-3-am-mw0000191731';
            $data = $this->simpleHmtl->getHtml($url);
            if ($data->innertext != '') {
                $tracknum = $duration = $track_link = $track_name = $primary = null;
                foreach ($data->find('.track-listing table td') as $el) {
                    $s = Helper::clearHtml($el->outertext());
                    $values = Helper::getXmlStruct($s);
                    $prev = array();
                    foreach ($values as $val) {
                        if (strtolower($val['tag']) == 'td' && (isset($val['attributes']['CLASS']) && $val['attributes']['CLASS'] == 'tracknum')) {
                            $tracknum = $this->getElValue($val); // порядковый номер в альбоме
                        }
                        if (strtolower($val['tag']) == 'a' && isset($val['attributes']['HREF']) && isset($val['attributes']['ITEMPROP'])) {
                            $href = $val['attributes']['HREF'];
                            $track_link = $href; // ссылка на трек
                            $track_name = $this->getElValue($val); // название
                        }
                        if ((isset($val['attributes']['CLASS']) && $val['attributes']['CLASS'] == 'primary')) {
                            $prev = $val;
                        }
                        if (!empty($prev) && strtolower($val['tag']) == 'a' && isset($val['attributes']['HREF'])) {
                            $primary = $this->getElValue($val);
                            $prev = array();
                        }
                        if (strtolower($val['tag']) == 'td' && (isset($val['attributes']['CLASS']) && $val['attributes']['CLASS'] == 'time')) {
                            $duration = $this->getElValue($val); // продолжительность
                            $out[] = array('tracknum' => $tracknum, // порядковый номер в альбоме
                                'duration' => $duration, // продолжительность
                                //'track_link' => $track_link, // название
                                'track_name' => $track_name, // ссылка на трек
                                'primary' => $primary, // ссылка на трек
                            );
                        }
                    }
                }
            }
        }
        return $out;
    }

    /**
     * @param $data
     * @return array
     */
    protected function formatOutput($data) {
        $out = array();
        foreach ($data as $artist) {
            $artistName = Helper::codeToHtmlEntity($artist[0]['artist_name']);
            $artist = (array)$artist;
            foreach ($artist as $k_artist => $album) {
                $album = (array)$album;
                if (!empty($album['tracks'])) {
                    foreach ($album['tracks'] as $k_album => $tack) {
                        $tack = (array)$tack;
                        $out[] = array(
                            'artist_name' => $artistName,
                            'album_release_year' => $album['year'],
                            'album_release_date' => strtotime($album['release_date']) ? date('d.m.Y', strtotime($album['release_date'])) : '',
                            'album_name' => Helper::codeToHtmlEntity($album['album_name']),
                            'album_label' => Helper::codeToHtmlEntity($album['label']),
                            'album_rating' => $album['rating'],
                            'album_img' => $album['img'],
                            'discography_type' => Helper::codeToHtmlEntity($album['discography_type']),
                            'track_sequence_number' => $tack['tracknum'],
                            'track_name' => Helper::codeToHtmlEntity($tack['track_name']), //'track_link' => $tack['track_link'],
                            'track_duration' => $tack['duration'],
                            'primary' => Helper::codeToHtmlEntity($tack['primary']),);
                    }
                } else {
                    $out[] = array(
                        'artist_name' => $artistName,
                        'album_release_year' => $album['year'],
                        'album_release_date' => strtotime($album['release_date']) ? date('d.m.Y', strtotime($album['release_date'])) : '',
                        'album_name' => Helper::codeToHtmlEntity($album['album_name']),
                        'album_label' => Helper::codeToHtmlEntity($album['label']),
                        'album_rating' => $album['rating'],
                        'album_img' => $album['img'],
                        'discography_type' => Helper::codeToHtmlEntity($album['discography_type']),
                        'track_sequence_number' => '', 'track_name' => '', //'track_link' => '',
                        'track_duration' => '', 'primary' => '',);
                }
            }
        }
        return $out;
    }

    private function dataInFile($path, $fileName) {
        $this->dataInFile->initPath($path, $fileName);
        return $this->dataInFile;
    }

    private function artistInfoSave($artist) {
        $csvFileName = Helper::fileNameFormat($artist['name']);
        file_put_contents($this->outputDir . self::DS . $csvFileName . '.csv', $artist['csv']);
        var_dump($artist['name']);
        $this->dataInFile($this->confDir, $this->savedList)->save($artist['name']); // save info about save to csv
    }

    /**
     * @return array
     */
    function run($artistName = null) {
        if ($artistName) {
            $this->artistsNames = array($artistName);
        }
        $artistInfoAll = array();
        $report = array();
        if (!empty($this->artistsNames)) {
            // $artistNamesFromFileNames = $this->getArtistNamesFromFileNames();
            $artistsSavedList = $this->dataInFile($this->confDir, $this->listToSave)->getSavedAsArray();
            $t = time();
            foreach ($this->artistsNames as $artistName) {
                //                if (!in_array($artistName, $bad)) {
                if ((empty($artistsSavedList) || !in_array($artistName, $artistsSavedList)) || $artistName) {
                    $artistData = $this->getArtistInfo($artistName);
                    $this->artistInfoSave($artistData);
                    $artistInfoAll[] = $artistData['info'];
                    $report[] = array(
                        'number' => count($artistInfoAll),
                        'artist_name' => $artistName,
                        'time' => date('H:i:s'),
                        'time_to_get_data' => TimeHelper::getHumanFormatFromNow($t),
                    );
                    $t = time();
                }
                //                }
            }
        }
        return array('artist_info_all' => $artistInfoAll, 'report_about_parse' => $report);
    }
}

// sleep(5);
// echo time();
// exit;
// $t = '..\output\Amy Winehouse.csv_';
// file_put_contents($t, 232345);
// exit;
// echo time();
// exit;
$AllMusicParser = new AllMusicParser('../output');
// $out = $AllMusicParser->run();
// $table = Helper::arrayToHtmlTable($out['out']);
// echo $table;
$artist = isset($_GET['artist']) ? urldecode($_GET['artist']) : null;
$artistData = $AllMusicParser->run($artist);
$table = Helper::arrayToHtmlTable($artistData['report_about_parse']);
echo $table;