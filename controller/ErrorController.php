<?php
/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 28.04.2016
 * Time: 20:28
 */

namespace kernel\controller;

class ErrorController extends BaseController {
    public static function __default() {
        parent::__default(); //Call BaseController
        parent::__args(func_get_args());
        echo('<span style="color: #FF0000;font-size: 14px;font-weight: bold;">Controller "' . self::$args['controller'] . '" not exists!</span><br>');
        echo('<span style="font-size: 12px;font-weight: bold;">Create a Controller-File: "'.ROOT_PATH.'/controller/'.self::$args['controller'].'.php" with content:</span><br>');
        echo('<span style="font-size: 12px;font-weight: bold;"><pre>namespace kernel\controller;<br><br>class '.self::$args['controller'].
            ' extends BaseController {<br>&nbsp;&nbsp;&nbsp;&nbsp;public static function __default() '.
            '{<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;parent::__default(); //Call BaseController'.
            '<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;parent::__args(func_get_args(),array(\'action\',\'controller\'));<br><br>'.
            '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;//CODE...<br>&nbsp;&nbsp;&nbsp;&nbsp;}<br>}</pre></span><br>');
    }
}