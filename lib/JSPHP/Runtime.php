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
    public $evalParser;
    public $evalCompiler;
    
    protected $exports = array ();
    protected $commonVars;
    
    protected $cachedEvalOpCode = array ();
    
    function __construct() {
        $this->commonVars = new JSPHP_Runtime_VarScope();
        $this->vars = new JSPHP_Runtime_VarScope($this->commonVars);
        $this->setupCommonVars();
        $this->setupJSPHPVars();
    }
    
    function setupCommonVars() {
        $objConstructor = new JSPHP_Runtime_FunctionHeader();
        $this->commonVars['Object'] = $objConstructor;
        $objConstructor['prototype'] = $this->createObjectWrapper(new JSPHP_Runtime_Common_ObjectPrototype(), $objConstructor);
        
        $functionConstructor = new JSPHP_Runtime_FunctionHeader();
        $this->commonVars['Function'] = $functionConstructor;
        $functionPrototype = $functionConstructor['prototype'] = $this->createObject();
        $functionConstructor['prototype']['call'] = $this->createPHPFunction(array ($this, 'runtimeFunctionCall'), false);
        $functionConstructor['prototype']['apply'] = $this->createPHPFunction(array ($this, 'runtimeFunctionApply'), false);
        
        $this->commonVars['Array'] = $this->createFunction();
        $this->commonVars['String'] = $this->createPHPFunction(array ($this, 'createString'));
        $this->commonVars['String']['prototype'] = $this->createObjectWrapper(new JSPHP_Runtime_Common_StringPrototype(), $objConstructor);
        
        $this->commonVars['Number'] = $this->createPHPFunction(array ($this, 'createNumber'));
        $this->commonVars['Number']['prototype'] = $this->createObject();
        $this->commonVars['Boolean'] = $this->createPHPFunction(array ($this, 'createBoolean'));
        $this->commonVars['Boolean']['prototype'] = $this->createObject();
        
        $this->commonVars['Math'] = $this->createObjectWrapper(new JSPHP_Runtime_Common_MathObject(), $objConstructor);
        
        $this->commonVars['eval'] = $this->createPHPFunction(array ($this, 'runtimeEval'), false);
    }
    
    function runtimeFunctionCall() {
        $args = func_get_args();
        $f = array_shift($args);
        $context = array_shift($args);
        $this->vm->prepareFunctionCall($f, $context, $args);
    }
    
    function runtimeFunctionApply($f, $context, $args = null) {
        if ($args instanceof JSPHP_Runtime_Array) {
            $args = $args->getOwnValues();
        } else if ($args === null) {
            $args = array ();
        } else if(!is_array($args)) {
            throw new Exception("Argument 2 of .apply should be an array");
        }
        $this->vm->prepareFunctionCall($f, $context, $args);
    }
    
    function runtimeEval($context, $code) {
        $label = substr(md5("eval({$code})"), 0, 12);
        if (isset ($this->cachedEvalOpCode[$label])) {
            $ops = $this->cachedEvalOpCode[$label];
        } else {
            if (!$this->evalParser) {
                require_once 'JSPHP/Parser.php';
                $this->evalParser = new JSPHP_Parser();
            }
            if (!$this->evalCompiler) {
                require_once 'JSPHP/Compiler.php';
                $this->evalCompiler = new JSPHP_Compiler();
            }
            $tree = $this->evalParser->parseJS($code);
            $ops = $this->evalCompiler->compile($tree);
            $this->cachedEvalOpCode[$label] = $ops;
        }
        $this->vm->evalOpCode($ops, $label);
    }
    
    function setupJSPHPVars() {
        $jsPHPObject = new JSPHP_Runtime_Common_JSPHPObject($this);
        $this->commonVars['jsphp'] = $this->createObjectWrapper($jsPHPObject, $this->vars['Object']);
    }
    
    /**
     * Add exported functions at the request of JS code.
     * @param JSPHP_Runtime_Object $functions
     */ 
    function addExportedFunctions(JSPHP_Runtime_Object $functions) {
        foreach ($functions as $name => $f) {
            $this->exports[$name] = $f;
        }
    }
    
    /**
     * Call a function that has been exported from the JS environment.
     * @param string $name
     * @param array $args
     * @return mixed
     */
    function callExportedFunction($name, $args) {
        if (!isset ($this->exports[$name])) {
            throw new Exception("Function not exported: {$name}");
        }
        if (!$this->exports[$name] instanceof JSPHP_Runtime_FunctionHeader) {
            throw new Exception("Export is not a function: {$name}");
        }
        $f = $this->exports[$name];
        
        // import args
        $args = array_map(array ($this, 'importData'), $args);
        
        return $this->vm->callFunction($f, null, $args);
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
    
    function createPHPFunction($callback, $pushesReturnValue = true) {
        $f = new JSPHP_Runtime_PHPFunctionHeader($this->vars['Function'], $callback, $pushesReturnValue);
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