JS.php
======

JS.php is a project that allows you to parse and run Javascript code in a PHP environment. It includes its own parser/compiler, a VM and a runtime environment; together these are enough to pull tricks like this:

calc.js:

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

calc.php:

    $e = new JSPHP_Environment;
    $exports = $e->runFile("{$dir}/calc.js");
    var_dump($exports->g(1, 2)); // => 26
    $exports->setX(5);
    var_dump($exports->g(1, 2)); // => 16

You can also go much further and manipulate PHP objects, jumping between PHP and Javascript:

objects.js:

    var priceReductionFunction = function (product) {
      if (product.id == 5) {
        return 5;
      }
    }
    jsphp.export({
      calculatePrice: function (product, priceCalculator) {
        // call PHP functions on PHP objects from Javascript
        product.setPrice(priceCalculator.priceForProduct(product, priceReductionFunction));
      }
    })

objects.php:

    class PricesCalculator {
      function priceForProduct($product, $priceReductionFunction = null) {
        $product = $product->wrappedObject; // $product is a Javascript object; get the PHP object it wraps
        if ($product->id == 5) {
          $price = 100;
        } else {
          $price = 50;
        }
        if ($priceReductionFunction) {
          $price -= $priceReductionFunction->callFunction($product); // call a Javascript function from PHP
        }
        return $price;
      }
    }
    
    class Product {
      public $id;
      function setPrice($price) {
        $this->price = $price;
      }
    }
    
    $e = new JSPHP_Environment;
    $exports = $e->runFile("{$dir}/objects.js");
    $product1 = new Product;
    $product1->id = 5;
    $product2 = new Product;
    $product2->id = 6;
    
    $calculator = new PricesCalculator;
    // passing PHP objects into Javascript
    $exports->calculatePrice($product1, $calculator);
    var_dump($product1->price); // => 95
    $exports->calculatePrice($product2, $calculator);
    var_dump($product2->price); // => 50

Why?
====
I built it because I wanted to learn how to build a thing like this. That said, it may have some use in practical scenarios where you want to share logic between your frontend and backend. For instance, you can run the same form validation code on the frontend and on the backend, or perform a calculation on the server or on the client depending on circumstances. Another unholy idea that I have been pondering is using JSPHP as a kind of scripting language within CMS environments.

Of course, node.js would be far more suitable in both cases. JS.php is probably only useful in a few obscure cases, but hey!

Does it support complicated Javascript stuff?
====
Well, not nearly everything, but it does support:

* Lexical scoping
* Closures
* Prototypal inheritance with constructors
* `eval()`
* `try/catch`
* Mutable `Array`/`Object` prototypes
* `this`, `.call()`, `.apply()`, `arguments` (though without `caller` or `callee`)
* Most Unicode stuff

(Or at least to the degree that I understand these things and have managed to test them. Check out `testsuite.js`.)

Possible misapprehensions
====
This is **not** a tool to communicate between server-side PHP code and client-side Javascript. That's so 2005. This is an environment to run Javascript on the server using only the PHP runtime.

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