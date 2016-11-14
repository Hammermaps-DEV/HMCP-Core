<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('vendor\autoload.php');
use \Composer\Autoload as composer;

composer\includeFile('kernel\common.inc.php');
kernel\common::$is_cronjob = true; //Set is_cronjob to true
$common = new kernel\common();

header('Content-type:application/json;charset=utf-8');
exit($common->run_cronjob());