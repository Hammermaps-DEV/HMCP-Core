<?php
/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 29.05.2016
 * Time: 23:20
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