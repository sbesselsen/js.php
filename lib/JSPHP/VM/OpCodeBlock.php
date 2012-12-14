<?php
class JSPHP_VM_OpCodeBlock {
    private $fileName;
    private $ops = array ();
    private $processedOps = array ();
    private $labels = array ();
    private $pi = array ();
    private $lineNumbers = array ();
    
    function __construct($fileName, array $ops) {
        $this->fileName = $fileName;
        $this->loadOpCode($ops);
    }
    
    function fileName() {
        return $this->fileName;
    }
    
    function ops() {
        return $this->ops;
    }
    
    function processedOps() {
        return $this->processedOps;
    }
    
    /**
     * Get the line number that corresponds with an opIndex.
     * @param int $opIndex
     * @return int|null
     */
    function lineNumberForOpIndex($opIndex) {
        return isset ($this->lineNumbers[$opIndex]) ? $this->lineNumbers[$opIndex] : null;
    }
    
    /**
     * Get the opIndex that corresponds to a label.
     * @param string $label
     * @return int|null
     */
    function opIndexForLabel($label) {
        return isset ($this->labels[$label]) ? $this->labels[$label] : null;
    }
    
    /**
     * Get a string containing the OpCode that's loaded into this VM.
     * @return string
     */
    function opCodeAsString() {
        $lines = array ();
        foreach ($this->ops as $opIndex => $op) {
            foreach ($this->pi[$opIndex] as $pi) {
                $lines[] = implode(' ', $pi);
            }
            $lines[] = implode(' ', $op);
        }
        if (isset ($this->pi[$opIndex + 1])) {
            foreach ($this->pi[$opIndex + 1] as $pi) {
                $lines[] = implode(' ', $pi);
            }
        }
        return implode("\n", $lines) . "\n";
    }
    
    private function loadOpCode(array $ops) {
        $pi = array ();
        $lineNumber = 1;
        
        array_unshift($ops, array ('-', "file: {$this->fileName}"));
        
        // make sure each block of opcode runs in its own context
        $ops[] = array ('pushnull');
        $ops[] = array ('return');
        foreach ($ops as $op) {
            if ($op[0] == '%label') {
                $this->labels[$op[1]] = sizeof($this->ops);
                $pi[] = $op;
            } else if ($op[0] == '%loc') {
                $lineNumber = $op[1];
                $pi[] = $op;
            } else if ($op[0] == '-') {
                $pi[] = $op;
            } else {
                $this->pi[] = $pi;
                $pi = array ();
                $this->ops[] = $op;
                $processedOp = array (array_shift($op));
                $processedOp[] = $op ? $op : array ();
                $this->processedOps[] = $processedOp;
                $this->lineNumbers[] = $lineNumber;
            }
        }
        if ($pi) {
            $this->pi[] = $pi;
        }
    }
}
