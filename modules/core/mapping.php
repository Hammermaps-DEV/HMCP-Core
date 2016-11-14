<?php

namespace module\core;
use kernel\common;

/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 28.04.2016
 * Time: 22:11
 */
class mapping extends common
{
    public static function init() {
        self::$router->map('GET', '/[en|de:language]?', 'Redirect');
        self::$router->map('GET', '/', 'Redirect');
    }
}