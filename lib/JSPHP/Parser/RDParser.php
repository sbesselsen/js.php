<?php
require_once 'Sparse/RDParser.php';

/**
 * A parser that takes JS code as input and outputs a syntax tree.
 * The syntax tree is not yet standardized and should not be inspected,
 * as you will bump into plain opcodes very soon.
 */
class JSPHP_Parser_RDParser extends Sparse_RDParser {
    private $reservedWords = array ('if', 'for', 'in', 'while', 'return', 'function', 'do', 'else', 'new', 'arguments', 'typeof', 'instanceof', 'switch', 'case', 'default', 'delete');
    private $breakLabelStack;
    private $continueLabelStack;
    private $functionReferencesArguments;
    
    protected $indicators = array (
        'variableDeclareAssignStatement' => array ('var'),
        'returnStatement' => array ('return'),
        'ifStatement' => array ('if'),
        'doWhileStatement' => array ('do'),
        'whileStatement' => array ('while'),
        'forEachStatement' => array ('for'),
        'forLoopStatement' => array ('for'),
        'switchCaseStatement' => array ('switch'),
        'breakOrContinueStatement' => array ('break', 'continue'),
        'codeBlock' => array ('{'),
        'functionArgs' => array ('('),
        'parenExpr' => array ('('),
        'notExpr' => array ('!'),
        'functionDef' => array ('function'),
        'stringExpr' => array ('"', "'"),
        'arrayExpr' => array ('['),
        'objectExpr' => array ('{'),
        'typeofExpr' => array ('typeof'),
        'newObjectExpr' => array ('new'),
    );
    
    /**
     * Get the current state of the parser, so we can backtrack to it if things don't work out.
     * @return mixed
     */
    protected function state() {
        $state = parent::state();
        $state[] = $this->breakLabelStack;
        $state[] = $this->continueLabelStack;
        $state[] = $this->functionReferencesArguments;
        return $state;
    }
    
    /**
     * Restore the parser to an earlier state.
     * @param mixed $state
     */
    protected function restoreState($state) {
        $this->functionReferencesArguments = array_pop($state);
        $this->continueLabelStack = array_pop($state);
        $this->breakLabelStack = array_pop($state);
        parent::restoreState($state);
    }
    
    private function generateLabel() {
        return substr(md5(uniqid(true)), 0, 12);
    }
    
    function trimLeadingWhitespace() {
        $str = parent::trimLeadingWhitespace();
        do {
            $str0 = $str;
            $str = ltrim($str);
            $str = preg_replace("(^//(.*)\n)", '', $str);
            $str = preg_replace("(^/\*(.*?)\*/)s", '', $str);
        } while ($str0 != $str);
        return $str;
    }
    
    function main() {
        $this->breakLabelStack = array ();
        $this->continueLabelStack = array ();
        $this->functionReferencesArguments = false;
        return $this->statements();
    }
    
    function statements() {
        $ops = array ();
        $lineNumber = $this->lineNumber;
        $prevLineNumber = -1;
        while (($statementOps = $this->tryStatement()) !== null) {
            if ($lineNumber != $prevLineNumber) {
                $prevLineNumber = $lineNumber;
                $ops[] = array ('%loc', $lineNumber);
            }
            foreach ($statementOps as $op) {
                $ops[] = $op;
            }
            $lineNumber = $this->lineNumber;
        }
        return $ops;
    }
    
    function statement() {
        if ($st = $this->tryBreakOrContinueStatement()) {
            return $st;
        }
        if ($st = $this->tryIfStatement()) {
            return $st;
        }
        if ($st = $this->tryDoWhileStatement()) {
            return $st;
        }
        if ($st = $this->tryWhileStatement()) {
            return $st;
        }
        if ($st = $this->tryForEachStatement()) {
            return $st;
        }
        if ($st = $this->tryForLoopStatement()) {
            return $st;
        }
        if ($st = $this->trySwitchCaseStatement()) {
            return $st;
        }
        if ($st = $this->tryVariableDeclareAssignStatement()) {
            return $st;
        }
        if ($st = $this->tryReturnStatement()) {
            return $st;
        }
        if ($st = $this->tryExprStatement()) {
            return $st;
        }
        if ($this->tryText(';')) {
            return array ();
        }
        $this->expected('statement');
    }
    
