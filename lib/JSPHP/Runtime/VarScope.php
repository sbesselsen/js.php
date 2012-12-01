<?php
class JSPHP_Runtime_VarScope implements ArrayAccess {
    public $parentScope;
    private $values = array ();
    
    function __construct($parentScope = null) {
        $this->parentScope = $parentScope;
    }
    
    function declareVar($k) {
        if (!array_key_exists($k, $this->values)) {
            $this->values[$k] = null;
        }
    }
    
    function clearLocalVars() {
        $this->values = array ();
    }
    
    function offsetExists($k) {
        return array_key_exists($k, $this->values) 
            || (isset ($this->parentScope) && $this->parentScope->offsetExists($k));
    }
    
    function offsetGet($k) {
        if (array_key_exists($k, $this->values)) {
            return $this->values[$k];
        } else if ($this->parentScope) {
            return $this->parentScope->offsetGet($k);
        } else {
            return null;
        }
    }
    
    function offsetSet($k, $v) {
        if (!array_key_exists($k, $this->values) && isset ($this->parentScope) && $this->parentScope->offsetExists($k)) {
            $this->parentScope->offsetSet($k, $v);
        } else {
            $this->values[$k] = $v;
        }
    }
    
    function offsetUnset($k) {
        unset ($this->values[$k]);
    }
    
    /**
     * Create a new VarScope that inherits vars from this scope.
     * @return JSPHP_Runtime_VarScope
     */
    function createSubScope() {
        return new JSPHP_Runtime_VarScope($this);
    }
}
