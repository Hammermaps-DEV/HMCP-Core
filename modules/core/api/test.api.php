<?php

namespace module\core\api;
use kernel\common;

/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 28.04.2016
 * Time: 22:11
 */
class core extends common
{
    public static function run() {
        return array('module: test -> API');
    }
}