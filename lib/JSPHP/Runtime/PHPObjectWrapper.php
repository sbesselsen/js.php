<?php
require_once 'JSPHP/Runtime/Object.php';
require_once 'JSPHP/Runtime/PHPFunctionHeader.php';

class JSPHP_Runtime_PHPObjectWrapper extends JSPHP_Runtime_Object {
    public $wrappedObject;
    public $runtime;
    
    function __construct($obj, $constructor = null) {
        parent::__construct($constructor);
        $this->wrappedObject = $obj;
    }
    
    function getOwnValues() {
        return get_object_vars($this->wrappedObject);
    }
    
    function offsetExists($k) {
        return isset ($this->wrappedObject->{$k}) || is_callable(array ($this->wrappedObject, $k));
    }
    
    function offsetGet($k) {
        if (isset ($this->wrappedObject->{$k})) {
            $val = $this->wrappedObject->{$k};
            if (is_array($val)) {
                return $this->runtime->importData($val);
            } else if (is_object($val) && !$val instanceof JSPHP_Runtime_Object) {
                return $this->runtime->importData($val);
            }
            return $val;
        } else if ($f = $this->functionGet($k)) {
            return $f;
        } else {
            return parent::offsetGet($k);
        }
    }
    
    function functionGet($k) {
        $cb = array ($this->wrappedObject, $k);
        if (is_callable($cb)) {
            $f = $this->runtime->createPHPFunction($cb);
            $f->ignoreContext = true;
            return $f;
        } else {
            $k .= "__onObject";
            $cb = array ($this->wrappedObject, $k);
            if (is_callable($cb)) {
                $f = $this->runtime->createPHPFunction($cb);
                return $f;
            }
        }
    }
    
    function offsetSet($k, $v) {
        $this->wrappedObject->{$k} = $v;
    }
    
    function offsetUnset($k) {
        if (isset ($this->wrappedObject->{$k})) {
            unset ($this->wrappedObject->{$k});
        }
    }
}
