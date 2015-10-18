<?php

/**
 * User: romanitalian
 * Date: 18.10.2015
 * Time: 12:09
 */
class TimeHelper
{
    public static function getHumanFormat($diff) {
        return sprintf('%02d:%02d:%02d', ($diff / 3600), ($diff / 60 % 60), $diff % 60);
    }

    public static function getHumanFormatFromNow($t) {
        $diff = time() - $t;
        return self::getHumanFormat($diff);
    }
}