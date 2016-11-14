<?php
namespace kernel\controller;

class IndexController extends BaseController {
    public static function __default() {
        parent::__default(); //Call BaseController
        parent::__args(func_get_args(),array('action','controller'));

        //CODE...
    }
}