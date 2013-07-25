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