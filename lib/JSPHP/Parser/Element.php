<?php
abstract class JSPHP_Parser_Element {
}

class JSPHP_Parser_Element_Main extends JSPHP_Parser_Element {
    /**
     * Statements of the code.
     * @var array (JSPHP_Parser_Element_Statement)
     */
    public $statements = array ();
}

abstract class JSPHP_Parser_Element_Statement extends JSPHP_Parser_Element {
}

/**
 * A statement consisting of precompiled opcode.
 */
class JSPHP_Parser_Element_Statement_Precompiled extends JSPHP_Parser_Element_Statement {
    /**
     * @var array
     */
    public $ops = array ();
}
