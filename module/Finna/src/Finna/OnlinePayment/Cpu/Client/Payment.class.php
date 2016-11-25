<?php
// @codingStandardsIgnoreStart
/**
 * Payment data to be sent to CPU payment gateway.
 *
 * @since 2015-05-19 MB, Version 1.0 created
 * @version 1.0
 */
class Cpu_Client_Payment {
	/**
	 * eCommerce integration.
	 * 3 = eCommerce
	 *
	 * @var string
	 */
	const MODE_ECOMMERCE = '3';

	/**
	 * Version of integration software.
	 *
	 * @var string
	 */
	public $ApiVersion = '2.0';

	/**
	 * Payment identification created by client system.
	 * This identifies payments within client system.
	 *
	 * @var string
	 */
	public $Id = NULL;

	/**
	 * Integration mode.
	 *
	 * @var string
	 */
	public $Mode = Cpu_Client_Payment::MODE_ECOMMERCE;

	/**
	 * Description of payment. Max length 100 chars.
	 * Will be added into email confirmation.
	 *
	 * @var string
	 */
	public $Description = NULL;

	/**
	 * List of products.
	 *
	 * @see Cpu_Client_Product
	 * @var array
	 */
	public $Products = [];

	/**
	 * Url where server sends customer after he has succesfully paid his order.
	 *
	 * @var string
	 */
	public $ReturnAddress = NULL;

	/**
	 * Url where server sends notifications about statuses of order payments.
	 *
	 * @var string
	 */
	public $NotificationAddress = NULL;

	/**
	 * E-Mail address of customer.
	 * Server sends confirmation email into this account.
	 *
	 * If email is not given then server will ask it at checkout.
	 *
	 * @var string
	 */
	public $Email = NULL;

	/**
	 * First name of customer.
	 *
	 * If name is not given then server will ask it at checkout.
	 *
	 * @var string
	 */
	public $FirstName = NULL;

	/**
	 * Last name of customer.
	 *
	 * If name is not given then server will ask it at checkout.
	 *
	 * @var string
	 */
	public $LastName = NULL;

	/**
	 * Constructor initialises object.
	 *
	 * @param string $id Payment identification
	 */
	public function __construct($id = NULL) {
		$this->Id = Cpu_Client::sanitize($id);
	}

	/**
	 * Adds product into payment data.
	 * Checks validity of product data before including it.
	 *
	 * @see Cpu_Client_Product::isValid()
	 * @param Cpu_Client_Product $product Product
	 * @return Cpu_Client_Payment
	 */
	public function addProduct(Cpu_Client_Product $product) {
		if ($product->isValid()) {
			$this->Products[] = $product;
		}

		return $this;
	}

	/**
	 * Checks mandatory properties of payment.
	 *
	 * @return boolean Payment has all necessary properties
	 */
	public function isValid() {
		return (!empty($this->ApiVersion)
				&& !empty($this->Id)
				&& $this->Mode == self::MODE_ECOMMERCE
				&& count($this->Products)
				&& !empty($this->ReturnAddress) && filter_var($this->ReturnAddress, FILTER_VALIDATE_URL)
				&& !empty($this->NotificationAddress) && filter_var($this->NotificationAddress, FILTER_VALIDATE_URL)
			//	&& !empty($this->Hash)
			) ? TRUE : FALSE;
	}

	/**
	 * Calculates sha256 signature.
	 * Only mandatory properties and properties with values are used in calculation.
	 *
	 * @param string $source Source identification given by CPU
	 * @param string $secret_key Secret Key identification given by CPU
	 * @return string sha256 hash signature
	 */
	public function calculateHash($source, $secret_key) {
		$source     = Cpu_Client::sanitize($source);
		$secret_key = Cpu_Client::sanitize($secret_key);
		$separator  = '&';
		$string     = '';

		if (!empty($source) && !empty($secret_key)) {
			$string .= $this->ApiVersion . $separator;
			$string .= $source . $separator;
			$string .= $this->Id . $separator;
			$string .= $this->Mode . $separator;

			if ($this->Description != NULL)
				$string .= str_replace(';', '', $this->Description) . $separator;

			foreach ($this->Products as $product) {
				if ($product instanceof Cpu_Client_Product) {
					$string .= str_replace(';', '', $product->Code) . $separator;

					if ($product->Amount != NULL)
						$string .= intval($product->Amount) . $separator;

					if ($product->Price != NULL)
						$string .= intval($product->Price) . $separator;

					if ($product->Description != NULL)
						$string .= str_replace(';', '', $product->Description) . $separator;

					if ($product->Taxcode != NULL)
						$string .= str_replace(';', '', $product->Taxcode) . $separator;
				}
			}

			if ($this->Email != NULL)
				$string .= $this->Email . $separator;

			if ($this->FirstName != NULL)
				$string .= $this->FirstName . $separator;

			if ($this->LastName != NULL)
				$string .= $this->LastName . $separator;

			$string .= $this->ReturnAddress . $separator;
			$string .= $this->NotificationAddress . $separator;
			$string .= $secret_key;

			$string = hash('sha256', $string);
		}

		return $string;
	}

	/**
	 * Returns structure of payment object as an array.
	 * Only properties with values are returned in array.
	 *
	 * @return array Payment obects as an array
	 */
	public function convertToArray() {
		$ret = [];

		$ret['ApiVersion'] = $this->ApiVersion;
		$ret['Id'] = $this->Id;
		$ret['Mode'] = $this->Mode;

		if ($this->Description != NULL)
			$ret['Description'] = $this->Description;

		$ret['Products'] = [];

		foreach ($this->Products as $iterator => $product) {
			$ret['Products'][$iterator]['Code'] = $product->Code;

			if ($product->Amount != NULL)
				$ret['Products'][$iterator]['Amount'] = $product->Amount;

			if ($product->Price != NULL)
				$ret['Products'][$iterator]['Price'] = $product->Price;

			if ($product->Description != NULL)
				$ret['Products'][$iterator]['Description'] = $product->Description;

			if ($product->Taxcode != NULL)
				$ret['Products'][$iterator]['Taxcode'] = $product->Taxcode;
		}

		if ($this->Email != NULL)
			$ret['Email'] = $this->Email;

		if ($this->FirstName != NULL)
			$ret['FirstName'] = $this->FirstName;

		if ($this->LastName != NULL)
			$ret['LastName'] = $this->LastName;

		$ret['ReturnAddress'] = $this->ReturnAddress;
		$ret['NotificationAddress'] = $this->NotificationAddress;

		return $ret;
	}
}
// @codingStandardsIgnoreEnd
