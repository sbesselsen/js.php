<?php
class JSPHP_Runtime_Object implements ArrayAccess, IteratorAggregate {
    private $prototype;
    private $constructor;
    private $values = array ();
    public $primitiveValue;
    
    function __construct(JSPHP_Runtime_FunctionHeader $constructor = null) {
        if ($constructor) {
            $this->setConstructor($constructor);
        }
    }
    
    function setConstructor(JSPHP_Runtime_FunctionHeader $constructor = null) {
        $this->constructor = $constructor;
        if (isset ($constructor['prototype'])) {
            $this->setPrototype($constructor['prototype']);
        }
    }
    
    function setPrototype(JSPHP_Runtime_Object $prototype = null) {
        $this->prototype = $prototype;
    }
    
    function getIterator() {
        return new ArrayIterator($this->getOwnValues());
    }
    
    function getOwnValues() {
        return $this->values;
    }
    
    function offsetExists($k) {
        return array_key_exists($k, $this->values) 
            || (isset ($this->prototype) && $this->prototype->offsetExists($k));
    }
    
    function offsetGet($k) {
        if (array_key_exists($k, $this->values)) {
            return $this->values[$k];
        } else if ($k === 'constructor') {
            return $this->constructor;
        } else if ($this->prototype && $this->prototype !== $this) {
            return $this->prototype[$k];
        } else {
            return null;
        }
    }
    
    function offsetSet($k, $v) {
        $this->values[$k] = $v;
    }
    
    function offsetUnset($k) {
        unset ($this->values[$k]);
    }
    
    function isPrototypalInstanceOf(JSPHP_Runtime_FunctionHeader $f) {
        if ($this->constructor === $f) {
            return true;
        } else if ($this->prototype && $this->prototype !== $this) {
            return $this->prototype->isPrototypalInstanceOf($f);
        }
        return false;
    }
    
    function setObjectValues(array $values) {
        $this->values = $values;
    }
    
    function toJSString() {
        if ($this->primitiveValue !== null) {
            return (string)$this->primitiveValue;
        }
        // TODO: constructor name
        return '[object Object]';
    }
    
    function valueOf() {
        return $this->primitiveValue;
    }
    
    function __toString() {
        return $this['toString']->callFunctionWithArgs($this, array ());
    }
    
    function __call($f, $args) {
        if (($data = self::offsetGet($f)) && $data instanceof JSPHP_Runtime_FunctionHeader) {
            return $data->callFunctionWithArgs($this, $args);
        } else {
            throw new Exception("Call to undefined function {$f}");
        }
    }
}
