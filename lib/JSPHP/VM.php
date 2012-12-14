<?php
require_once 'JSPHP/VM/Exception.php';
require_once 'JSPHP/VM/OpCodeBlock.php';
require_once 'JSPHP/VM/Evaluator.php';

class JSPHP_VM {
    public $runtime;
    public $currentEvaluator;
    
    /**
     * A cache of opCode blocks to prevent duplicate loading of files or eval ops.
     * @var array
     */
    private $opCodeBlockCache = array ();
    
    function __construct(JSPHP_Runtime $runtime) {
        $this->runtime = $runtime;
        $this->runtime->vm = $this;
    }
    
    function currentLine() {
        return $this->currentEvaluator->currentLine();
    }
    
    function currentFile() {
        return $this->currentEvaluator->currentFile();
    }
    
    /**
     * Load OpCode and return a reference to an executable OpCodeBlock.
     * @param array $ops
     * @param string|null $file    Name of the file, to use in error messages etc.
     * @return JSPHP_VM_OpCodeBlock
     */
    function loadOpCode(array $ops, $file = null) {
        return new JSPHP_VM_OpCodeBlock($file, $ops);
    }
    
    /**
     * Load OpCode for in-line evaluation and return a reference to an executable OpCodeBlock.
     * @param array $ops
     * @param string|null $file    Name of the file, to use in error messages etc.
     * @return JSPHP_VM_OpCodeBlock
     */
    function loadOpCodeForEval(array $ops, $file = null) {
        for ($i = sizeof($ops) - 1; $i >= 0; $i--) {
            if ($ops[$i][0] == 'return') {
                break;
            }
            if ($ops[$i][0] == 'pop') {
                $ops[$i] = array ('return');
                break;
            }
        }
        return $this->loadOpCode($ops, $file);
    }
    
    /**
     * Run a block of OpCode as a module, and return its return value.
     * @param JSPHP_VM_OpCodeBlock $block
     * @param int $opIndex
     * @return mixed
     */
    function runBlockAsModule(JSPHP_VM_OpCodeBlock $opCodeBlock, $opIndex = 0) {
        $ev = new JSPHP_VM_Evaluator($this, $opCodeBlock);
        $ev->opIndex = $opIndex;
        $ev->vars = $this->runtime->newVarScope();
        return $this->runEvaluator($ev);
    }
    
    /**
     * Run a block of OpCode as an eval statement, in the current var scope, and return its return value.
     * @param JSPHP_VM_OpCodeBlock $block
     * @param int $opIndex
     * @return mixed
     */
    function runBlockInCurrentScope(JSPHP_VM_OpCodeBlock $opCodeBlock, $opIndex = 0) {
        $ev = new JSPHP_VM_Evaluator($this, $opCodeBlock);
        $ev->opIndex = $opIndex;
        if ($this->currentEvaluator) {
            $ev->vars = $this->currentEvaluator->vars;
        } else {
            $ev->vars = $this->runtime->newVarScope();
        }
        return $this->runEvaluator($ev);
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
        
        if ($f instanceof JSPHP_Runtime_PHPFunctionHeader) {
            return $f->callFunctionWithArgs($context, $args);
        }
        
        if ($f->opIndex < 0 || !$f->opCodeBlock) {
            // this is an empty function header
            // should this be an error?
            return null;
        }
        
        $ev = new JSPHP_VM_Evaluator($this, $f->opCodeBlock);
        $ev->opIndex = $f->opIndex;
        $ev->vars = $f->parentVarScope->createSubScope();
        
        // put arguments on the stack
        $numArgs = sizeof($args);
        $maxNumArgs = min($numArgs, $f->numParams);
        for ($i = 0; $i < $maxNumArgs; $i++) {
            $ev->stack[] = $args[$i];
        }
        for ($i = $numArgs; $i < $f->numParams; $i++) {
            $ev->stack[] = null;
        }
        if ($f->referencesArguments) {
            $ev->stack[] = $this->runtime->createArray($args);
        }
        $ev->vars['this'] = $context;
        
        return $this->runEvaluator($ev);
    }
    
    private function runEvaluator(JSPHP_VM_Evaluator $ev) {
        $stackedEv = $this->currentEvaluator;
        $this->currentEvaluator = $ev;
        $out = $ev->evaluate();
        $this->currentEvaluator = $stackedEv;
        return $out;
    }
    
    function compareValues($a, $b) {
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
    
    /**
     * Try to get an OpCodeBlock from the cache.
     * @param string $k
     * @return JSPHP_VM_OpCodeBlock|null
     */
    function cacheGetOpCodeBlock($k) {
        return isset ($this->opCodeBlockCache[$k]) ? $this->opCodeBlockCache[$k] : null;
    }
    
    /**
     * Store an OpCodeBlock in the cache.
     * @param string $k
     * @param JSPHP_VM_OpCodeBlock $block
     */
    function cacheSetOpCodeBlock($k, JSPHP_VM_OpCodeBlock $block) {
        $this->opCodeBlockCache[$k] = $block;
    }
}
