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

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('vendor\autoload.php');
use \Composer\Autoload as composer;

composer\includeFile('kernel\common.inc.php');
kernel\common::$is_jsonapi = true; //Set is is_jsonapi to true
$common = new kernel\common();

exit($common->run_jsonapi());