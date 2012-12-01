<?php
class JSPHP_Runtime_Common_StringPrototype {
    function toLowerCase__onObject($str) {
        return mb_strtolower($str, 'UTF-8');
    }
    function toUpperCase__onObject($str) {
        return mb_strtoupper($str, 'UTF-8');
    }
    function substring__onObject($str, $from, $to = null) {
        if ($to === null) {
            return iconv_substr($str, $from, iconv_strlen($str, 'UTF-8'), 'UTF-8');
        } else {
            return iconv_substr($str, $from, $to - $from, 'UTF-8');
        }
    }
    function valueOf__onObject($str) {
        return $str;
    }
}