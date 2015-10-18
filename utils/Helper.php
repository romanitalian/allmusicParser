<?php

/**
 * User: romanitalian
 * Date: 01.09.2015
 * Time: 18:11
 */
class Helper
{
    /**
     * @param $ar
     * @return string
     */
    public static function arrayToHtmlTable($ar) {
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

    /**
     * @param $s
     * @return mixed
     */
    public static function clearHtml($s) {
        return str_replace(array('&quot;', "&", "'", 'itemscope'), array("&#34;", '&#38;', '', ''), $s);
    }

    /**
     * @param array $_data
     * @param string $file_name
     * @param bool|false $file_to_output
     * @return string
     * @throws Exception
     */
    public static function getCsvFromArray(array $_data, $with_column_name = false, $file_name = '', $file_to_output = false) {
        $csv = '';
        if($file_to_output) {
            header("Content-Type: text/csv; charset=windows-1251");
            header('Content-disposition: attachment;filename='.$file_name.date('Y-m-d H:i:s', time()).'.csv');
        }
        if(!empty($_data)) {
            $head = array_keys($_data[0]);
            if($with_column_name) {
                // $csv .= '"'.mb_convert_encoding(join('";"', $head), 'windows-1251', 'utf8').'"'."\r\n";
                $csv .= '"'.join('";"', $head).'"'."\r\n";
            }
            foreach($_data as $row) {
                if(!empty($row)) {
                    //$str = '"'.mb_convert_encoding(join('";"', array_map(function ($row) { return str_replace('"', '""', $row); }, $row)), 'windows-1251', 'utf8').'"';
                    $str = '"'.join('";"', array_map(function ($row) { return str_replace('"', '""', $row); }, $row)).'"';
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


    /**
     * @param $s
     * @return mixed
     */
    public static function getXmlStruct($s) {
        $xmlparser = xml_parser_create();
        xml_parse_into_struct($xmlparser, $s, $values);
        xml_parser_free($xmlparser);
        return $values;
    }

    /**
     * @param $file_name
     * @return mixed
     */
    public static function fileNameFormat($file_name) {
        // $from = array('\\', '/', '"', '?', ':', '*', '>', '<', '&');
        // $to = array('', '', '', '', '', '', '', '');
        $from = array('\\', '/', ':', '*', '?', '"', '<', '>', '|');
        $to = array('', '', '', '', '', '', '', '', '');
        return str_replace($from, $to, $file_name);
    }

    public static function codeToHtmlEntity($str) {
        $from = array('&#34;', '&amp;');
        $to = array('"', '&');
        return str_replace($from, $to, $str);
    }
}