<?php
class JSPHP_VM {
    private $ops = array ();
    private $processedOps = array ();
    private $opIndex;
    private $stack;
    
    private $labels = array ();
    private $pi = array ();
    private $locs = array ();
    
    private $vars;
    private $varScopeStack;
    private $currentFunction;
    
    function __construct(JSPHP_Runtime $runtime) {
        $this->runtime = $runtime;
        $this->runtime->vm = $this;
    }
    
    /**
     * Get the current line number.
     * @return int
     */
    function currentLine() {
        if ($this->opIndex >= 0 && $this->opIndex < sizeof($this->loc)) {
            list ($line, $file) = $this->loc[$this->opIndex];
            return $line;
        }
    }
    
    /**
     * Get the name of the file that is currently being executed (if known).
     * @return string|null
     */
    function currentFile() {
        if ($this->opIndex >= 0 && $this->opIndex < sizeof($this->loc)) {
            list ($line, $file) = $this->loc[$this->opIndex];
            return $file;
        }
    }
    
    /**
     * Load OpCode into the VM and return the opIndex where it begins.
     * @param array $ops
     * @param string|null $file    Name of the file, to use in error messages etc.
     * @return int
     */
    function loadOpCode(array $ops, $file = null) {
        $opIndex = sizeof($this->ops);
        
        $pi = array ();
        $loc = array (1, $file);
        
        array_unshift($ops, array ('-', "file: {$file}"));
        
        // make sure each block of opcode runs in its own context
        $ops[] = array ('pushnull');
        $ops[] = array ('return');
        foreach ($ops as $op) {
            if ($op[0] == '%label') {
                $this->labels[$op[1]] = sizeof($this->ops);
                $pi[] = $op;
            } else if ($op[0] == '%loc') {
                $loc = array ($op[1], $file);
                $pi[] = $op;
            } else if ($op[0] == '-') {
                $pi[] = $op;
            } else {
                $this->pi[] = $pi;
                $pi = array ();
                $this->ops[] = $op;
                $opName = 'op_' . array_shift($op);
                $this->processedOps[] = array (array ($this, $opName), $op);
                $this->loc[] = $loc;
            }
        }
        if ($pi) {
            $this->pi[] = $pi;
        }
        
        return $opIndex;
    }
    
    /**
     * Load OpCode into the VM and execute it immediately. Returns the return value of the code.
     * @param array $ops
     * @param string|null $file    Name of the file, to use in error messages etc.
     * @return mixed
     */
    function runOpCode(array $ops, $file = null) {
        $opIndex = $this->loadOpCode($ops, $file);
        return $this->runFromOpIndex($opIndex);
    }
    
    /**
     * Run the OpCode at index 0 in the VM. Returns the return value of the code.
     * @return mixed
     */
    function run() {
        return $this->runFromOpIndex(0);
    }
    
    /**
     * Call a function within this VM. $context and $args must contain valid JSPHP_Runtime_* objects.
     * @param JSPHP_Runtime_FunctionHeader $f
     * @param mixed $context
     * @param array $args
     * @return mixed
     */
    function callFunction(JSPHP_Runtime_FunctionHeader $f, $context, array $args) {
        if (!$this->runtime) {
            throw new Exception("VM must have a valid runtime to run in");
        }
        
        // put a HALT opindex on the stack to return to after the loop
        $this->stack[] = $this->opIndex;
        $this->opIndex = -2;
        $this->prepareFunctionCall($f, $context, $args);
        $this->opIndex++;
        $val = $this->runLoop();
        $this->opIndex = array_pop($this->stack);
        return $val;
    }
    
    /**
     * Prepare the VM for a function call, that will be executed when the runLoop continues.
     * @param JSPHP_Runtime_FunctionHeader $f
     * @param mixed $context
     * @param array $args
     * @param bool|null $tailRecursive
     */
    function prepareFunctionCall(JSPHP_Runtime_FunctionHeader $f, $context, array $args, $tailRecursive = false) {
        if ($f instanceof JSPHP_Runtime_PHPFunctionHeader) {
            $val = $f->callFunctionWithArgs($context, $args);
            if ($f->pushesReturnValue) {
                $this->stack[] = $val;
            }
            return;
        } else if (!$f instanceof JSPHP_Runtime_FunctionHeader) {
            $this->error("Function call to non-function");
        }
        if ($f->opIndex == -1) {
            // this is a placeholder function
            $this->stack[] = null;
            return;
        }
        $numArgs = sizeof($args);
        // put the current opindex on the stack
        $this->stack[] = $this->opIndex;
        $maxNumArgs = min($numArgs, $f->numParams);
        for ($i = 0; $i < $maxNumArgs; $i++) {
            $this->stack[] = $args[$i];
        }
        for ($i = $numArgs; $i < $f->numParams; $i++) {
            $this->stack[] = null;
        }
        if ($f->referencesArguments) {
            $this->stack[] = $this->runtime->createArray($args);
        }
        if ($tailRecursive) {
            if ($f === $this->currentFunction) {
                $this->vars->clearLocalVars();
            } else {
                $this->vars = $f->parentVarScope->createSubScope();
            }
        } else {
            $this->varScopeStack[] = $this->vars;
            $this->vars = $f->parentVarScope->createSubScope();
        }
        $this->vars->declareVar('this');
        $this->vars['this'] = $context;
        $this->currentFunction = $f;
        $this->opIndex = $f->opIndex - 1;
    }
    
