<?php
/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 28.04.2016
 * Time: 22:34
 */

namespace kernel\controller;

use kernel\common;

class RedirectController extends BaseController {
    public static function __default() {
        parent::__default(); //Call BaseController
        parent::__args(func_get_args(),array('language','action'));



      //  common::$template_index['content'] = $test;
    }
}
