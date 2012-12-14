<?php
require_once 'JSPHP/Runtime/FunctionHeader.php';

class JSPHP_Runtime_PHPFunctionHeader extends JSPHP_Runtime_FunctionHeader {
    private $callback;
    public $ignoreContext = false;
    
    function __construct(JSPHP_Runtime_FunctionHeader $constructor, $callback) {
        parent::__construct($constructor);
        $this->callback = $callback;
    }
    
    function callFunction() {
        $args = func_get_args();
        return $this->callFunctionWithArgs(null, $args);
    }
    
    function callFunctionWithArgs($context, array $args) {
        if (!$this->ignoreContext) {
            array_unshift($args, $context);
        }
        return call_user_func_array($this->callback, $args);
    }
}
