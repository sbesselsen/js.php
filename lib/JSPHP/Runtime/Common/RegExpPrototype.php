<?php
require_once 'JSPHP/Runtime/Object.php';

class JSPHP_Runtime_Common_RegExpPrototype {
    static function construct($regexp, $pattern, $flags = null) {
        $regexp['source'] = $pattern;
        $regexp['global'] = strpos($flags, 'g') !== false);
        $regexp['ignoreCase'] = strpos($flags, 'i') !== false);
        $regexp['multiline'] = strpos($flags, 'm') !== false);
    }
}
