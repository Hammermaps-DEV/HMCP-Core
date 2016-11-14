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

namespace kernel;

/* block attempts to directly run this script */
if (getcwd() == dirname(__FILE__)) {
    die('block directly run');
}

class SystemMassages {
    //System [ 0 -> 99 ]
    const SYS_NOT_ERROR = 0;
    const SYS_FATAL_ERROR = 1;

    //UserSpace [ 100 -> 199 ]
    const USER_NOT_ENABLED = 100;
    const USER_NOT_FOUND = 101;
    const USER_PASSWORD_FAILED = 102;

    const USER_CHANGE_PASSWORD_FAILED = 103;
    const USER_CHANGE_PASSWORD_NOT_FOUND = 104;
    const USER_CHANGE_PASSWORD_EMPTY = 105;

    const USER_CHANGE_PASSWORD_EMAIL_SEND = 106;
    const USER_CHANGE_PASSWORD_EMAIL_SEND_FAILD = 107;

    const USER_EMAIL_PWCH_SEND_FAILD = 108;
    const USER_UAK_NOT_FOUND = 109;

    //XXXX [ 200 -> 299 ]
}