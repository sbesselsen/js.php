/**
 * Test for loops
 */
(function () {
var fib = function (n) {
    var a = 1;
    var b = 1;
    for (var i = 1; i < n; i++) {
        b = a + b;
        a = b - a;
    }
    return a;
};
jsphp.assert(fib(10) == 55);
})();

/**
 * Test while loops
 */
(function () {
var fib2 = function (n) {
    var a = 1;
    var b = 1;
    while (n > 1) {
        b = a + b;
        a = b - a;
        n--;
    }
    return a;
};
jsphp.assert(fib2(10) == 55);
})();

/**
 * Test recursion
 */
(function () {
var fib3 = function (n) {
    if (n <= 2) {
        return 1;
    }
    return fib3(n - 1) + fib3(n - 2);
};
jsphp.assert(fib3(10) == 55);
})();

/**
 * Test break & continue
 */
(function () {
var a = 0;
for (var i = 0; i < 10; i++) {
    if (i % 2 == 0) continue;
    if (i == 7) break;
    a += i;
}
jsphp.assert(a == 9);
jsphp.assert(i == 7);
})();

/**
 * Test prototypal inheritance
 */
(function () {
var baseCls = function () { }
var subCls = function () { }
subCls.prototype = new baseCls;
var x = new subCls;
jsphp.assert(x instanceof baseCls);
jsphp.assert(x instanceof subCls);
jsphp.assert(!x instanceof Array);
jsphp.assert(function() {} instanceof Object);
jsphp.assert(!(function() {} instanceof Array));
jsphp.assert(String instanceof Function);
jsphp.assert(Function instanceof Function);
jsphp.assert(Object instanceof Function);
jsphp.assert(Function instanceof Object);
jsphp.assert(!(String instanceof String));
jsphp.assert(function () {}.call.apply instanceof Function);
})();

/**
 * Test object properties and paths and .call
 */
(function () {
var baseCls = function (x) { this.x = x; }
baseCls.prototype.getX = function () { return 'x=' + this.x; }
var subCls = function (x) { baseCls.call(this, x + 5); }
subCls.prototype = new baseCls;
var q = new baseCls(5);
jsphp.assert(q.getX() == 'x=5');
var q = new subCls(5);
jsphp.assert(q.getX() == 'x=10');
jsphp.assert(function (x, y, z) { return this - x + y * z }.apply(10, [1, 4, 5]) == 29);
})();

/**
 * Test dereferencing, arguments
 */
(function () {
var arr = [1, 2, 3, 4, 5];
var f = function () {
    var sum = 0;
    for (var i = 0; i < arguments.length; i++) sum += arguments[i];
    for (var i = 0; i < this.y.length; i++) sum += this.y[i];
    return sum;
}
var obj = { x: 1, y: arr, f: f, g: function () { return this.y; } };
jsphp.assert(obj.f(1, 2, 3, 4) + obj['y'][1 + 1] == 28);
obj.y[0] = 0;
obj.g()[1] = 1;
jsphp.assert(obj.f(1, 2, 3, 4) + obj['y'][1 + 2] == 27);
})();

/**
 * Object prototyping
 */
(function () {
Object.prototype.testF = function () { return 'obj.x = ' + this.x; }
var obj = { x: 'test', y: 1, z: {} };
jsphp.assert(obj.testF() == 'obj.x = test');
jsphp.assert(obj.z.testF() == 'obj.x = ');
jsphp.assert([1, 2, 3, 4].length == 4);
})();

/**
 * Object iteration
 */
(function () {
var obj = { x: 1, y: 2, z: 3 };
var sum = 0;
for (var k in obj) {
    sum += obj[k];
}
jsphp.assert(sum == 6);
})();

/**
 * Constructors
 */
(function () {
var obj = { x: 1, y: 2, z: 3 };
jsphp.assert(obj.constructor === Object);
jsphp.assert([1, 2, 3].constructor === Array);
for (var k in obj) jsphp.assert(k != 'constructor');
jsphp.assert(''.constructor === String);
})();

/**
 * Math
 */