    function variableDeclareAssignStatement() {
        $this->text('var');
        // var aap, schaap = 10, blaat, test = 5, schaap = 3;
        $out = array ();
        do {
            $variableName = $this->variableName();
            $out[] = array ('declare', $variableName);
            if ($this->tryText('=')) {
                foreach ($this->expr() as $inst) {
                    $out[] = $inst;
                }
                $out[] = array ('assign', $variableName);
                $out[] = array ('pop');
            }
            if (!$this->tryText(',')) {
                break;
            }
        } while (true);
        return $out;
    }
    
    function returnStatement() {
        $this->text('return');
        $out = array ();
        if ($this->tryText(';')) {
            $out[] = array ('pushnull');
        } else if ($this->peekText('}')) {
            $out[] = array ('pushnull');
        } else {
            foreach ($this->expr() as $inst) {
                $out[] = $inst;
            }
        }
        $out[] = array ('return');
        return $out;
    }
    
    function ifStatement() {
        $this->text('if');
        $this->text('(');
        $ifExpr = $this->expr();
        $this->text(')');
        if ($this->peekText('{')) {
            $ifCode = $this->codeBlock();
        } else {
            $ifCode = $this->statement();
        }
        $out = $ifExpr;
        array_unshift($out, array ('-', 'begin if'));
        $out[] = array ('not');
        $elseLbl = $this->generateLabel();
        $endLbl = $this->generateLabel();
        $hasElse = $this->tryText('else');
        $out[] = array ('gotoif', $hasElse ? $elseLbl : $endLbl);
        foreach ($ifCode as $inst) {
            $out[] = $inst;
        }
        $out[] = array ('goto', $endLbl);
        if ($hasElse) {
            $out[] = array ('-', 'else');
            $out[] = array ('%label', $elseLbl);
            if ($elseCode = $this->tryIfStatement()) {
                foreach ($elseCode as $inst) {
                    $out[] = $inst;
                }
            } else {
                if ($this->peekText('{')) {
                    $elseCode = $this->codeBlock();
                } else {
                    $elseCode = $this->statement();
                }
                foreach ($elseCode as $inst) {
                    $out[] = $inst;
                }
            }
        }
        $out[] = array ('-', 'end if');
        $out[] = array ('%label', $endLbl);
        return $out;
    }
    
    function whileStatement() {
        $this->text('while');
        $this->text('(');
        $cond = $this->expr();
        $this->text(')');
        $startLbl = $this->generateLabel();
        $endLbl = $this->generateLabel();
        $this->continueLabelStack[] = $startLbl;
        $this->breakLabelStack[] = $endLbl;
        if ($this->peekText('{')) {
            $whileCode = $this->codeBlock();
        } else {
            $whileCode = $this->statement();
        }
        array_pop($this->continueLabelStack);
        array_pop($this->breakLabelStack);
        $out = $cond;
        array_unshift($out, array ('%label', $startLbl));
        array_unshift($out, array ('-', 'begin while loop'));
        $out[] = array ('not');
        $out[] = array ('gotoif', $endLbl);
        foreach ($whileCode as $inst) {
            $out[] = $inst;
        }
        $out[] = array ('goto', $startLbl);
        $out[] = array ('-', 'end while loop');
        $out[] = array ('%label', $endLbl);
        return $out;
    }
    
    function doWhileStatement() {
        $this->text('do');
        $startLbl = $this->generateLabel();
        $endLbl = $this->generateLabel();
        $this->continueLabelStack[] = $startLbl;
        $this->breakLabelStack[] = $endLbl;
        $out = $this->codeBlock();
        array_pop($this->continueLabelStack);
        array_pop($this->breakLabelStack);
        array_unshift($out, array ('%label', $startLbl));
        array_unshift($out, array ('-', 'begin do..while loop'));
        $this->text('while');
        $this->text('(');
        foreach ($this->expr() as $op) {
            $out[] = $op;
        }
        $this->text(')');
        $out[] = array ('gotoif', $startLbl);
        $out[] = array ('-', 'end do..while loop');
        $out[] = array ('%label', $endLbl);
        return $out;
    }
    
