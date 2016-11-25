<?php
// @codingStandardsIgnoreStart
/**
 * Product data wrapper to make it easier to use and validate products.
 *
 * @since 2015-05-19 MB, version 1.0 created
 * @version 1.0
 *
 */
class Cpu_Client_Product {
	/**
	 * Product code. Max. length 25 chars.
	 *
	 * Use sku or other identification.
	 * There needs to be a product with this sku in eCommerce!
	 *
	 * @var string
	 */
	public $code = NULL;

	/**
	 * Order amount.
	 *
	 * @var integer
	 */
	public $amount = NULL;

	/**
	 * Price of single product vat included in cents.
	 *
	 * @example 20.50€ = 2050
	 * @var integer
	 */
	public $price = NULL;

	/**
	 * Product description. Max. length 40 chars.
	 * Will be added into confirmation email sent by server at checkout.
	 *
	 * @var string
	 */
	public $description = NULL;

	/**
	 * Vat code to be used with this product. Max. length 25 chars.
	 * There needs to be a taxcode with this taxcode in eCommerce!
	 *
	 * @var string
	 */
	public $Taxcode = NULL;

	/**
	 * Constructor creates the product.
	 * Sanitizes all the parameters to be fit for Product object.
	 *
	 * @param string $code Product code
	 * @param integer $amount Amount ordered
	 * @param number $price Price of single product
	 * @param string $description Short product description
	 */
	public function __construct($code, $amount = NULL, $price = NULL, $description = NULL) {
		$this->Code = Cpu_Client::sanitize($code);

		if ($amount)
			$this->Amount = Cpu_Client::sanitize($amount);

		if ($price)
			$this->Price = Cpu_Client::sanitize($price);

		if ($description)
			$this->Description = Cpu_Client::sanitize($description);
	}

	/**
	 * Checks mandatory properties of Product.
	 *
	 * @return boolean All mandatory properties are set
	 */
	public function isValid() {
		return ($this->Code != NULL)
			? TRUE
			: FALSE;
	}
}
// @codingStandardsIgnoreEnd
