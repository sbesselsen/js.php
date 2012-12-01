<?php
class JSPHP_Runtime_Common_ObjectPrototype {
    function toString__onObject($obj) {
        if ($obj instanceof JSPHP_Runtime_Object) {
            return $obj->toJSString();
        }
        return (string)$obj;
    }
    
    function valueOf__onObject($obj) {
        if ($obj instanceof JSPHP_Runtime_Object) {
            return $obj->valueOf();
        }
        return null;
    }
}