    function forEachStatement() {
        $this->text('for');
        $this->text('(');
        $out = array ();
        $varDeclare = (bool)$this->tryText('var');
        $varName = $this->variableName();
        if ($varDeclare) {
            $out[] = array ('declare', $varName);
        }
        $this->text('in');
        $expr = $this->expr();
        $this->text(')');
        $startLbl = $this->generateLabel();
        $nextLbl = $this->generateLabel();
        $endLbl = $this->generateLabel();
        $this->continueLabelStack[] = $nextLbl;
        $this->breakLabelStack[] = $endLbl;
        if ($this->peekText('{')) {
            $loopCode = $this->codeBlock();
        } else {
            $loopCode = $this->statement();
        }
        array_pop($this->continueLabelStack);
        array_pop($this->breakLabelStack);
        foreach ($expr as $op) {
            $out[] = $op;
        }
        $out[] = array ('iterator');
        $out[] = array ('%label', $startLbl);
        $out[] = array ('itervalid');
        $out[] = array ('not');
        $out[] = array ('gotoif', $endLbl);
        $out[] = array ('iterkey');
        $out[] = array ('assign', $varName);
        $out[] = array ('pop');
        // and now the loop code
        foreach ($loopCode as $op) {
            $out[] = $op;
        }
        $out[] = array ('%label', $nextLbl);
        $out[] = array ('iternext');
        $out[] = array ('goto', $startLbl);
        $out[] = array ('%label', $endLbl);
        $out[] = array ('pop');
        return $out;
    }
    
    function forLoopStatement() {
        $this->text('for');
        $this->text('(');
        if ($this->tryText(';')) {
            $preCode = array ();
        } else {
            $preCode = $this->forLeadingStatement();
            $this->text(';');
        }
        $cond = $this->expr();
        $this->text(';');
        if ($this->tryText(')')) {
            $postCode = array ();
        } else {
            $postCode = $this->expr();
            $postCode[] = array ('pop');
        }
        $startLbl = $this->generateLabel();
        $nextLbl = $this->generateLabel();
        $endLbl = $this->generateLabel();
        $this->text(')');
        $this->continueLabelStack[] = $nextLbl;
        $this->breakLabelStack[] = $endLbl;
        if ($this->peekText('{')) {
            $loopCode = $this->codeBlock();
        } else {
            $loopCode = $this->statement();
        }
        array_pop($this->continueLabelStack);
        array_pop($this->breakLabelStack);
        $out = $preCode;
        $out[] = array ('%label', $startLbl);
        foreach ($cond as $inst) {
            $out[] = $inst;
        }
        $out[] = array ('not');
        array_unshift($out, array ('-', 'begin for loop'));
        $out[] = array ('gotoif', $endLbl);
        foreach ($loopCode as $inst) {
            $out[] = $inst;
        }
        $out[] = array ('%label', $nextLbl);
        foreach ($postCode as $inst) {
            $out[] = $inst;
        }
        $out[] = array ('goto', $startLbl);
        $out[] = array ('-', 'end for loop');
        $out[] = array ('%label', $endLbl);
        return $out;
    }
    
    function breakOrContinueStatement() {
        if ($this->tryText('break')) {
            if (!$size = sizeof($this->breakLabelStack)) {
                $this->expected('no break');
            }
            $label = $this->breakLabelStack[$size - 1];
            return array (array ('goto', $label));
        }
        $this->text('continue');
        if (!$size = sizeof($this->continueLabelStack)) {
            $this->expected('no continue');
        }
        $label = $this->continueLabelStack[$size - 1];
        return array (array ('goto', $label));
    }
    
    function forLeadingStatement() {
        if ($st = $this->tryVariableDeclareAssignStatement()) {
            return $st;
        }
        if ($st = $this->tryExprStatement()) {
            return $st;
        }
        $this->expected('leading statement in for loop');
    }
    
