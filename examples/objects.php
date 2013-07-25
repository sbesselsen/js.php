<?php
error_reporting(E_ALL | E_STRICT);

$dir = dirname(__FILE__);
set_include_path($dir . '/../lib/' . PATH_SEPARATOR . get_include_path());

require_once 'JSPHP/Environment.php';
$e = new JSPHP_Environment();

class PricesCalculator {
  function priceForProduct($product, $priceReductionFunction = null) {
    $product = $product->wrappedObject;
    if ($product->id == 5) {
      $price = 100;
    } else {
      $price = 50;
    }
    if ($priceReductionFunction) {
      $price -= $priceReductionFunction->callFunction($product);
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
