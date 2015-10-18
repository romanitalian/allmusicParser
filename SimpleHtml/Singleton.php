<?php

/**
 * User: romanitalian
 * Date: 15.10.2015
 * Time: 13:39
 */
abstract class Singleton
{
    protected static $inst = null;

    protected function __construct(){
    }

    public static function getInstance(){
        if (is_null(static::$inst)) {
            static::$inst = new static();
        }
        return self::$inst;
    }

    private function __clone(){
    }

    private function __wakeup(){
    }

}