(function () {
jsphp.assert(Math.E > 2.71 && Math.E < 2.72);
jsphp.assert(Math.random() != Math.random());
jsphp.assert(Math.abs(-10) == 10);
jsphp.assert(Math.acos(-1) == Math.PI);
jsphp.assert(Math.asin(1) == Math.PI / 2);
jsphp.assert(Math.atan(1) == 0.7853981633974483);
jsphp.assert(Math.atan2(90, 15) == 1.4056476493802699);
jsphp.assert(Math.ceil(10.5) == 11);
jsphp.assert(Math.ceil(-1.5) == -1);
jsphp.assert(Math.cos(Math.PI) == -1);
jsphp.assert(Math.exp(2) == Math.E * Math.E);
jsphp.assert(Math.floor(10.5) == 10);
jsphp.assert(Math.floor(-1.5) == -2);
jsphp.assert(Math.log(Math.E * Math.E) == 2);
jsphp.assert(Math.max(1, 2, 3, 4, 2) == 4);
jsphp.assert(Math.min(1, 2, 3, 4, 2) == 1);
jsphp.assert(Math.min.apply(null, [1, 2, 3, 4, 2]) == 1);
jsphp.assert(Math.pow(2, 8) == 256);
jsphp.assert(Math.round(1.4) == 1);
jsphp.assert(Math.round(1.5) == 2);
jsphp.assert(Math.sin(Math.PI / 2) == 1);
jsphp.assert(Math.tan(Math.PI / 4) > 0.9999 && Math.tan(Math.PI / 4) < 1.0001);
jsphp.assert(Math.sqrt(100) == 10);
})();

/**
 * Object weirdness
 */
(function () {
jsphp.assert(new Number(10) == 10);
jsphp.assert(new Number(10) !== 10);
jsphp.assert(new Number(10).valueOf() == 10);
jsphp.assert(new Boolean(true) instanceof Boolean);
jsphp.assert(new Boolean(true) instanceof Object);
jsphp.assert({}.toString() == '[object Object]');
})();

/**
 * Strings
 */
(function () {
jsphp.assert('AAP'.toLowerCase() == 'aap');
jsphp.assert('eéëøπœ'.length == 6);
jsphp.assert('AAP'.substring(1, 2) == 'A');
jsphp.assert('aap'.toUpperCase() == 'AAP');
jsphp.assert('eéëøπœ'.charAt(2) == 'ë');
jsphp.assert('abc'.concat('def') == 'abcdef');
jsphp.assert('abcdefcdef'.indexOf('cde') == 2);
jsphp.assert('abcdef'.indexOf('cdf') == -1);
jsphp.assert('abcdefcdefbladieblacdefe'.lastIndexOf('cde') == 19);
jsphp.assert('abcdefcdefbladieblacdefe'.lastIndexOf('cdf') == -1);
jsphp.assert('abc'.slice(-2) == 'bc');
jsphp.assert('abc'.slice(1, 3) == 'bc');
jsphp.assert('abc'.substr(-2, 1) == 'b');
jsphp.assert('abc'.substr(1, 2) == 'bc');
jsphp.assert('aëc'.charCodeAt(1) == 235);
jsphp.assert(' '.charCodeAt(0) == 32);
jsphp.assert(String.fromCharCode(235) == 'ë');
jsphp.assert(String.fromCharCode(32) == ' ');
jsphp.assert('test\\\'a\n\t\u0044test'.length == 14);
jsphp.assert("test\\\"a\n\t\u0044test".charAt(9) == 'D');
})();

/**
 * Eval
 */
(function () {
var a = 10;
var b = 20;
jsphp.assert(eval('var c = a + b') == 30);
jsphp.assert(eval('var c = a + b; return 31') == 31);
for (var i = 0; i < 100; i++) eval('c += 10');
jsphp.assert(c == 1030);
})();

/**
 * Require
 */
(function () {
var aap = jsphp.require('testsuite_include.js');
jsphp.assert(aap.aap == aap.schaap(3));
jsphp.assert(aap.aap == 5);
})();

/**
 * Exception handling/bubbling
 */
(function () {
var aap = function (c) {
    if (c) {
        try {
            throw 'test';
        } catch (e) {
            jsphp.assert(e == 'test');
            return e;
        }
    } else {
        throw 'test2';
    }
}
try {
    jsphp.assert(aap(true) == 'test');
    aap();
} catch (e) {
    jsphp.assert(e == 'test2');
}
})();

/**
 * Regex
 */
(function () {
var a = new RegExp('aap', 'im');
jsphp.assert(a instanceof RegExp);
var b = /(aap\/schaap|test[0-9]+)/ig;
jsphp.assert(b instanceof RegExp);
jsphp.assert(b.global);
jsphp.assert(b.ignoreCase);
jsphp.assert(a.multiline);
jsphp.assert(!b.multiline);
})();

jsphp.export({
    manipulateObject: function (obj, a, b, c) {
        obj.setX(obj.getY());
        return 5;
    }
});


