<?php
require_once 'JSPHP/ICompiler.php';
require_once 'JSPHP/Compiler/Exception.php';

class JSPHP_Compiler {
    /**
     * Compile Javascript into OpCode.
     * @param JSPHP_Parser_Element_Main $code
     * @return array
     */
    function compile(JSPHP_Parser_Element_Main $code) {
        return $this->compileMain($code);
    }
    
    protected function compileMain(JSPHP_Parser_Element_Main $code) {
        $ops = array ();
        foreach ($code->statements as $statement) {
            if ($statement instanceof JSPHP_Parser_Element_Statement_Precompiled) {
                foreach ($statement->ops as $op) {
                    $ops[] = $op;
                }
            } else {
                throw new JSPHP_Compiler_Exception("Unknown element type: " . get_class($statement));
            }
        }
        return $ops;
    }
}