    /**
     * Get a string containing the OpCode that's loaded into this VM.
     * @return string
     */
    function opCode() {
        $lines = array ();
        foreach ($this->ops as $opIndex => $op) {
            foreach ($this->pi[$opIndex] as $pi) {
                $lines[] = implode(' ', $pi);
            }
            $lines[] = implode(' ', $op);
        }
        if (isset ($this->pi[$opIndex + 1])) {
            foreach ($this->pi[$opIndex + 1] as $pi) {
                $lines[] = implode(' ', $pi);
            }
        }
        return implode("\n", $lines) . "\n";
    }
    
    private function runFromOpIndex($opIndex) {
        if (!$this->runtime) {
            throw new Exception("VM must have a valid runtime to run in");
        }
        $this->opIndex = $opIndex;
        $this->exports = array ();
        $this->vars = $this->runtime->vars;
        $this->vars->declareVar('this');
        $this->vars['this'] = null;
        $this->currentFunction = null;
        $this->varScopeStack = array ();
        $this->stack = array ();
        return $this->runLoop();
    }
    
    private function runLoop() {
        $maxOp = sizeof($this->ops) - 1;
        // why put these in variables? it's faster. it matters: this loop shoud be freaky fast
        $opIndex = $this->opIndex;
        $processedOps = $this->processedOps;
        while ($opIndex >= 0 && $opIndex <= $maxOp) {
            list ($cb, $args) = $processedOps[$opIndex];
            call_user_func_array($cb, $args);
            $opIndex = ++$this->opIndex;
        }
        return array_pop($this->stack);
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
        $this->stack[] = $this->compareValues($a, $b);
    }
    
    function op_neq() {
        $b = array_pop($this->stack);
        $a = array_pop($this->stack);
        $this->stack[] = !$this->compareValues($a, $b);
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
            $this->opIndex = $this->labels[$lbl] - 1;
        }
    }
    
    function op_goto($lbl) {
        $this->opIndex = $this->labels[$lbl] - 1;
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
        $f->numParams = $numParams;
        $f->parentVarScope = $this->vars;
        $f->referencesArguments = $referencesArguments;
        $this->stack[] = $f;
        $this->opIndex = $this->labels[$endLbl] - 1;
    }
    
    function op_callfun() {
        $numArgs = array_pop($this->stack);
        $args = array ();
        for ($i = 0; $i < $numArgs; $i++) {
            array_unshift($args, array_pop($this->stack));
        }
        $f = array_pop($this->stack);
        $context = array_pop($this->stack);
        
        // now call the function
        $this->prepareFunctionCall($f, $context, $args);
    }
    
    function op_callfuntail() {
        $numArgs = array_pop($this->stack);
        $args = array ();
        for ($i = 0; $i < $numArgs; $i++) {
            array_unshift($args, array_pop($this->stack));
        }
        $f = array_pop($this->stack);
        $context = array_pop($this->stack);
        $this->opIndex = array_pop($this->stack);
        
        // now call the function
        $this->prepareFunctionCall($f, $context, $args, true);
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
        } else {
            // JS constructor
            $object = $this->runtime->createObject(null, $constructor);
            $this->stack[] = $object;
            $this->prepareFunctionCall($constructor, $object, $args);
        }
    }
    
    function op_swap() {
        $a = array_pop($this->stack);
        $b = array_pop($this->stack);
        $this->stack[] = $a;
        $this->stack[] = $b;
    }
    
    function op_return() {
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
    
    private function compareValues($a, $b) {
        $aIsObject = is_object($a);
        $bIsObject = is_object($b);
        if ($aIsObject && !$bIsObject) {
            $a = $a instanceof JSPHP_Runtime_Object ? $a->valueOf() : (string)$a;
        } else if (!$aIsObject && $bIsObject) {
            $b = $b instanceof JSPHP_Runtime_Object ? $b->valueOf() : (string)$b;
        } else if ($a instanceof JSPHP_Runtime_PHPObjectWrapper && $b instanceof JSPHP_Runtime_PHPObjectWrapper) {
            return $a->wrappedObject === $b->wrappedObject;
        }
        return $a == $b;
    }
    
    private function error($msg) {
        if ($this->opIndex >= 0 && $this->opIndex < sizeof($this->loc)) {
            list ($line, $file) = $this->loc[$this->opIndex];
            if ($file) {
                $msg = "Error on line {$line} of file {$file}: {$msg}";
            } else {
                $msg = "Error on line {$line}: {$msg}";
            }
        }
        $this->opIndex = -1;
        $this->stack = array ();
        throw new Exception($msg);
    }
}