    function switchCaseStatement() {
        $this->text('switch');
        $this->text('(');
        $out = $this->expr();
        $endLbl = $this->generateLabel();
        $this->breakLabelStack[] = $endLbl;
        $this->text(')');
        $this->text('{');
        $first = true;
        $codeStartLbl = $this->generateLabel();
        while (!$this->tryText('}')) {
            $codeEndLbl = $this->generateLabel();
            
            // get case/default labels
            $hasLabels = false;
            while (true) {
                if ($this->tryText('case')) {
                    $hasLabels = true;
                    $out[] = array ('dup');
                    foreach ($this->expr(true) as $op) {
                        $out[] = $op;
                    }
                    $this->text(':');
                    $out[] = array ('eq');
                    $out[] = array ('gotoif', $codeStartLbl);
                } else if ($this->tryText('default')) {
                    $hasLabels = true;
                    $this->text(':');
                    $out[] = array ('goto', $codeStartLbl);
                } else {
                    $out[] = array ('goto', $codeEndLbl);
                    break;
                }
            }
            if (!$hasLabels) {
                break;
            }
            $out[] = array ('%label', $codeStartLbl);
            $out[] = array ('pop');
            foreach ($this->statements() as $op) {
                $out[] = $op;
            }
            $codeStartLbl = $this->generateLabel();
            if ($this->peekText('case') || $this->peekText('default')) {
                // move immediately to the next case if we are in
                $out[] = array ('goto', $codeStartLbl);
            }
            $out[] = array ('%label', $codeEndLbl);
        }
        $out[] = array ('%label', $codeStartLbl);
        $out[] = array ('pop');
        $out[] = array ('%label', $endLbl);
        return $out;
    }
    
    function exprStatement() {
        $expr = $this->expr();
        $expr[] = array ('pop');
        return $expr;
    }
    
    function codeBlock() {
        $this->text('{');
        $out = $this->statements();
        $this->text('}');
        return $out;
    }
    
    function variableName() {
        $regex = $this->regex('[$a-z_A-Z][a-zA-Z0-9_$]*');
        if (in_array($regex[0], $this->reservedWords)) {
            $this->expected('variable name');
        }
        return $regex[0];
    }
    
    function expr($caseExpr = false) {
        $exprs = array ($this->nonInfixExpr());
        while (true) {
            if ($caseExpr && $this->peekText(':')) {
                // stop at the : if we are in a case label
                break;
            }
            if (!$op = $this->tryInfixOperator()) {
                break;
            }
            $exprs[] = $op;
            $exprs[] = $this->nonInfixExpr();
        }
        $i = 0;
        while (sizeof($exprs) > 1) {
            $this->mergeInfixLassoc($exprs, '%', 'mod');
            $this->mergeInfixRassoc($exprs, '*', 'mul');
            $this->mergeInfixLassoc($exprs, '/', 'div');
            $this->mergeInfixLassoc($exprs, '-', 'sub');
            $this->mergeInfixRassoc($exprs, '+', 'add');
            $this->mergeInfixRassoc($exprs, '<', 'les');
            $this->mergeInfixRassoc($exprs, '<=', 'leq');
            $this->mergeInfixRassoc($exprs, '>', 'gre');
            $this->mergeInfixRassoc($exprs, '>=', 'greq');
            $this->mergeInfixRassoc($exprs, '===', 'eeq');
            $this->mergeInfixRassoc($exprs, '!==', 'neeq');
            $this->mergeInfixRassoc($exprs, '==', 'eq');
            $this->mergeInfixRassoc($exprs, '!=', 'neq');
            $this->mergeInfixRassoc($exprs, 'instanceof', 'instanceof');
            $this->mergeInfixRassoc($exprs, 'in', 'in');
            $this->mergeInfixAnd($exprs);
            $this->mergeInfixOr($exprs);
            $this->mergeInfixEitherOr($exprs);
            if ($i++ > 1000) {
                // there are limits...
                $this->expected('expression');
            }
        }
        return $exprs[0];
    }
    
    private function mergeInfixRassoc(array &$exprs, $op, $inst) {
        for ($i = sizeof($exprs) - 3; $i >= 0; $i -= 2) {
            if ($exprs[$i + 1] == $op) {
                // splice
                $exprs[$i] = array_merge($exprs[$i], $exprs[$i + 2]);
                $exprs[$i][] = array ($inst);
                array_splice($exprs, $i + 1, 2);
            }
        }
    }
    
    private function mergeInfixOr(array &$exprs) {
        $op = '||';
        for ($i = sizeof($exprs) - 3; $i >= 0; $i -= 2) {
            if ($exprs[$i + 1] == $op) {
                $afterLabel = $this->generateLabel();
                $expr = $exprs[$i];
                // splice
                // exprs[$i] contains the LHS
                // exprs[$i + 2] contains the RHS
                $expr[] = array ('dup');
                $expr[] = array ('gotoif', $afterLabel);
                $expr[] = array ('pop');
                foreach ($exprs[$i + 2] as $inst) {
                    $expr[] = $inst;
                }
                $expr[] = array ('%label', $afterLabel);
                array_splice($exprs, $i, 3, array ($expr));
            }
        }
    }
    
