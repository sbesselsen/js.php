<?php
class JSPHP_Runtime_Common_JSPHPObject {
    /**
     * @var JSPHP_Runtime
     */
    public $runtime;
    
    function __construct(JSPHP_Runtime $runtime) {
        $this->runtime = $runtime;
    }
    
    function export($obj) {
        return $this->runtime->addExportedFunctions($obj);
    }
    
    function dump($obj) {
        var_dump((string)$obj);
    }
    
    function assert($val) {
        if (!$val) {
            $msg = "Assertion failed";
            if ($line = $this->runtime->vm->currentLine()) {
                $msg .= " on line {$line}";
                if ($file = $this->runtime->vm->currentFile()) {
                    $msg .= " of file {$file}";
                }
            }
            var_dump($msg);
            exit;
        }
    }
}