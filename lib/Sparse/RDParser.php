<?php
abstract class Sparse_RDParser {
    protected $str;
    protected $lineNumber;
    protected $furthestLineNumber;
    
    protected $indicators = array ();
    
    abstract function main();
    
    /**
     * Parse a text to the end.
     * @param string $text
     * @return mixed
     */
    function parse($text) {
        $this->str = $text;
        $this->lineNumber = 1;
        $this->furthestLineNumber = 1;
        $this->shiftWhitespace();
        $output = $this->main();
        if ($this->str != '') {
            throw new Sparse_RDParser_ParseException("Unexpected token at line {$this->furthestLineNumber}");
        }
        return $output;
    }
    
    /**
     * Get the current state of the parser, so we can backtrack to it if things don't work out.
     * @return mixed
     */
    protected function state() {
        return array ($this->str, $this->lineNumber);
    }
    
    /**
     * Restore the parser to an earlier state.
     * @param mixed $state
     */
    protected function restoreState($state) {
        list ($this->str, $this->lineNumber) = $state;
    }
    
    /**
     * Return the current remainder minus leading whitespace.
     */
    protected function trimLeadingWhitespace() {
        return ltrim($this->str);
    }
    
    /**
     * Perform a regular expression using preg_match.
     * @param string $regex
     * @param string|null $flags
     * @return array    Match.
     */
    protected function regex($regex, $flags = '') {
        if (!$match = $this->peekRegex($regex, $flags)) {
            $this->expected($regex);
        }
        $this->shiftBuffer(strlen($match[0]));
        $this->shiftWhitespace();
        return $match;
    }
    
    /**
     * Perform a regular expression using preg_match, and advance the parser if we have a match.
     * @param string $regex
     * @param string|null $flags
     * @return array    Match.
     */
    protected function tryRegex($regex, $flags = '') {
        if (!$match = $this->peekRegex($regex, $flags)) {
            return null;
        }
        $this->shiftBuffer(strlen($match[0]));
        $this->shiftWhitespace();
        return $match;
    }
    
    /**
     * Perform a regular expression using preg_match, but don't advance the parser.
     * @param string $regex
     * @param string|null $flags
     * @return array|null    Match.
     */
    protected function peekRegex($regex, $flags = '') {
        if (preg_match('(^' . $regex . ')'. $flags, $this->str, $match)) {
            return $match;
        }
    }
    
    /**
     * Match a fixed string.
     * @param string $text
     * @return string
     */
    protected function text($text) {
        if (!$this->peekText($text)) {
            $this->expected($text);
        }
        $this->shiftBuffer(strlen($text));
        $this->shiftWhitespace();
        return $text;
    }
    
    /**
     * Match a fixed string, but don't advance the parser.
     * @param string $text
     * @return string|null
     */
    protected function peekText($text) {
        if (substr($this->str, 0, strlen($text)) == $text) {
            return $text;
        }
    }
    
    /**
     * Match a fixed string, and advance the parser if we have a match.
     * @param string $text
     * @return string|null
     */
    protected function tryText($text) {
        if (!$this->peekText($text)) {
            return null;
        }
        $this->shiftBuffer(strlen($text));
        $this->shiftWhitespace();
        return $text;
    }
    
    /**
     * Throw a Sparse_RDParser_ParseException because a certain expression was expected.
     * @param string $expr  Name of the expected expression.
     */
    protected function expected($expr) {
        throw new Sparse_RDParser_ParseException("Expected {$expr} at line {$this->lineNumber}");
    }
    
    function __call($f, $args) {
        if (substr($f, 0, 3) == 'try') {
            $f = lcfirst(substr($f, 3));
            if (isset ($this->indicators[$f])) {
                $possible = false;
                foreach ($this->indicators[$f] as $ind) {
                    if ($this->peekText($ind)) {
                        $possible = true;
                        break;
                    }
                }
                if (!$possible) {
                    return null;
                }
            }
            $cb = array ($this, $f);
            if (is_callable($cb)) {
                $state = $this->state();
                try {
                    return call_user_func_array($cb, $args);
                } catch (Sparse_RDParser_ParseException $e) {
                    $this->restoreState($state);
                    return null;
                }
            }
        }
        throw new Exception("Unknown method: {$f}");
    }
    
    private function shiftWhitespace() {
        $str = $this->str;
        $this->str = $this->trimLeadingWhitespace();
        $trimmed = substr($str, 0, -1 * strlen($this->str));
        $this->lineNumber += substr_count($trimmed, "\n");
        if ($this->lineNumber > $this->furthestLineNumber) {
            $this->furthestLineNumber = $this->lineNumber;
        }
    }
    
    private function shiftBuffer($len) {
        if ($len > 0) {
            $this->lineNumber += substr_count($this->str, "\n", 0, $len);
            $this->str = substr($this->str, $len);
            if ($this->lineNumber > $this->furthestLineNumber) {
                $this->furthestLineNumber = $this->lineNumber;
            }
        }
    }
}

class Sparse_RDParser_ParseException extends Exception {}
