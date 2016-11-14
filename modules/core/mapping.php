<?php
/*
 * Diese Datei ist Teil von HM-Core-Module.
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