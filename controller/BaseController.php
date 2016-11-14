<?php
/*
 * Diese Datei ist Teil von HM-Kernel.
 *
 * HM-Kernel ist Freie Software: Sie können es unter den Bedingungen
 * der GNU General Public License, wie von der Free Software Foundation,
 * Version 3 der Lizenz oder (nach Ihrer Wahl) jeder späteren
 * veröffentlichten Version, weiterverbreiten und/oder modifizieren.
 *
 * HM-Kernel wird in der Hoffnung, dass es nützlich sein wird, aber
 * OHNE JEDE GEWÄHRLEISTUNG, bereitgestellt; sogar ohne die implizite
 * Gewährleistung der MARKTFÄHIGKEIT oder EIGNUNG FÜR EINEN BESTIMMTEN ZWECK.
 * Siehe die GNU General Public License für weitere Details.
 *
 * Sie sollten eine Kopie der GNU General Public License zusammen mit diesem
 * Programm erhalten haben. Wenn nicht, siehe <http://www.gnu.org/licenses/>.
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