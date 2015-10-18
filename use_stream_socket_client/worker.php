<?php
set_time_limit(86400);
ini_set('memory_limit', '3G');

require_once 'simple_html_dom.php';

function getCsvFromArray(array $_data, $file_name = '', $file_to_output = false) {
    $csv = '';
    if($file_to_output) {
        header("Content-Type: text/csv; charset=windows-1251");
        header('Content-disposition: attachment;filename='.$file_name.date('Y-m-d H:i:s', time()).'.csv');
    }
    if(!empty($_data)) {
        $head = array_keys($_data[0]);
        $csv .= '"'.mb_convert_encoding(join('";"', $head), 'windows-1251', 'utf8').'"'."\r\n";
        foreach($_data as $row) {
            if(!empty($row)) {
                $str = '"'.mb_convert_encoding(join('";"', array_map(function ($row) { return str_replace('"', '""', $row); }, $row)), 'windows-1251', 'utf8').'"';
                $csv .= $str."\r\n";
            } else {
                throw new Exception('Not 2d array.');
            }
        }
        if($file_to_output) {
            echo $csv;
        }
    }
    return $csv;
}


function clearHtml($s) {
    return str_replace(array('&quot;', "&", "'", 'itemscope'), array("&#34;", '&#38;', '', ''), $s);
}


function getElValue($el) {
    $out = null;
    if(isset($el['value']) && $value = trim($el['value'])) {
        //$name = isset($el['attributes']) && isset($el['attributes']['CLASS']) ? $el['attributes']['CLASS'] : '';
        $out = $value;
    }
    return $out;
}

function arrayToHtmlTable($ar) {
    if(!isset($ar[0])) {
        return '';
    }
    $ar = is_object($ar) ? (array)$ar : $ar;
    $head = array_keys((array)$ar[0]);
    $html = "<table style='border: 1px solid #3e3e3e; border-collapse: collapse;' class='myTable tablesorter'>\n<thead>\n<tr>\n";
    foreach($head as $th) {
        $th = is_object($th) ? (array)$th : $th;
        if(is_array($th)) {
            $th = is_array($th[0]) ? $th : array($th);
            $html .= "\t\t<td style='border: 1px solid #3e3e3e'>\n".arrayToHtmlTable($th)."\n</td>\n";
        } else {
            $html .= "\t<th style='border: 1px solid #3e3e3e; font-weight: bold;' class='header'>".$th."</th>\n";
        }
    }
    $html .= "</thead>\n<tbody>\n";
    foreach($ar as $tr) {
        $html .= "\t<tr>\n";
        $tr = is_object($tr) ? (array)$tr : $tr;
        foreach($tr as $td) {
            $td = is_object($td) ? (array)$td : $td;
            if(is_array($td)) {
                $td = is_array($td[0]) ? $td : array($td);
                $html .= "\t\t<td style='border: 1px solid #3e3e3e'>\n".arrayToHtmlTable($td)."\n</td>\n";
            } else {
                if(filter_var($td, FILTER_VALIDATE_URL)) {
                    $td = '<a href="'.$td.'">'.$td.'</a>';
                }
                $html .= "\t\t<td style='border: 1px solid #3e3e3e'>".$td."</td>\n";
            }

        }
        $html .= "\t</tr>\n";
    }
    $html .= "</tr>\n</table>\n";
    return $html;
}

