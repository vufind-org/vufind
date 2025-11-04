<?php

/**
 * CPU Product
 *
 * PHP version 8
 *
 * This is free and unencumbered software released into the public domain.
 *
 * Anyone is free to copy, modify, publish, use, compile, sell, or
 * distribute this software, either in source code form or as a compiled
 * binary, for any purpose, commercial or non-commercial, and by any
 * means.
 *
 * In jurisdictions that recognize copyright laws, the author or authors
 * of this software dedicate any and all copyright interest in the
 * software to the public domain. We make this dedication for the benefit
 * of the public at large and to the detriment of our heirs and
 * successors. We intend this dedication to be an overt act of
 * relinquishment in perpetuity of all present and future rights to this
 * software under copyright law.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR
 * OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 * For more information, please refer to <https://unlicense.org>
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   MB <asiakastuki@cpu.fi>
 * @license  https://unlicense.org The Unlicense
 * @link     https://www.cpu.fi/
 */

// @codingStandardsIgnoreStart

namespace Finna\OnlinePayment\Handler\Connector\Cpu;

/**
 * Product data wrapper to make it easier to use and validate products.
 *
 * @since   2015-05-19 MB, version 1.0 created
 * @version 1.0
 */
class Product
{
    /**
     * Product code. Max. length 25 chars.
     *
     * Use sku or other identification.
     * There needs to be a product with this sku in eCommerce!
     *
     * @var string
     */
    public $Code = null;

    /**
     * Order amount.
     *
     * @var integer
     */
    public $Amount = null;

    /**
     * Price of single product vat included in cents.
     *
     * @example 20.50€ = 2050
     * @var     integer
     */
    public $Price = null;

    /**
     * Product description. Max. length 40 chars.
     * Will be added into confirmation email sent by server at checkout.
     *
     * @var string
     */
    public $Description = null;

    /**
     * Vat code to be used with this product. Max. length 25 chars.
     * There needs to be a taxcode with this taxcode in eCommerce!
     *
     * @var string
     */
    public $Taxcode = null;

    /**
     * Constructor creates the product.
     * Sanitizes all the parameters to be fit for Product object.
     *
     * @param string  $code        Product code
     * @param integer $amount      Amount ordered
     * @param number  $price       Price of single product
     * @param string  $description Short product description
     */
    public function __construct($code, $amount = null, $price = null, $description = null)
    {
        $this->Code = Client::sanitize($code);

        if ($amount) {
            $this->Amount = Client::sanitize($amount);
        }

        if ($price) {
            $this->Price = Client::sanitize($price);
        }

        if ($description) {
            $this->Description = Client::sanitize($description);
        }
    }

    /**
     * Checks mandatory properties of Product.
     *
     * @return boolean All mandatory properties are set
     */
    public function isValid()
    {
        return ($this->Code != null)
            ? true
            : false;
    }
}
// @codingStandardsIgnoreEnd
