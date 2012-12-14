<?php
require_once 'JSPHP/Runtime/Object.php';
require_once 'JSPHP/Runtime/Array.php';
require_once 'JSPHP/Runtime/VarScope.php';
require_once 'JSPHP/Runtime/FunctionHeader.php';
require_once 'JSPHP/Runtime/PHPFunctionHeader.php';
require_once 'JSPHP/Runtime/PHPObjectWrapper.php';
require_once 'JSPHP/Runtime/Common/JSPHPObject.php';
require_once 'JSPHP/Runtime/Common/MathObject.php';
require_once 'JSPHP/Runtime/Common/ObjectPrototype.php';
require_once 'JSPHP/Runtime/Common/StringPrototype.php';

class JSPHP_Runtime {
    public $vars;
    public $vm;
    public $environment;
    
    function __construct() {
        $this->vars = new JSPHP_Runtime_VarScope();
        $this->setupCommonVars();
        $this->setupJSPHPVars();
    }
    
    protected function initEnvironment() {
        if ($this->environment) {
            return;
        }
        require_once 'JSPHP/Environment.php';
        $this->environment = new JSPHP_Environment();
        $this->environment->initComponents();
    }
    
    function setupCommonVars() {
        /**
         * Set up the intricate structure where:
         * - All objects are instanceof Object (by setting $objConstructor->isObjectConstructor = true)
         * - All functions are instanceof Function, including Function itself
         * - Object instanceof Function
         */
        $objConstructor = new JSPHP_Runtime_FunctionHeader();
        $this->vars['Object'] = $objConstructor;
        $objConstructor['prototype'] = $this->createObjectWrapper(new JSPHP_Runtime_Common_ObjectPrototype(), $objConstructor);
        
        $functionConstructor = new JSPHP_Runtime_FunctionHeader();
        $objConstructor->setConstructor($functionConstructor);
        $functionConstructor->setConstructor($functionConstructor);
        $functionConstructor->setPrototype($this->createObject());
        $this->vars['Function'] = $functionConstructor;
        $functionPrototype = $functionConstructor['prototype'] = $this->createObject();
        
        /**
         * Set up all the other machinery
         */
        $functionConstructor['prototype']['call'] = $this->createPHPFunction(array ($this, 'runtimeFunctionCall'));
        $functionConstructor['prototype']['apply'] = $this->createPHPFunction(array ($this, 'runtimeFunctionApply'));
        
        $this->vars['Array'] = $this->createFunction();
        $this->vars['String'] = $this->createPHPFunction(array ($this, 'createString'));
        $this->vars['String']['prototype'] = $this->createObjectWrapper(new JSPHP_Runtime_Common_StringPrototype(), $objConstructor);
        
        $this->vars['Number'] = $this->createPHPFunction(array ($this, 'createNumber'));
        $this->vars['Number']['prototype'] = $this->createObject();
        $this->vars['Boolean'] = $this->createPHPFunction(array ($this, 'createBoolean'));
        $this->vars['Boolean']['prototype'] = $this->createObject();
        
        $this->vars['Math'] = $this->createObjectWrapper(new JSPHP_Runtime_Common_MathObject(), $objConstructor);
        
        $this->vars['eval'] = $this->createPHPFunction(array ($this, 'runtimeEval'));
    }
    
    function newVarScope() {
        return new JSPHP_Runtime_VarScope($this->vars);
    }
    
    function runtimeFunctionCall() {
        $args = func_get_args();
        $f = array_shift($args);
        $context = array_shift($args);
        return $this->vm->callFunction($f, $context, $args);
    }
    
    function runtimeFunctionApply($f, $context, $args = null) {
        if ($args instanceof JSPHP_Runtime_Array) {
            $args = $args->getOwnValues();
        } else if ($args === null) {
            $args = array ();
        } else if(!is_array($args)) {
            throw new Exception("Argument 2 of .apply should be an array");
        }
        return $this->vm->callFunction($f, $context, $args);
    }
    
    function runtimeEval($context, $code) {
        $label = substr(md5("eval({$code})"), 0, 12);
        if (!$block = $this->vm->cacheGetOpCodeBlock($label)) {
            $this->initEnvironment();
            $tree = $this->environment->parser->parseJS($code);
            $ops = $this->environment->compiler->compile($tree);
            $desc = "eval'ed code";
            if ($line = $this->vm->currentLine()) {
                $desc .= " on line {$line}";
                if ($fileName = $this->vm->currentFile()) {
                    $desc .= " of {$fileName}";
                }
            }
            $block = $this->vm->loadOpCodeForEval($ops, $desc);
            $this->vm->cacheSetOpCodeBlock($label, $block);
        }
        return $this->vm->runBlockInCurrentScope($block);
    }
    
    function runtimeRequire($context, $path) {
        $this->initEnvironment();
        return $this->environment->runFile($path);
    }
    
    function setupJSPHPVars() {
        $jsPHPObject = new JSPHP_Runtime_Common_JSPHPObject($this);
        $this->vars['jsphp'] = $this->createObjectWrapper($jsPHPObject, $this->vars['Object']);
        $this->vars['jsphp']['require'] = $this->createPHPFunction(array ($this, 'runtimeRequire'), false);
    }
    
    /**
     * Import data from outside JSPHP into JSPHP.
     */
    function importData($data) {
        if ($data === null) {
            return null;
        } else if (is_object($data)) {
            return $this->createObjectWrapper($data);
        } else if (is_array($data)) {
            $out = $this->createArray();
            foreach ($data as $v) {
                $out[] = $this->importData($v);
            }
            return $out;
        } else if (is_resource($data)) {
            throw new Exception("Can't pass resources to JSPHP VM");
        } else {
            return $data;
        }
    }
    
    function createString($context, $str) {
        return (string)$str;
    }
    
    function createNumber($context, $val) {
        return 0 + $val;
    }
    
    function createBoolean($context, $val) {
        return (bool)$val;
    }
    
    function createArray(array $values = null) {
        $arrConstructor = $this->vars['Array'];
        $arr = new JSPHP_Runtime_Array($arrConstructor);
        if ($values) {
            $arr->setArrayValues($values);
        }
        return $arr;
    }
    
    function createObject(array $values = null, $constructor = null) {
        if ($constructor === null) {
            $constructor = $this->vars['Object'];
        }
        $obj = new JSPHP_Runtime_Object($constructor);
        if ($values) {
            $obj->setObjectValues($values);
        }
        return $obj;
    }
    
    function createFunction() {
        $f = new JSPHP_Runtime_FunctionHeader($this->vars['Function']);
        $f['prototype'] = $this->createObject();
        $f->runtime = $this;
        return $f;
    }
    
    function createPHPFunction($callback) {
        $f = new JSPHP_Runtime_PHPFunctionHeader($this->vars['Function'], $callback);
        $f['prototype'] = $this->createObject();
        return $f;
    }
    
    function createObjectWrapper($obj, $constructor = null) {
        if ($constructor === null) {
            $constructor = $this->vars['Object'];
        }
        $wrapper = new JSPHP_Runtime_PHPObjectWrapper($obj, $constructor);
        $wrapper->runtime = $this;
        return $wrapper;
    }
}