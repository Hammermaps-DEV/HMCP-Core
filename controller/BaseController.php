<?php
/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 28.04.2016
 * Time: 20:29
 */

namespace kernel\controller;

use kernel\common;

class BaseController extends common {
    public static $args = array();
    public static $controller_language = null;

    public static function __default() {
        self::$args = array();
    }

    public static function __args(array $args,array $index=array('action','controller')) {
        if(count($args) >= 1) {
            $count = 0;
            foreach ($args as $arg) {
                if($count >= count($index)) {
                    break;
                }
                self::$args[$index[$count]] = $arg;
                $count++;
            }
        }

        self::__language(); //language switch on url
    }

    public static function __language() {
        if(array_key_exists('language',self::$args)) {
            self::$controller_language = strtolower(self::$args['language']).'_'.strtoupper(self::$args['language']);
            if(!empty(self::$args['language']) && \session::get('language') != self::$controller_language) {
                if (is_dir(ROOT_PATH . '/locale/' . self::$controller_language)) { //Check is language exists
                    self::$log['kernel']->addDebug('[Language-Switch]: Change Language to ->',
                        array(self::getIp(), self::$controller_language));
                    \session::set('language', self::$controller_language);
                } else {
                    self::$log['kernel']->addAlert('[Language-Switch]: Language is not exists! ->',
                        array(self::getIp(), self::$controller_language));
                }
            }

            unset(self::$args['language']);
        }
    }
}