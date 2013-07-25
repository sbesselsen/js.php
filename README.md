JS.php
======

JS.php is a project that allows you to parse and run Javascript code in a PHP environment. It includes its own parser/compiler, a VM and a runtime environment; together these are enough to pull tricks like this:

    // calc.js:
    var x = 10;
    var f = function (a, b, c) {
      return a(b + c + x);
    }
    var g = function (x, y) {
      return f(function (z) { return 2 * z }, 1, 2);
    }
    jsphp.export({
      g: g,
      setX: function (y) {
        x = y;
      }
    })
    
    // calc.php:
    $exports = $e->runFile("{$dir}/calc.js");
    var_dump($exports->g(1, 2)); // => 26
    $exports->setX(5);
    var_dump($exports->g(1, 2)); // => 16

Why?
====
I built it because I wanted to learn how to build a thing like this. That said, it may have some use in practical scenarios where you want to share logic between your frontend and backend. For instance, you can run the same form validation code on the frontend and on the backend, or perform a calculation on the server or on the client depending on circumstances. Another unholy idea that I have been pondering is using JSPHP as a kind of scripting language within CMS environments.

Performance
===========
While I have spent lots of time tweaking performance to make it at least acceptable, it should be clear that JS.php is not suitable for use in sites with heavy traffic, or when speedy execution is an issue. It's CPU-intensive and although an expert could probably rewrite the thing to be whole orders of magnitude faster, it will never be near native PHP speed. Still, much of the time is spent in the parser, and implementing some kind of opcode cache won't hurt. Feel free to implement it (but run testsuite.php/js for those pesky edge cases).

Todo
====
* Implement Regex: the schizophrenic API that Javascript uses means this is no small feat. In the mean time, you can of course inject the required PHP functions into JSPHP (the testsuite.js shows how to pass PHP functions into the JSPHP environment).
* Implement an opcode cache.
* Simplify the class structure just a little bit more.
* Do something about nested evaluators -- cache and reuse them when possible?
* Maybe parse into an AST and then compile, instead of sleazily doing the whole thing at once.

This is *so* half-baked!
=====
Yes, yes it is. In fact, I quit working on this some 8 months ago. I just decided to release this because hey, oblivion on Github beats oblivion on my hard drive.

 --- SB