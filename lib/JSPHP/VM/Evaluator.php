<?php
class JSPHP_VM_Evaluator {
    public $stack = array ();
    
    public $vm;
    public $runtime;
    
    public $opIndex = 0;
    public $vars;
    
    private $exports;
    
    private $opCodeBlock;
    private $ops = array ();
    
    private $catchStack = array ();
    
    function __construct(JSPHP_VM $vm, JSPHP_VM_OpCodeBlock $opCodeBlock) {
        $this->vm = $vm;
        $this->runtime = $vm->runtime;
        $this->opCodeBlock = $opCodeBlock;
        $this->ops = $this->opCodeBlock->processedOps();
    }
    
    function currentLine() {
        return $this->opCodeBlock->lineNumberForOpIndex($this->opIndex);
    }
    
    function currentFile() {
        return $this->opCodeBlock->fileName();
    }
    
    function addExports(JSPHP_Runtime_Object $obj) {
        foreach ($obj as $k => $v) {
            $this->exports[$k] = $v;
        }
    }
    
    function exports() {
        return $this->exports;
    }
    
    function evaluate() {
        $this->exports = $this->runtime->createObject();
        
        while (true) { // while loop for exception handling
            try {
                // why put these in variables? it's faster. it matters: this loop shoud be freaky fast
                $opIndex = $this->opIndex;
                $ops =& $this->ops;
                while ($opIndex >= 0) {
                    list ($name, $args) = $ops[$opIndex];
                    if ($name == 'return') {
                        break;
                    }
                    $cb = array ($this, 'op_' . $name);
                    call_user_func_array($cb, $args);
                    $opIndex = ++$this->opIndex;
                }
                return array_pop($this->stack);
            } catch (Exception $e) {
                if (!$this->catchStack) {
                    throw $e;
                }
                if ($e instanceof JSPHP_VM_Exception) {
                    $exceptionObject = $e->exceptionObject;
                } else {
                    $exceptionObject = $this->runtime->createObject(array (
                        'name' => get_class($e),
                        'message' => $e->getMessage(),
                    ));
                }
                $catchLabel = array_pop($this->catchStack);
                $this->vars['e'] = $exceptionObject;
                $this->opIndex = $this->opCodeBlock->opIndexForLabel($catchLabel);
            }
        }
    }
    
    function op_throwex() {
        $ex = array_pop($this->stack);
        $this->stack = array ();
        $msg = 'Exception';
        if ($ex instanceof JSPHP_Runtime_Object) {
            if (isset ($ex['name'])) {
                $msg = "{$ex['name']}";
                if (isset ($ex['message'])) {
                    $msg .= ": {$ex['message']}";
                }
            }
        }
        $this->error($msg, $ex);
    }
    
    function op_pushcatchex($label) {
        $this->catchStack[] = $label;
    }
    
    function op_popcatchex() {
        array_pop($this->catchStack);
    }
    
    function op_declare($var) {
        $this->vars->declareVar($var);
    }
    
    function op_delete($var) {
        unset ($this->vars[$var]);
    }
    
    function op_pushnum($val) {
        $this->stack[] = 0 + $val;
    }
    
    function op_pushnull() {
        $this->stack[] = null;
    }
    
    function op_pushbool($v) {
        $this->stack[] = (bool)$v;
    }
    
    function op_pushstr($v) {
        $this->stack[] = $v;
    }
    
    function op_mul() {
        $this->stack[] = array_pop($this->stack) * array_pop($this->stack);
    }
    
