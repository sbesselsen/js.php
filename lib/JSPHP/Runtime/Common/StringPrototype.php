<?php
/**
 * charCodeAt() and fromCharCode() are based on code from Scott Reynen.
 * See: http://randomchaos.com/documents/?source=php_and_unicode
 */
class JSPHP_Runtime_Common_StringPrototype {
    function charAt__onObject($str, $n) {
        return $this->substring__onObject($str, $n, $n + 1);
    }
    function charCodeAt__onObject($str, $n) {
        $char = $this->charAt__onObject($str, $n);
        
        $values = array ();
        $lookingFor = 1;
        
        for ($i = 0; $i < strlen($char); $i++) {
            $thisValue = ord($char{$i});
            if ($thisValue < 128) {
                return $thisValue;
            }
            if (count($values) == 0) {
                $lookingFor = ( $thisValue < 224 ) ? 2 : 3;
            }
            $values[] = $thisValue;
            if (count($values) == $lookingFor) {
                if ($lookingFor == 3) {
                    return (($values[0] % 16) * 4096) 
                        + (($values[1] % 64) * 64) 
                        + ($values[2] % 64);
                } else {
                    return (($values[0] % 32) * 64) + ($values[1] % 64);
                }
            }
        }
        return null;
    }
    function concat__onObject($str, $otherStr) {
        return $str . $otherStr;
    }
    function indexOf__onObject($str, $find) {
        $pos = mb_strpos($str, $find, 0, 'UTF-8');
        if ($pos === false) {
            return -1;
        }
        return $pos;
    }
    function lastIndexOf__onObject($str, $find) {
        $offset = -1;
        while (($pos = mb_strpos($str, $find, $offset + 1, 'UTF-8')) !== false) {
            $offset = $pos;
        }
        return $offset;
    }
    function match__onObject($str, $regex) {
        throw new Exception("Regular expressions are not implemented yet");
    }
    function replace__onObject($str, $from, $to) {
        throw new Exception("Regular expressions are not implemented yet");
    }
    function search__onObject($str, $val) {
        throw new Exception("Regular expressions are not implemented yet");
    }
    function slice__onObject($str, $from, $to = null) {
        if ($from < 0) {
            $from = iconv_strlen($str, 'UTF-8') + $from;
        }
        if ($to === null) {
            return iconv_substr($str, $from, iconv_strlen($str, 'UTF-8'), 'UTF-8');
        } else {
            return iconv_substr($str, $from, $to - $from, 'UTF-8');
        }
    }
    function split__onObject($str, $sep) {
        throw new Exception("Regular expressions are not implemented yet");
    }
    function substr__onObject($str, $from, $length = null) {
        if ($from < 0) {
            $from = iconv_strlen($str, 'UTF-8') + $from;
        }
        if ($length === null) {
            return iconv_substr($str, $from, iconv_strlen($str, 'UTF-8'), 'UTF-8');
        } else {
            return iconv_substr($str, $from, $length, 'UTF-8');
        }
    }
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
    static function fromCharCode($context, $n) {
        if ($n < 128) {
            return chr($n);
        } else if ($n < 2048) {
            return chr(192 + (($n - ($n % 64)) / 64))
                . chr(128 + ($n % 64));
        } else {
            return chr(224 + (($n - ($n % 4096)) / 4096))
                . chr(128 + ((($n % 4096) - ($n % 64)) / 64))
                . chr(128 + ($n % 64));
        }
    }
}