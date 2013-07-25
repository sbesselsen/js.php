<?php
error_reporting(E_ALL | E_STRICT);

$dir = dirname(__FILE__);
set_include_path($dir . '/../lib/' . PATH_SEPARATOR . get_include_path());

require_once 'JSPHP/Environment.php';
$e = new JSPHP_Environment();

$exports = $e->runFile("{$dir}/calc.js");
var_dump($exports->g(1, 2));
$exports->setX(5);
var_dump($exports->g(1, 2));