function getAlbums($url, $discography_type = '') {
    $out = array();
    $data = file_get_html($url.'/'.$discography_type);
    $base_url = 'http://www.allmusic.com';
    if($data->innertext != '') {
        $year
            = $album_name
            = $release_date
            = $label
            = $album_link
            = $img
            = null;
        foreach($data->find('.discography table>tbody td') as $el) {
            $s = clearHtml($el->outertext());
            $xmlparser = xml_parser_create();
            xml_parse_into_struct($xmlparser, $s, $values);
            xml_parser_free($xmlparser);
            foreach($values as $val) {
                //var_dump($val);
                if(strtolower($val['tag']) == 'td' && (isset($val['attributes']['CLASS']) && strtolower($val['attributes']['CLASS']) == 'year')) {
                    $year = getElValue($val);
                }
                if(strtolower($val['tag']) == 'td' && $val['type'] == 'complete') {
                    if(isset($val['attributes']['CLASS']) && $val['attributes']['CLASS'] == 'year' && isset($val['attributes']['DATA-SORT-VALUE'])) {
                        $album_info = $val['attributes']['DATA-SORT-VALUE'];
                        $release_date = substr($album_info, 0, 10); // release_date
                        $album_name = substr($album_info, 11, strlen($album_info) - 11); // album_name
                    }
                }
                if(strtolower($val['tag']) == 'td' && (isset($val['attributes']['CLASS']) && strtolower($val['attributes']['CLASS']) == 'label')) {
                    $label = getElValue($val); // label
                }
                if(strtolower($val['tag']) == 'img' && (isset($val['attributes']['DATA-ORIGINAL']))) {
                    $img = str_replace(array('JPG_75', '?partner=allrovi.com'), array('JPG_500', ''), $val['attributes']['DATA-ORIGINAL']); // img
                }
                if(strtolower($val['tag']) == 'a' && (isset($val['attributes']['HREF']) && stripos($val['attributes']['HREF'], 'album') !== false)) {
                    $href = $val['attributes']['HREF'];
                    if($href[0] == '/') {
                        $href = $base_url.$href;
                    }
                    $album_link = $href; // album_link
                }
                $name = isset($val['attributes']) && isset($val['attributes']['CLASS']) ? $val['attributes']['CLASS'] : '';
                if(preg_match('/(allmusic-rating rating-allmusic-)(\d)/i', $name, $t)) {
                    $rating = isset($t[2]) ? $t[2] : null;
                    $out[] = array(
                        'year' => $year,
                        'album_name' => $album_name,
                        'release_date' => $release_date,
                        'label' => $label,
                        'rating' => $rating,
                        'album_link' => $album_link,
                        'img' => $img,
                        'discography_type' => $discography_type ?: 'albums',
                    );
                }
            }

        }
    }
    return $out;
}

