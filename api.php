<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('vendor\autoload.php');
use \Composer\Autoload as composer;

composer\includeFile('kernel\common.inc.php');
kernel\common::$is_jsonapi = true; //Set is is_jsonapi to true
$common = new kernel\common();

exit($common->run_jsonapi());