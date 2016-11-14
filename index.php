<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('vendor\autoload.php');
use \Composer\Autoload as composer;

composer\includeFile('kernel\common.inc.php');
$common = new kernel\common();

header('Content-Type: text/html; charset=utf-8');
exit($common->run_mainpage());