    private function mergeInfixAnd(array &$exprs) {
        $op = '&&';
        for ($i = sizeof($exprs) - 3; $i >= 0; $i -= 2) {
            if ($exprs[$i + 1] == $op) {
                $afterLabel = $this->generateLabel();
                $expr = $exprs[$i];
                // splice
                // exprs[$i] contains the LHS
                // exprs[$i + 2] contains the RHS
                $expr[] = array ('pushbool', 0);
                $expr[] = array ('swap');
                $expr[] = array ('not');
                $expr[] = array ('gotoif', $afterLabel);
                $expr[] = array ('pop');
                foreach ($exprs[$i + 2] as $inst) {
                    $expr[] = $inst;
                }
                $expr[] = array ('%label', $afterLabel);
                array_splice($exprs, $i, 3, array ($expr));
            }
        }
    }
    
    private function mergeInfixLassoc(array &$exprs, $op, $inst) {
        for ($i = 0; $i < sizeof($exprs) - 2; $i += 2) {
            if ($exprs[$i + 1] == $op) {
                // splice
                $exprs[$i] = array_merge($exprs[$i], $exprs[$i + 2]);
                $exprs[$i][] = array ($inst);
                array_splice($exprs, $i + 1, 2);
                $i -= 2;
            }
        }
    }
    
    private function mergeInfixEitherOr(array &$exprs) {
        // a ? b : c
        for ($i = sizeof($exprs) - 5; $i >= 0; $i -= 2) {
            if ($exprs[$i + 1] == '?' && $exprs[$i + 3] == ':') {
                $secondPartLabel = $this->generateLabel();
                $endLabel = $this->generateLabel();
                $expr = $exprs[$i];
                $expr[] = array ('not');
                $expr[] = array ('gotoif', $secondPartLabel);
                foreach ($exprs[$i + 2] as $op) {
                    $expr[] = $op;
                }
                $expr[] = array ('goto', $endLabel);
                $expr[] = array ('%label', $secondPartLabel);
                foreach ($exprs[$i + 4] as $op) {
                    $expr[] = $op;
                }
                $expr[] = array ('%label', $endLabel);
                array_splice($exprs, $i, 5, array ($expr));
            }
        }
    }
    
    function nonInfixExpr() {
        $prefixOp = null;
        if ($this->tryText('--')) {
            $prefixOp = '--';
        } else if ($this->tryText('++')) {
            $prefixOp = '++';
        } else if ($this->tryText('delete')) {
            $prefixOp = 'delete';
        }
        if ($expr = $this->tryParenExpr()) {
            return $this->derefExpand($expr, $prefixOp);
        }
        if (!$prefixOp) {
            if ($expr = $this->tryStringExpr()) {
                return $this->derefExpand($expr, $prefixOp);
            }
            if ($expr = $this->tryConstantIdentifier()) {
                return $expr;
            }
            if ($expr = $this->tryNumber()) {
                return $expr;
            }
            if ($expr = $this->tryNotExpr()) {
                return $expr;
            }
        }
        if ($expr = $this->tryArrayExpr()) {
            return $this->derefExpand($expr, $prefixOp);
        }
        if ($expr = $this->tryObjectExpr()) {
            return $this->derefExpand($expr, $prefixOp);
        }
        if ($expr = $this->tryNewObjectExpr()) {
            return $this->derefExpand($expr, $prefixOp);
        }
        if ($expr = $this->tryFunctionDef()) {
            return $this->derefExpand($expr, $prefixOp);
        }
        if ($expr = $this->tryVarDerefOrAssignExpr($prefixOp)) {
            return $expr;
        }
        if ($expr = $this->tryTypeofExpr()) {
            return $expr;
        }
        $this->expected('expression');
    }
    
