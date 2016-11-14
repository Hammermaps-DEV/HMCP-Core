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