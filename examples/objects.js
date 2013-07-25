var priceReductionFunction = function (product) {
  if (product.id == 5) {
    return 5;
  }
}
jsphp.export({
  calculatePrice: function (product, priceCalculator) {
    product.setPrice(priceCalculator.priceForProduct(product, priceReductionFunction));
  }
})