    function varDerefOrAssignExpr($prefixOp = null) {
        if ($var = $this->tryText('arguments')) {
            $this->functionReferencesArguments = true;
        } else {
            $var = $this->variableName();
        }
        $ops = array ();
        if ($this->peekText('=') && !$this->peekText('==')) {
            $assignOps = array (array ('assign', $var));
        } else if ($this->peekText('+=') || $this->peekText('-=') || $this->peekText('++') || $this->peekText('--')) {
            if ($prefixOp) {
                $this->expected('variable expression or dereferencing expression');
            }
            $ops[] = array ('pushvar', $var);
            $assignOps = array (array ('assign', $var));
        } else {
            if ($this->peekText('(')) {
                // use the current subject as the subject for the function call
                $ops[] = array ('pushvar', 'this');
            } else if (!$this->peekText('.') && !$this->peekText('[') && $prefixOp == 'delete') {
                // delete <var-name>
                $ops[] = array ('delete', $var);
                return $ops;
            }
            $ops[] = array ('pushvar', $var);
            list ($ops, $assignOps) = $this->derefFollowPath($ops, true, $prefixOp);
        }
        return $this->derefContinue($ops, $assignOps, $prefixOp);
    }
    
    function derefFollowPath(array $derefOps, $includeFunctionCalls = true, $prefixOp = null) {
        $assignOps = array ();
        while (true) {
            if ($this->peekText('(')) {
                if (!$includeFunctionCalls) {
                    break;
                }
                // we are calling a function again
                list ($numArgs, $argOps) = $this->functionArgs();
                $derefOps[] = array ('-', 'begin function call');
                foreach ($argOps as $op) {
                    $derefOps[] = $op;
                }
                $derefOps[] = array ('pushnum', $numArgs);
                $derefOps[] = array ('callfun');
                $derefOps[] = array ('-', 'end function call');
                if ($this->peekText('(')) {
                    // returned function: make sure its context exists (just null)
                    // otherwise it will just pop something off the stack and use it for context
                    $derefOps[] = array ('pushnull');
                    $derefOps[] = array ('swap');
                }
                if ($this->peekText('=') && !$this->peekText('==')) {
                    $this->expected('no assignment to function call result');
                }
                if ($this->peekText('+=') || $this->peekText('-=') || $this->peekText('--') || $this->peekText('++')) {
                    $this->expected('no increment/decrement of function call result');
                }
                if (!$this->peekText('.') && !$this->peekText('(') && !$this->peekText('[') && $prefixOp == 'delete') {
                    $this->expected('no delete on a function expression');
                }
            } else if ($this->tryText('[')) {
                $expr = $this->expr();
                $this->text(']');
                if ($this->peekText('(') && $includeFunctionCalls) {
                    // use the path so far as the subject for the function call
                    $derefOps[] = array ('dup');
                }
                foreach ($expr as $op) {
                    $derefOps[] = $op;
                }
                if (!$this->peekText('.') && !$this->peekText('(') && !$this->peekText('[') && $prefixOp == 'delete') {
                    $assignOps[] = array ('objectdelete');
                    break;
                } else if ($this->peekText('=') && !$this->peekText('==')) {
                    $assignOps[] = array ('objectset');
                    break;
                } else if ($this->peekText('+=') || $this->peekText('-=') || $this->peekText('++') || $this->peekText('--') || ($prefixOp && !$this->peekText('.') && !$this->peekText('(') && !$this->peekText('['))) {
                    $derefOps[] = array ('dup', 2);
                    $derefOps[] = array ('objectget');
                    $assignOps[] = array ('objectset');
                    break;
                } else {
                    $derefOps[] = array ('objectget');
                }
            } else if ($this->tryText('.')) {
                $var = $this->variableName();
                if ($this->peekText('(') && $includeFunctionCalls) {
                    // use the path so far as the subject for the function call
                    $derefOps[] = array ('dup');
                }
                $derefOps[] = array ('pushstr', $var);
                if (!$this->peekText('.') && !$this->peekText('(') && !$this->peekText('[') && $prefixOp == 'delete') {
                    $assignOps[] = array ('objectdelete');
                    break;
                } else if ($this->peekText('=') && !$this->peekText('==')) {
                    $assignOps[] = array ('objectset');
                    break;
                } else if ($this->peekText('+=') || $this->peekText('-=') || $this->peekText('++') || $this->peekText('--') || ($prefixOp && !$this->peekText('.') && !$this->peekText('(') && !$this->peekText('['))) {
                    $derefOps[] = array ('dup', 2);
                    $derefOps[] = array ('objectget');
                    $assignOps[] = array ('objectset');
                    break;
                } else {
                    $derefOps[] = array ('objectget');
                }
            } else {
                if ($prefixOp == 'delete') {
                    $this->expected('different content after delete');
                }
                break;
            }
        }
        return array ($derefOps, $assignOps);
    }
    