    function op_div() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $a / $b;
    }
    
    function op_add() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        if (is_string($b) || is_string($a)) {
            $this->stack[] = $a . $b;
        } else {
            $this->stack[] = $a + $b;
        }
    }
    
    function op_sub() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $a - $b;
    }
    
    function op_les() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $a < $b;
    }
    
    function op_leq() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $a <= $b;
    }
    
    function op_gre() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $a > $b;
    }
    
    function op_greq() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $a >= $b;
    }
    
    function op_eq() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $this->vm->compareValues($a, $b);
    }
    
    function op_neq() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = !$this->vm->compareValues($a, $b);
    }
    
    function op_eeq() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $a === $b;
    }
    
    function op_neeq() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $a !== $b;
    }
    
    function op_not() {
        $this->stack[] = !array_pop($this->stack);
    }
    
    function op_mod() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = $a % $b;
    }
    
    function op_pushvar($var) {
        if (!isset ($this->vars[$var])) {
            $this->stack[] = null;
        } else {
            $this->stack[] = $this->vars[$var];
        }
    }
    
    function op_gotoif($lbl) {
        if (array_pop($this->stack)) {
            $this->opIndex = $this->opCodeBlock->opIndexForLabel($lbl) - 1;
        }
    }
    
    function op_goto($lbl) {
        $this->opIndex = $this->opCodeBlock->opIndexForLabel($lbl) - 1;
    }
    
    function op_pop() {
        array_pop($this->stack);
    }
    
    function op_assign($var) {
        if (!isset ($this->vars[$var])) {
            $this->error("Assigning to undeclared variable {$var}");
        }
        // don't pop, keep the value on the stack
        $this->vars[$var] = $this->stack[sizeof($this->stack) - 1];
    }
    
    function op_dup($num = 1) {
        for ($j = 0; $j < $num; $j++) {
            $this->stack[] = $this->stack[sizeof($this->stack) - $num];
        }
    }
    
    function op_pusharray() {
        $this->stack[] = $this->runtime->createArray();
    }
    
    function op_arraypush() {
        $val = array_pop($this->stack);
        $arr =& $this->stack[sizeof($this->stack) - 1];
        $arr[] = $val;
    }
    
    function op_pushobject() {
        $this->stack[] = $this->runtime->createObject();
    }
    
    function op_objectpush() {
        $val = array_pop($this->stack);
        $k = array_pop($this->stack);
        $obj = $this->stack[sizeof($this->stack) - 1];
        $obj[$k] = $val;
    }
    
    function op_objectget() {
        $k = array_pop($this->stack);
        $obj = array_pop($this->stack);
        if (is_string($obj)) {
            if ($k == 'length') {
                $this->stack[] = iconv_strlen($obj, 'UTF-8');
            } else if ($k == 'constructor') {
                $this->stack[] = $this->vars['String'];
            } else {
                $this->stack[] = $this->vars['String']['prototype'][$k];
            }
        } else if ($obj instanceof JSPHP_Runtime_Object) {
            $this->stack[] = $obj[$k];
        } else {
            $this->error("Trying to get property {$k} of non-object");
        }
    }
    
    function op_objectset() {
        $val = array_pop($this->stack);
        $k = array_pop($this->stack);
        $obj = array_pop($this->stack);
        if (!$obj instanceof JSPHP_Runtime_Object) {
            $this->error("Trying to set property {$k} of non-object");
        }
        $obj[$k] = $val;
        $this->stack[] = $val;
    }
    
    function op_objectdelete() {
        $k = array_pop($this->stack);
        $obj = array_pop($this->stack);
        if (!$obj instanceof JSPHP_Runtime_Object) {
            $this->error("Trying to delete property {$k} of non-object");
        }
        unset ($obj[$k]);
        $this->stack[] = true;
    }
    
    function op_deffun($numParams, $referencesArguments, $endLbl) {
        $f = $this->runtime->createFunction();
        $f->opIndex = $this->opIndex + 1;
        $f->opCodeBlock = $this->opCodeBlock;
        $f->numParams = $numParams;
        $f->parentVarScope = $this->vars;
        $f->referencesArguments = $referencesArguments;
        $this->stack[] = $f;
        $this->opIndex = $this->opCodeBlock->opIndexForLabel($endLbl) - 1;
    }
    
    function op_callfun() {
        $numArgs = array_pop($this->stack);
        $args = array ();
        for ($i = 0; $i < $numArgs; $i++) {
            array_unshift($args, array_pop($this->stack));
        }
        $f = array_pop($this->stack);
        $context = array_pop($this->stack);
        if (!$f instanceof JSPHP_Runtime_FunctionHeader) {
            $this->error("Function call to non-function");
        }
        $this->stack[] = $this->vm->callFunction($f, $context, $args);
    }
    
    function op_callconstr() {
        $numArgs = array_pop($this->stack);
        $args = array ();
        for ($i = 0; $i < $numArgs; $i++) {
            array_unshift($args, array_pop($this->stack));
        }
        $constructor = array_pop($this->stack);
        
        if (!$constructor instanceof JSPHP_Runtime_FunctionHeader) {
            $this->error("Can't create object from non-function");
        }
        
        if ($constructor === $this->vars['String']) {
            $this->stack[] = isset ($args[0]) ? (string)$args[0] : '';
            $this->stack[] = null;
            return;
        } else if ($constructor === $this->vars['Array']) {
            $this->stack[] = $this->runtime->createArray($args);
            $this->stack[] = null;
        } else if ($constructor === $this->vars['Number'] || 
                   $constructor === $this->vars['Boolean']) {
            $object = $this->runtime->createObject(null, $constructor);
            if (isset ($args[0])) {
                $object->primitiveValue = $args[0];
            }
            $this->stack[] = $object;
            $this->stack[] = null;
        } else if ($constructor === $this->vars['RegExp']) {
            $object = $this->runtime->createRegExp(isset ($args[0]) ? $args[0] : '', isset ($args[1]) ? $args[1] : null);
            $this->stack[] = $object;
            $this->stack[] = null;
        } else {
            // JS constructor
            $object = $this->runtime->createObject(null, $constructor);
            $this->stack[] = $object;
            $this->stack[] = $this->vm->callFunction($constructor, $object, $args);
        }
    }
    
    function op_swap() {
        $a = array_pop($this->stack);
        $b = array_pop($this->stack);
        $this->stack[] = $a;
        $this->stack[] = $b;
    }
    
    function op_return() {
        // TODO
        $retval = array_pop($this->stack);
        $opIndex = array_pop($this->stack);
        if ($opIndex !== null) {
            $this->opIndex = $opIndex;
        } else {
            $this->opIndex = -2; // HALT
        }
        $this->stack[] = $retval;
        $this->vars = array_pop($this->varScopeStack);
    }
    
    function op_iterator() {
        $val = array_pop($this->stack);
        if (!$val instanceof IteratorAggregate) {
            $iterator = new ArrayIterator(array ());
        } else {
            $iterator = $val->getIterator();
        }
        $iterator->rewind();
        $this->stack[] = $iterator;
    }
    
    function op_itervalid() {
        $iterator = $this->stack[sizeof($this->stack) - 1];
        $this->stack[] = $iterator->valid();
    }
    
    function op_iterkey() {
        $iterator = $this->stack[sizeof($this->stack) - 1];
        $this->stack[] = $iterator->key();
    }
    
    function op_iternext() {
        $iterator = $this->stack[sizeof($this->stack) - 1];
        $iterator->next();
    }
    
    function op_instanceof() {
        $type = array_pop($this->stack);
        $obj = array_pop($this->stack);
        if (!$obj instanceof JSPHP_Runtime_Object || !$type instanceof JSPHP_Runtime_FunctionHeader) {
            $this->stack[] = false;
        } else {
            $this->stack[] = $obj->isPrototypalInstanceOf($type);
        }
    }
    
    function op_in() {
        $obj = array_pop($this->stack);
        $key = array_pop($this->stack);
        if (!$obj instanceof JSPHP_Runtime_Object) {
            $this->stack[] = false;
        } else {
            $this->stack[] = isset ($obj[$key]);
        }
    }
    
    function op_typeof() {
        $obj = array_pop($this->stack);
        if ($obj === null || $obj instanceof JSPHP_Runtime_Object) {
            $this->stack[] = 'object';
        } else if (is_string($obj)) {
            $this->stack[] = 'string';
        } else if (is_bool($obj)) {
            $this->stack[] = 'boolean';
        } else if (is_numeric($obj)) {
            $this->stack[] = 'number';
        }
    }
    
    /**
     * Declare variable $k, pop an item off the stack, and assign it to $k.
     * @param string $k
     */
    function op_unpackarg($k) {
        $val = array_pop($this->stack);
        $this->vars->declareVar($k);
        $this->vars[$k] = $val;
    }
    
    function error($msg, $exceptionObject = null) {
        $fileName = $this->opCodeBlock->fileName();
        if ($lineNumber = $this->currentLine()) {
            $msg .= " on line {$lineNumber}";
            if ($fileName) {
                $msg .= " of {$fileName}";
            }
        }
        throw new JSPHP_VM_Exception($msg, $fileName, $lineNumber, $exceptionObject);
    }
}
