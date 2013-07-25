<?php
error_reporting(E_ALL | E_STRICT);

$dir = dirname(__FILE__);
set_include_path($dir . '/../lib/' . PATH_SEPARATOR . get_include_path());

require_once 'JSPHP/Environment.php';
$e = new JSPHP_Environment();

$exports = $e->runFile("{$dir}/testsuite.js");

class Test {
    function getY() {
        return time();
    }
    
    function setX($x) {
        $this->x = $x;
    }
}
$a = new Test();

$exports->manipulateObject($a);
var_dump("The time is: " . $a->x);