function getTracks($url) {
    $out = array();
    if($url) {
        //$url = 'http://www.allmusic.com/album/wednesday-morning-3-am-mw0000191731';
        $data = file_get_html($url);
        $base_url = 'http://www.allmusic.com';
        if($data->innertext != '') {
            $tracknum
                = $duration
                = $track_link
                = $track_name
                = $primary
                = null;
            foreach($data->find('.track-listing table td') as $el) {
                $s = clearHtml($el->outertext());
                $values = getXmlStruct($s);
                $prev = array();
                foreach($values as $val) {
                    if(strtolower($val['tag']) == 'td' && (isset($val['attributes']['CLASS']) && $val['attributes']['CLASS'] == 'tracknum')) {
                        $tracknum = getElValue($val); // порядковый номер в альбоме
                    }
                    if(strtolower($val['tag']) == 'a' && isset($val['attributes']['HREF']) && isset($val['attributes']['ITEMPROP'])) {
                        $href = $val['attributes']['HREF'];
                        $track_link = $href; // ссылка на трек
                        $track_name = getElValue($val); // название
                    }
                    if((isset($val['attributes']['CLASS']) && $val['attributes']['CLASS'] == 'primary')) {
                        $prev = $val;
                    }
                    if(!empty($prev) && strtolower($val['tag']) == 'a' && isset($val['attributes']['HREF'])) {
                        $primary = getElValue($val);
                        $prev = array();
                    }
                    if(strtolower($val['tag']) == 'td' && (isset($val['attributes']['CLASS']) && $val['attributes']['CLASS'] == 'time')) {
                        $duration = getElValue($val); // продолжительность
                        $out[] = array(
                            'tracknum' => $tracknum, // порядковый номер в альбоме
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

function getArtistPageLink($s) {
    $href = null;
    if($s) {
        $url = 'http://www.allmusic.com/search/all/'.urlencode($s);
        $data = file_get_html($url);
        if($data->innertext != '') {
            foreach($data->find('.artist .name') as $el) {
                $s = $el->outertext();
                $s = str_replace('&quot;', "&#34;", $s);
                $values = getXmlStruct($s);
                foreach($values as $val) {
                    if(strtolower($val['tag']) == 'a' && isset($val['attributes']['HREF'])) {
                        $href = $val['attributes']['HREF'];
                        //$out = getAlbum($val['attributes']['HREF'].'/discography');
                    }
                }
            }
        }
    }
    return $href;
}

function formatOutput($data) {
    $out = array();
    foreach($data as $artist) {
        $artist = (array)$artist;
        //var_dump($artist);
        //exit;
        foreach($artist as $k_artist => $album) {
            $album = (array)$album;
            if(!empty($album['tracks'])) {
                foreach($album['tracks'] as $k_album => $tack) {
                    $tack = (array)$tack;
                    $out[] = array(
                        'artist_name' => $artist['artist_name'],

                        'album_release_year' => $album['year'],
                        'album_release_date' => strtotime($album['release_date']) ? date('d.m.Y', strtotime($album['release_date'])) : '',
                        'album_name' => $album['album_name'],
                        'album_label' => $album['label'],
                        'album_rating' => $album['rating'],
                        'album_img' => $album['img'],
                        'discography_type' => $album['discography_type'],

                        'track_sequence_number' => $tack['tracknum'],
                        'track_name' => $tack['track_name'],
                        //'track_link' => $tack['track_link'],
                        'track_duration' => $tack['duration'],
                        'primary' => $tack['primary'],
                    );
                }
            } else {
                $out[] = array(
                    'artist_name' => $artist['artist_name'],

                    'album_release_year' => $album['year'],
                    'album_release_date' => strtotime($album['release_date']) ? date('d.m.Y', strtotime($album['release_date'])) : '',
                    'album_name' => $album['album_name'],
                    'album_label' => $album['label'],
                    'album_rating' => $album['rating'],
                    'album_img' => $album['img'],
                    'discography_type' => $album['discography_type'],

                    'track_sequence_number' => '',
                    'track_name' => '',
                    //'track_link' => '',
                    'track_duration' => '',
                    'primary' => '',
                );
            }
        }
    }
    return $out;
}

/**
 * @param $s
 * @return mixed
 */
function getXmlStruct($s) {
    $xmlparser = xml_parser_create();
    xml_parse_into_struct($xmlparser, $s, $values);
    xml_parser_free($xmlparser);
    return $values;
}


//////////////////////////////////////// allmusic /////////////////////////////////////


$artists = array(
    //'Simon & Garfunkel',
    //'AC/DC',
    //'Radiohead',
    //'Bruce Springsteen',
    //'Grateful Dead',
    //'Paul McCartney',
    //'The Beach Boys',
    //'Bob Dylan',
    //'Janis Joplin',
    //'Elvis Presley',
    //'James Taylor',
    //'Pink Floyd',
    //
    //'Jimi Hendrix',
    //'Nirvana',
    //'Aerosmith',
    //'Billy Joel',
    //'Green Day',
    //'The Ramones',
    //'The Doors',
    //'David Bowie',
    //'The Who',
    //'Pearl Jam',
    //'Elton John',
    //'The Clash',
    //'Led Zeppelin',
    //'U2',
    //'John Lennon',
        //'Madonna',
    //'Van Morrison',
    //'R.E.M.',
    //'The Beatles',
    //'Christina Aguilera',
    //'Queen',
    //'Neil Diamond',
    //'The Rolling Stones',
    //'Neil Young',
    //'Elvis Costello',
    //'Justin Timberlake',
    //'Phil Collins',
    //'Stevie Ray Vaughan',
    //'Megadeth',
    //'Lady Gaga',
    //'John Hiatt',
    //'George Harrison',
    //'Foreigner',
    //'Alice Cooper',
    //'Lou Reed',
    //'Rod Stewart',
    //'Kraftwerk',
    //'Daft Punk',
    //'Tangerine Dream',
    //'The Chemical Brothers',
    //'DJ Shadow',
    //'Aphex Twin',
    //'Roni Size',
    //'Underworld',
    //'Vangelis',
    //'Moby',
    //'The Prodigy',
    //'Goldie',
    //'Orbital',
    //'Skrillex',
    //'Deadmau5',
    //'Major Lazer',
    //'Basement Jaxx',
    //'Fatboy Slim',
    //'Massive Attack',
    //'Autechre',
    //'Mouse on Mars',
    //'Paul Oakenfold',
    //'Todd Terry',
    //'Tricky',
    //'Air',
    //'Boards of Canada',
    //'Dan the Automator',
    //'Bjцrk',
    //'Nightmares on Wax',
    //'Lamb',
    //'Borgore',
    //'The Future Sound of London',
    //'Larry Heard',
    //'Justice',
    //'Jean Michel Jarre',
    //'Fischerspooner',
    //'Actress',
    //'Carl Cox',
    //'µ-Ziq',
    //'Fennesz',
    //'Juan Atkins',
    //'Sasha + John Digweed',
    //'Masters at Work',
    //'The Orb',
    //'Plastikman',
    //'Coldcut',
    //'Carl Craig',
    //'Kanye West',
    //'OutKast',
    //'Dr. Dre',
    //'Eminem',
    //'Wu-Tang Clan',
    //'Grandmaster Flash',
    //'Jay-Z',
    //'The Notorious B.I.G.',
    //'2Pac',
    //'Missy Elliott',
    //'Beastie Boys',
    //'Afrika Bambaataa',
    //'DMX',
    //'Boogie Down Productions',
    //'Eric B. & Rakim',
    //'LL Cool J',
    //'Salt-N-Pepa',
    //'Nas',
    //'De La Soul',
    //'Public Enemy',
    //'Lil Wayne',
    //'Drake',
    //'Kool Moe Dee',
    //'N.W.A',
    //'Ice-T',
    //'Snoop Dogg',
    //'50 Cent',
    //'Run-D.M.C.',
    //'Big Daddy Kane',
    //'Queen Latifah',
    //'Ice Cube',
    //'Digital Underground',
    //'The Roots',
    //'A Tribe Called Quest',
    //'Lil Jon',
    //'Cypress Hill',
    //'Kurtis Blow',
    //'Nicki Minaj',
    //'Bone Thugs-N-Harmony',
    //'KRS-One',
    //'Fugees',
    //'Kool Keith',
    //'EPMD',
    //'Biz Markie',
    //'Naughty by Nature',
    //'Slick Rick',
    //'The Sugarhill Gang',
    //'Diddy',
    //'Gladys Knight',
    //'The Temptations',
    //'James Brown',
    //'The Supremes',
    //'Alicia Keys',
    //'Ray Charles',
    //'Marvin Gaye',
    //'Stevie Wonder',
    //'Aretha Franklin',
    //'Janet Jackson',
    //'Michael Jackson',
    //'Chris Brown',
    //'Earth, Wind & Fire',
    //'Mary J. Blige',
    //'Usher',
    //'Babyface',
    //'Beyoncй',
    //'Luther Vandross',
    //'Aaron Neville',
    //'Tina Turner',
    //'R. Kelly',
    //'Isaac Hayes',
    //'John Legend',
    //'Toni Braxton',
    //'Whitney Houston',
    //'Chaka Khan',
    //'Al Green',
    //'Sade',
    //'Kool & the Gang',
    //'Ciara',
    //'Brandy',
    //'Donna Summer',
    //'Amy Winehouse',
    //'Patti LaBelle',
    //'Ruth Brown',
    //'Smokey Robinson',
    //'Barry White',
    //'Macy Gray',
    //'Rick James',
    //'Ledisi',
    //'Eli "Paperboy" Reed',
    //'Boyz II Men',
    //'Lionel Richie',
    //'The Staple Singers',
    //'Jody Watley',
    //'Robin Thicke',
    //'The Isley Brothers',
    //'Jill Scott',
    //'Luis Miguel',
    //'Ricky Martin',
    //'Shakira',
    //'Enrique Iglesias',
    //'Juan Gabriel',
    //'Belinda',
    //'Astor Piazzolla',
    //'Menudo',
    //'Calle 13',
    //'Tito Puente',
    //'Alejandro Sanz',
    //'Celia Cruz',
    //'Flaco Jimйnez',
    //'Juanes',
    //'Cafй Tacuba',
    //'Marc Anthony',
    //'Don Omar',
    //'Aventura',
    //'Claudia Leitte',
    //'RBD',
    //'Mijares',
    //'Caetano Veloso',
    //'Paco de Lucнa',
    //'Chico Buarque',
    //'Ray Barretto',
    //'Airto Moreira',
    //'Marcos Valle',
    //'Rubйn Blades',
    //'Rosana',
    //'Bebe',
    //'Paulina Rubio',
    //'Manб',
    //'Wisin & Yandel',
    //'Diana Reyes',
    //'Mongo Santamaria',
    //'Tom Zй',
    //'Gipsy Kings',
    //'Machito',
    //'Los Fabulosos Cadillacs',
    //'Pedro Infante',
    //'Lucha Villa',
    //'Daddy Yankee',
    //'Johnny Pacheco',
    //'Aleks Syntek',
    //'Juan Luis Guerra',
    //'Rocнo Dъrcal',
    //'Compay Segundo',
    //'Marisa Monte',
    //'Johnny Cash',
    //'Hank Williams',
    //'Dolly Parton',
    //'Willie Nelson',
    //'Garth Brooks',
    //'Taylor Swift',
    //'Buck Owens',
    //'Waylon Jennings',
    //'George Strait',
    //'Ernest Tubb',
    //'Emmylou Harris',
    //'Merle Haggard',
    //'Alan Jackson',
    //'Tim McGraw',
    //'Reba McEntire',
    //'Brooks & Dunn',
    //'George Jones',
    //'Roy Acuff',
    //'Lady Antebellum',
    //'Randy Travis',
    //'The Carter Family',
    //'Charley Pride',
    //'Dixie Chicks',
    //'Ray Price',
    //'Glen Campbell',
    //'Hank Snow',
    //'Eddy Arnold',
    //'Toby Keith',
    //'Carrie Underwood',
    //'Tammy Wynette',
    //'Shania Twain',
    //'David Allan Coe',
    //'Kris Kristofferson',
    //'Vince Gill',
    //'Gene Autry',
    //'Conway Twitty',
    //'Jamey Johnson',
    //'Bob Wills',
    //'Patsy Cline',
    //'Dwight Yoakam',
    //'Gram Parsons',
    //'The Judds',
    //'Roy Rogers',
    //'Billy Joe Shaver',
    //'Brad Paisley',
    //'Loretta Lynn',
    //'Tennessee Ernie Ford',
    //'David Grisman',
    //'Bessie Smith',
    //'Muddy Waters',
    //'Etta James',
    //'B.B. King',
    //'Bobby "Blue" Bland',
    //'Robert Johnson',
    //'Jimmy Reed',
    //'John Lee Hooker',
    //'Buddy Guy',
    //'Albert King',
    //'Lightnin Hopkins',
    //'Otis Rush',
    //'Clarence "Gatemouth" Brown',
    //'Lonnie Johnson',
    //'Big Joe Turner',
    //'Willie Dixon',
    //'Charles Brown',
    //'Big Walter Horton',
    //'Son House',
    //'Roy Brown',
    //'Freddie King',
    //'T-Bone Walker',
    //'Little Walter',
    //'Sonny Boy Williamson II',
    //'Junior Wells',
    //'Champion Jack Dupree',
    //'Leroy Carr',
    //'Brownie McGhee',
    //'Michael Bloomfield',
    //'Johnny "Guitar" Watson',
    //'Pinetop Perkins',
    //'Jimmy Witherspoon',
    //'Little Milton',
    //'Memphis Slim',
    //'Sonny Terry & Brownie McGhee',
    //'Paul Butterfield',
    //'Professor Longhair',
    //'Robert Cray',
    //'Lowell Fulson',
    //'Mississippi Fred McDowell',
    //'Percy Mayfield',
    //'Earl King',
    //'Big Joe Williams',
    //'John Hammond, Jr.',
    //'Taj Mahal',
    //'James Booker',
    //'Charlie Musselwhite',
    //'Wynonie Harris',

    'Radiohead',
    //'Madonna',
    //'Simon & Garfunkel',
    //'AC/DC',
);


function fileNameFormat($file_name) {
    $from = array('\\', '/', '&', '.');
    $to = array('', '', '', '');
    return str_replace($from, $to, $file_name);
}

$time = microtime(true);
$out = array();
$total = array();
$types = array('', 'compilations', 'singles', /*'video', 'others'*/);
//$types = array('compilations');
$i = 0;
$artists = $_GET['artists'];
foreach($artists as $artist_name) {
    $out = array();
    //$albums = json_decode(file_get_contents('data_albums.txt'));
    //$albums = (array)$albums;
    //echo arrayToHtmlTable($albums);
    //exit;

    $artistPageLink = getArtistPageLink($artist_name);
    foreach($types as $type) {
        $albums = getAlbums($artistPageLink.'/discography', $type);
        //file_put_contents('data_albums.txt', json_encode($albums));
        //exit;
        foreach($albums as $k => $album) {
            //$album = (array)$album;
            //    $album['album_link'] = 'http://www.allmusic.com/album/im-breathless-mw0000208070';
            $tracks = getTracks($album['album_link']);
            //var_dump('$tracks');
            //var_dump($tracks);
            $albums[$k]['tracks'] = $tracks;
            $albums[$k]['artist_name'] = $artist_name;
            //$csv = getCsvFromArray($tracks);
            //file_put_contents('tracks_'.$i.'.csv', $csv);
            //$i++;
        }
        $out[] = $albums;
        //$total = array_merge($total, $out);
    }
    $t = formatOutput($out);
    $csv = getCsvFromArray($t);
    //echo $csv;
    $f = fileNameFormat($artist_name);
    $f .= '.csv';
    file_put_contents('data/'.$f, $csv);
    echo count($out).PHP_EOL;
    //echo arrayToHtmlTable($t);
}
//var_dump(microtime(true) - $time);
//var_dump($total);
//$t = getTable($total);
//echo arrayToHtmlTable($t);
//exit;
//arrayToCsv($t);
