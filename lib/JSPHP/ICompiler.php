<?php
interface JSPHP_ICompiler {
    /**
     * Compile Javascript into OpCode.
     * @param JSPHP_Parser_Element_Main $code
     * @return array
     */
    function compile(JSPHP_Parser_Element_Main $code);
}
