<?php
require_once 'JSPHP/Runtime/Object.php';

class JSPHP_Runtime_FunctionHeader extends JSPHP_Runtime_Object {
    public $numParams = 0;
    public $parentVarScope;
    public $referencesArguments = false;
    public $runtime;
    
    public $opIndex;
    public $opCodeBlock;
    
    function callFunction() {
        $args = func_get_args();
        return $this->callFunctionWithArgs(null, $args);
    }
    
    function callFunctionWithArgs($context, array $args) {
        if (!$this->runtime || !$this->runtime->vm) {
            throw new Exception("JS function is not meant to be called manually");
        }
        return $this->runtime->vm->callFunction($this, $context, $args);
    }
}