    function derefExpand(array $derefOps, $prefixOp = null) {
        list ($derefOps, $assignOps) = $this->derefFollowPath($derefOps, true, $prefixOp);
        return $this->derefContinue($derefOps, $assignOps, $prefixOp);
    }
    
    function derefContinue(array $derefOps, array $assignOps, $prefixOp = null) {
        $parseRHS = true;
        if ($prefixOp == '--') {
            $derefOps[] = array ('pushnum', 1);
            array_unshift($assignOps, array ('sub'));
            $parseRHS = false;
        } else if ($prefixOp == '++') {
            $derefOps[] = array ('pushnum', 1);
            array_unshift($assignOps, array ('add'));
            $parseRHS = false;
        } else if ($prefixOp == 'delete') {
            $parseRHS = false;
        } else if ($this->tryText('+=')) {
            array_unshift($assignOps, array ('add'));
        } else if ($this->tryText('-=')) {
            array_unshift($assignOps, array ('sub'));
        } else if ($this->tryText('++')) {
            $derefOps[] = array ('pushnum', 1);
            array_unshift($assignOps, array ('add'));
            // remove the increment from the value returned
            // this is a bit of a hack
            $assignOps[] = array ('pushnum', -1);
            $assignOps[] = array ('add');
            $parseRHS = false;
        } else if ($this->tryText('--')) {
            $derefOps[] = array ('pushnum', 1);
            array_unshift($assignOps, array ('sub'));
            // add the decrement back to the value returned
            $assignOps[] = array ('pushnum', 1);
            $assignOps[] = array ('add');
            $parseRHS = false;
        } else if ($this->peekText('==') || !$this->tryText('=')) {
            return $derefOps;
        }
        // assignment or increment
        $out = $derefOps;
        if ($parseRHS) {
            $expr = $this->expr();
            foreach ($expr as $op) {
                $out[] = $op;
            }
        }
        foreach ($assignOps as $op) {
            $out[] = $op;
        }
        return $out;
    }
    
    function typeofExpr() {
        $this->text('typeof');
        $out = $this->expr();
        $out[] = array ('typeof');
        return $out;
    }
    
    function functionCall() {
        $var = $this->variableName();
        list ($numArgs, $out) = $this->functionArgs();
        array_unshift($out, 
            array ('-', 'begin function call'), 
            array ('pushvar', $var)
        );
        $out[] = array ('pushnum', $numArgs);
        $out[] = array ('callfun');
        $out[] = array ('-', 'end function call');
        return $out;
    }
    
    function functionArgs() {
        $this->text('(');
        $out = array ();
        $numArgs = 0;
        if ($expr = $this->tryExpr()) {
            $numArgs++;
            foreach ($expr as $inst) {
                $out[] = $inst;
            }
            while ($this->tryText(',')) {
                $numArgs++;
                foreach ($this->expr() as $inst) {
                    $out[] = $inst;
                }
            }
        }
        $this->text(')');
        return array ($numArgs, $out);
    }
    
    function parenExpr() {
        $this->text('(');
        $out = $this->expr();
        $this->text(')');
        return $out;
    }
    
    function notExpr() {
        $this->text('!');
        $expr = $this->expr();
        $expr[] = array ('not');
        return $expr;
    }
    
    function infixOperator() {
        $regex = $this->regex('(\?|:|\+|-|/|\*|\|\||&&|%|<=?|>=?|===?|!==?|instanceof|in)');
        return $regex[0];
    }
    
    function number() {
        $number = $this->numberValue();
        return array (array ('pushnum', $number));
    }
    
    function constantIdentifier() {
        $regex = $this->regex('(true|false|null)');
        switch ($regex[0]) {
            case 'true':
                return array (array ('pushbool', 1));
            case 'false':
                return array (array ('pushbool', 0));
            case 'null':
                return array (array ('pushnull'));
        }
    }
    
    function numberValue() {
        $regex = $this->regex('-?[0-9]+(\.[0-9]+)?');
        return 0 + $regex[0];
    }
    
