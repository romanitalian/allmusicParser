<?php
set_time_limit(86400);
ini_set('memory_limit', '3G');

echo "Program starts at ".date('h:i:s').".\n";

$timeout = 3600;
$result = array();
$sockets = array();
$convenient_read_block = 8192;

/* Выполнить одновременно все запросы; ничего не блокируется. */
$id = 0;
$artists = ['Radiohead', 'Madonna'];
foreach($artists as $artistName) {
$s = stream_socket_client("allmusic.local:80", $errno,
        $errstr, $timeout,
        STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT);
    if($s) {
        $sockets[$id++] = $s;
        $http_message = "GET /ibm_test/worker.php?artists[]="
            .$artistName
            ." HTTP/1.0\r\nHost: allmusic.local\r\n\r\n";
        fwrite($s, $http_message);
    } else {
        echo "Stream ".$id." failed to open correctly.";
    }
}

while(count($sockets)) {
    $read = $sockets;
    stream_select($read, $w = null, $e = null, $timeout);
    if(count($read)) {
        /* stream_select обычно перемешивает $read, поэтому мы должны вычислить,
           из какого сокета выполняется чтение.  */
        foreach($read as $r) {
            $id = array_search($r, $sockets);
            $data = fread($r, $convenient_read_block);
            echo $data.PHP_EOL;
            /* Сокет можно прочитать либо потому что он
               имеет данные для чтения, ЛИБО потому что он в состоянии EOF. */
            if(strlen($data) == 0) {
                echo "Stream ".$id." closes at ".date('h:i:s').".\n";
                fclose($r);
                unset($sockets[$id]);
            } else {
                $result[$id] .= $data;
            }
        }
    } else {
        /* Таймаут означает, что *все* потоки не
           дождались получения ответа. */
        echo "Time-out!\n";
        break;
    }
}


