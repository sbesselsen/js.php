<?php
require_once 'JSPHP/Runtime/Object.php';

class JSPHP_Runtime_Common_RegExp extends JSPHP_Runtime_Object {
    public $pattern;
    public $flags;
    
    function __construct($constructor, $pattern, $flags = null) {
        parent::__construct($constructor);
        $this->pattern = $pattern;
        $this->flags = $flags;
    }
}
