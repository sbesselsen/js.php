<?php
class JSPHP_Optimizer {
    /**
     * Optimizers to run over the generated OpCode.
     * @var array (JSPHP_Optimizer_IOptimization)
     */
    protected $optimizations = array ();
    
    /**
     * Add an optimization step.
     * @param JSPHP_Optimizer_IOptimization $optimization
     * @return JSPHP_Compiler
     */
    function addOptimizations(JSPHP_Optimizer_IOptimization $optimization) {
        $this->optimizations[] = $optimization;
        return $this;
    }
    
    /**
     * Optimize the OpCode by running it through all optimizations.
     * @param array $ops
     * @return array
     */
    function optimize(array $ops) {
        foreach ($this->optimizations as $optimization) {
            $ops = $optimization->optimize($ops);
        }
        return $ops;
    }
}