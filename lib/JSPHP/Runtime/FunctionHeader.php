<?php
require_once 'JSPHP/Runtime/Object.php';

class JSPHP_Runtime_FunctionHeader extends JSPHP_Runtime_Object {
    public $numParams = 0;
    public $parentVarScope;
    public $opIndex = -1;
    public $referencesArguments = false;
    public $vm;
    
    function callFunction() {
        $args = func_get_args();
        return $this->callFunctionWithArgs(null, $args);
    }
    
    function callFunctionWithArgs($context, array $args) {
        if (!$this->vm) {
            throw new Exception("JS function is not meant to be called manually");
        }
        return $this->vm->callFunction($this, $context, $args);
    }
}