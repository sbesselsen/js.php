<?php
require_once 'Sparse/RDParser.php';

/**
 * A parser that takes JS code as input and outputs a syntax tree.
 */
interface JSPHP_IParser {
    /**
     * @param string $code
     * @return JSPHP_Parser_Element_Main
     * @throws JSPHP_Parser_ParseException
     */
    function parseJS($code);
}
