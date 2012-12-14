<?php
class JSPHP_VM_Exception extends Exception {
    public $fileName;
    public $lineNumber;
    
    function __construct($msg, $fileName = null, $lineNumber = null) {
        parent::__construct($msg);
        $this->fileName = $fileName;
        $this->lineNumber = $lineNumber;
    }
}