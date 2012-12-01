<?php
interface JSPHP_Optimizer_IOptimization {
    /**
     * Optimize the OpCode.
     * @param array $ops
     * @return array
     */
    function optimize(array $ops);
}