    function functionDef() {
        $this->text('function');
        $this->text('(');
        $params = array ();
        if ($var = $this->tryVariableName()) {
            $params[] = $var;
            while ($this->tryText(',')) {
                $params[] = $this->variableName();
            }
        }
        $this->text(')');
        $breakLabelStack = $this->breakLabelStack;
        $continueLabelStack = $this->continueLabelStack;
        $functionReferencesArguments = $this->functionReferencesArguments;
        $this->breakLabelStack = array ();
        $this->continueLabelStack = array ();
        $this->functionReferencesArguments = false;
        $code = $this->codeBlock();
        $this->breakLabelStack = $breakLabelStack;
        $this->continueLabelStack = $continueLabelStack;
        $thisFunctionReferencesArguments = $this->functionReferencesArguments;
        $this->functionReferencesArguments = $functionReferencesArguments;
        $out = array ();
        $out[] = array ('-', 'begin function');
        $endLbl = $this->generateLabel();
        $out[] = array ('deffun', sizeof($params), (int)$thisFunctionReferencesArguments, $endLbl);
        if ($thisFunctionReferencesArguments) {
            $out[] = array ('unpackarg', 'arguments');
        }
        // unpack params
        foreach (array_reverse($params) as $param) {
            $out[] = array ('unpackarg', $param);
        }
        foreach ($code as $inst) {
            $out[] = $inst;
        }
        $out[] = array ('pushnull');
        $out[] = array ('return');
        $out[] = array ('-', 'end function');
        $out[] = array ('%label', $endLbl);
        return $out;
    }
    
    function stringExpr() {
        if (($str = $this->stringValue()) !== null) {
            return array (array ('pushstr', $str));
        }
    }
    
    function stringValue() {
        if ($regex = $this->tryRegex("'((\\\\'|[^'])*)'")) {
            // single-quoted
            return str_replace("\'", "'", $regex[1]);
        } else {
            // double-quoted
            $regex = $this->regex('"((\\\\"|[^"])*)"');
            return str_replace('\"', '"', $regex[1]);
        }
    }
    
    function arrayExpr() {
        $this->text('[');
        $out = array ();
        $out[] = array ('-', 'create array');
        $out[] = array ('pusharray');
        if ($this->tryText(']')) {
            return $out;
        }
        foreach ($this->expr() as $inst) {
            $out[] = $inst;
        }
        $out[] = array ('arraypush');
        while ($this->tryText(',')) {
            if ($this->peekText(']')) {
                break;
            }
            foreach ($this->expr() as $inst) {
                $out[] = $inst;
            }
            $out[] = array ('arraypush');
        }
        $this->text(']');
        $out[] = array ('-', 'end create array');
        return $out;
    }
    
    function objectExpr() {
        $this->text('{');
        $out = array ();
        $out[] = array ('-', 'create object');
        $out[] = array ('pushobject');
        if ($this->tryText('}')) {
            return $out;
        }
        $out[] = array ('pushstr', $this->objectKey());
        $this->text(':');
        foreach ($this->expr() as $inst) {
            $out[] = $inst;
        }
        $out[] = array ('objectpush');
        while ($this->tryText(',')) {
            if ($this->peekText('}')) {
                break;
            }
            $out[] = array ('pushstr', $this->objectKey());
            $this->text(':');
            foreach ($this->expr() as $inst) {
                $out[] = $inst;
            }
            $out[] = array ('objectpush');
        }
        $this->text('}');
        $out[] = array ('-', 'end create object');
        return $out;
    }
    
    function objectKey() {
        if (($str = $this->tryStringValue()) !== null) {
            return $str;
        }
        return $this->variableName();
    }
    
    function newObjectExpr() {
        $this->text('new');
        $ops = array ();
        $ops[] = array ('-', 'object creation');
        $ops[] = array ('pushvar', $this->variableName());
        list ($ops, $assignOps) = $this->derefFollowPath($ops, false);
        if ($assignOps) {
            $this->expected('constructor call');
        }
        if ($this->peekText('(')) {
            list ($numArgs, $argOps) = $this->functionArgs();
            foreach ($argOps as $op) {
                $ops[] = $op;
            }
        } else {
            $numArgs = 0;
        }
        $ops[] = array ('pushnum', $numArgs);
        $ops[] = array ('callconstr');
        $ops[] = array ('pop');
        $ops[] = array ('-', 'end object creation');
        return $ops;
    }
}
