<?php
require_once 'JSPHP/Runtime/Object.php';

class JSPHP_Runtime_Array extends JSPHP_Runtime_Object implements Countable {
    private $arrayValues = array ();
    private $buildingJSString;
    
    function count() {
        return sizeof($this->arrayValues);
    }
    
    function offsetExists($k) {
        if (is_numeric($k)) {
            return sizeof($this->arrayValues) > $k;
        }
        if ($k === 'length') {
            return true;
        }
        return parent::offsetExists($k);
    }
    
    function offsetGet($k) {
        if ($k === 'length') {
            return $this->count();
        }
        if (is_numeric($k)) {
            return isset ($this->arrayValues[$k]) ? $this->arrayValues[$k] : null;
        }
        return parent::offsetGet($k);
    }
    
    function offsetSet($k, $v) {
        if ($k === null) {
            $this->arrayValues[] = $v;
        } else if (is_numeric($k)) {
            $this->arrayValues[$k] = $v;
        } else {
            parent::offsetSet($k, $v);
        }
    }
    
    function offsetUnset($k) {
        parent::offsetUnset($k);
    }
    
    function getOwnValues() {
        return $this->arrayValues;
    }
    
    function setArrayValues(array $values) {
        $this->arrayValues = $values;
    }
    
    function toJSString() {
        if ($this->buildingJSString) {
            return '';
        }
        $this->buildingJSString = true;
        $out = implode(',', $this->arrayValues);
        $this->buildingJSString = false;
        return $out;
    }
}
