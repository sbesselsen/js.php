<?php
class JSPHP_Runtime_Common_JSPHPObject {
    /**
     * @var JSPHP_Runtime
     */
    public $runtime;
    
    function __construct(JSPHP_Runtime $runtime) {
        $this->runtime = $runtime;
    }
    
    function export(JSPHP_Runtime_Object $obj) {
        return $this->runtime->vm->addExports($obj);
    }
    
    function dump($obj) {
        var_dump((string)$obj);
    }
    
    function assert($val) {
        if (!$val) {
            $this->runtime->vm->currentEvaluator->error('Assertion failed');
        }
    }
}