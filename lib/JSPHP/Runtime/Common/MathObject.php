<?php
class JSPHP_Runtime_Common_MathObject {
    public $E = M_E;
    public $LN2 = M_LN2;
    public $LN10 = M_LN10;
    public $LOG2E = M_LOG2E;
    public $LOG10E = M_LOG10E;
    public $PI = M_PI;
    public $SQRT1_2 = M_SQRT1_2;
    public $SQRT2 = M_SQRT2;
    
    function abs($x) {
        return abs($x);
    }
    
    function acos($x) {
        return acos($x);
    }
    
    function asin($x) {
        return asin($x);
    }
    
    function atan($x) {
        return atan($x);
    }
    
    function atan2($y, $x) {
        return atan2($y, $x);
    }
    
    function ceil($x) {
        return ceil($x);
    }
    
    function cos($x) {
        return cos($x);
    }
    
    function exp($x) {
        return pow(M_E, $x);
    }
    
    function floor($x) {
        return floor($x);
    }
    
    function log($x) {
        return log($x);
    }
    
    function max() {
        $args = func_get_args();
        return max($args);
    }
    
    function min() {
        $args = func_get_args();
        return min($args);
    }
    
    function pow($base, $exp) {
        return pow($base, $exp);
    }
    
    function random() {
        return mt_rand();
    }
    
    function round($x) {
        return round($x);
    }
    
    function sin($x) {
        return sin($x);
    }
    
    function sqrt($x) {
        return sqrt($x);
    }
    
    function tan($x) {
        return tan($x);
    }
}
