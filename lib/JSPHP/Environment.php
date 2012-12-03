<?php
class JSPHP_Environment {
    /**
     * @var bool
     */
    protected $componentsInitialized = false;
    
    /**
     * @var JSPHP_IParser
     */
    public $parser;
    
    /**
     * @var JSPHP_ICompiler
     */
    public $compiler;
    
    /**
     * @var JSPHP_Runtime
     */
    public $runtime;
    
    /**
     * @var JSPHP_VM
     */
    public $vm;
    
    /**
     * @var string
     */
    public $currentFile;
    
    function resetEnvironment() {
        $this->runtime = null;
        $this->componentsInitialized = false;
    }
    
    /**
     * Load default components if they have not been supplied.
     */
    function initComponents() {
        if ($this->componentsInitialized) {
            return;
        }
        $this->componentsInitialized = true;
        
        if (!$this->parser) {
            require_once 'JSPHP/Parser.php';
            $this->parser = new JSPHP_Parser();
        }
        if (!$this->compiler) {
            require_once 'JSPHP/Compiler.php';
            $this->compiler = new JSPHP_Compiler();
        }
        if (!$this->runtime) {
            require_once 'JSPHP/Runtime.php';
            $this->runtime = new JSPHP_Runtime();
            $this->runtime->environment = $this;
        }
        if (!$this->runtime->vm) {
            require_once 'JSPHP/VM.php';
            $this->runtime->vm = new JSPHP_VM($this->runtime);
        }
    }
    
    /**
     * Load a file and run its code.
     * @param string $path
     */
    function runFile($path) {
        return $this->injectOpCode($path, 'runOpCode');
    }
    
    /**
     * Load a file and return an opIndex.
     * @param string $path
     * @return int
     */
    function loadFile($path) {
        return $this->injectOpCode($path, 'loadOpCode');
    }
    
    /**
     * Load opCode into the VM and call the specified function on the VM.
     * @param string $path
     * @param string $f
     * @return int
     */
    protected function injectOpCode($path, $call) {
        if (!$this->componentsInitialized) {
            $this->initComponents();
        }
        
        $parentFile = $this->currentFile;
        if ($parentFile) {
            $path = $this->absolutePath($path, dirname($parentFile));
        } else {
            $path = realpath($path);
        }
        
        $this->currentFile = $path;
        try {
            if (!$path || !file_exists($path)) {
                throw new Exception("Can't read file {$path}");
            }
            $data = file_get_contents($path);
            
            $tree = $this->parser->parseJS($data);
            $ops = $this->compiler->compile($tree);
            
            $out = $this->runtime->vm->$call($ops, $path);
            
            $this->currentFile = $parentFile;
        } catch (Exception $e) {
            $this->currentFile = $parentFile;
            throw $e;
        }
        
        return $out;
    }
    
    protected function absolutePath($path, $dir = null) {
        if ($absolutePath = realpath($path)) {
            return $absolutePath;
        }
        return realpath($dir . DIRECTORY_SEPARATOR . $path);
    }
}

/*


$p = new JSPHP_Parser();
$file = file_get_contents("{$dir}/test.js");

$parsed = $p->parseJS($file);

$c = new JSPHP_Compiler();
$ops = $c->compile($parsed);

$runtime = new JSPHP_Runtime();

$vm = new JSPHP_VM($runtime);
var_dump($vm->runOpCode($ops, 'test.js'));
*/