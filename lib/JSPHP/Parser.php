<?php
require_once 'JSPHP/IParser.php';
require_once 'JSPHP/Parser/RDParser.php';
require_once 'JSPHP/Parser/Element.php';

/**
 * A good default parser for parsing code to JSPHP_Parser_Elements.
 */
class JSPHP_Parser implements JSPHP_IParser {
    /**
     * @var JSPHP_Parser_RDParser
     */
    private $parser;
    
    function __construct() {
        $this->parser = new JSPHP_Parser_RDParser();
    }
    
    function parseJS($code) {
        try {
            $ops = $this->parser->parse($code);
            
            // wrap the ops in an obligatory and useless parse tree
            $main = new JSPHP_Parser_Element_Main();
            $precompiled = new JSPHP_Parser_Element_Statement_Precompiled();
            $precompiled->ops = $ops;
            $main->statements[] = $precompiled;
            
            return $main;
            
        } catch (Sparse_RDParser_ParseException $e) {
            require_once 'JSPHP/Parser/ParseException.php';
            throw new JSPHP_Parser_ParseException($e->getMessage());
        }
    }
    
}