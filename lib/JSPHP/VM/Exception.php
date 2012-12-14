<?php
class JSPHP_VM_Exception extends Exception {
    public $fileName;
    public $lineNumber;
    public $exceptionObject;
    
    function __construct($msg, $fileName = null, $lineNumber = null, $exceptionObject = null) {
        parent::__construct($msg);
        $this->fileName = $fileName;
        $this->lineNumber = $lineNumber;
        $this->exceptionObject = $exceptionObject;
    }
}