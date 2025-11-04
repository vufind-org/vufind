<?php

/**
 * CPU Payment
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

use function count;
use function intval;

/**
 * Payment data to be sent to CPU payment gateway.
 *
 * @since   2015-05-19 MB, Version 1.0 created
 * @version 1.0
 */
class Payment
{
    /**
     * eCommerce integration.
     * 3 = eCommerce
     *
     * @var string
     */
    public const MODE_ECOMMERCE = '3';

    /**
     * Version of integration software.
     *
     * @var string
     */
    public $ApiVersion = '2.1.2';

    /**
     * Payment identification created by client system.
     * This identifies payments within client system.
     *
     * @var string
     */
    public $Id = null;

    /**
     * Integration mode.
     *
     * @var string
     */
    public $Mode = Payment::MODE_ECOMMERCE;

    /**
     * Description of payment. Max length 100 chars.
     * Will be added into email confirmation.
     *
     * @var string
     */
    public $Description = null;

    /**
     * List of products.
     *
     * @see Product
     * @var array
     */
    public $Products = [];

    /**
     * Url where server sends customer after he has succesfully paid his order.
     *
     * @var string
     */
    public $ReturnAddress = null;

    /**
     * Url where server sends notifications about statuses of order payments.
     *
     * @var string
     */
    public $NotificationAddress = null;

    /**
     * E-Mail address of customer.
     * Server sends confirmation email into this account.
     *
     * If email is not given then server will ask it at checkout.
     *
     * @var string
     */
    public $Email = null;

    /**
     * First name of customer.
     *
     * If name is not given then server will ask it at checkout.
     *
     * @var string
     */
    public $FirstName = null;

    /**
     * Last name of customer.
     *
     * If name is not given then server will ask it at checkout.
     *
     * @var string
     */
    public $LastName = null;

    /**
     * UI Language
     *
     * @var string
     */
    public $Language = null;

    /**
     * Constructor initialises object.
     *
     * @param string $id Payment identification
     */
    public function __construct($id = null)
    {
        $this->Id = Client::sanitize($id);
    }

    /**
     * Adds product into payment data.
     * Checks validity of product data before including it.
     *
     * @see    Product::isValid()
     * @param  Product $product Product
     * @return Payment
     */
    public function addProduct(Product $product)
    {
        if ($product->isValid()) {
            $this->Products[] = $product;
        }

        return $this;
    }

    /**
     * Checks mandatory properties of payment.
     *
     * @return mixed boolean|string if the payment is valid or error code
     */
    public function isValid()
    {
        $result = true;

        if (empty($this->ApiVersion)) {
            $result = 'Empty apiversion given to payment';
        }
        if (empty($this->Id)) {
            $result = 'Empty id given to payment';
        }
        if ($this->Mode !== self::MODE_ECOMMERCE) {
            $result = 'Mode is not eCommerce';
        }
        if (count($this->Products) === 0) {
            $result = 'No products given';
        }
        if (empty($this->ReturnAddress) || !filter_var($this->ReturnAddress, FILTER_VALIDATE_URL)) {
            $result = 'Empty or invalid return address given';
        }
        if (empty($this->NotificationAddress) || !filter_var($this->NotificationAddress, FILTER_VALIDATE_URL)) {
            $result = 'Empty or invalid notification address given';
        }

        return $result;
    }

    /**
     * Calculates sha256 signature.
     * Only mandatory properties and properties with values are used in calculation.
     *
     * @param  string $source     Source identification given by CPU
     * @param  string $secret_key Secret Key identification given by CPU
     * @return string sha256 hash signature
     */
    public function calculateHash($source, $secret_key)
    {
        $source     = Client::sanitize($source);
        $secret_key = Client::sanitize($secret_key);
        $separator  = '&';
        $string     = '';

        if (!empty($source) && !empty($secret_key)) {
            $string .= $this->ApiVersion . $separator;
            $string .= $source . $separator;
            $string .= $this->Id . $separator;
            $string .= $this->Mode . $separator;

            if ($this->Description != null) {
                $string .= str_replace(';', '', $this->Description) . $separator;
            }

            foreach ($this->Products as $product) {
                if ($product instanceof Product) {
                    $string .= str_replace(';', '', $product->Code) . $separator;

                    if ($product->Amount != null) {
                        $string .= intval($product->Amount) . $separator;
                    }

                    if ($product->Price != null) {
                        $string .= intval($product->Price) . $separator;
                    }

                    if ($product->Description != null) {
                        $string .= str_replace(';', '', $product->Description) . $separator;
                    }

                    if ($product->Taxcode != null) {
                        $string .= str_replace(';', '', $product->Taxcode) . $separator;
                    }
                }
            }

            if ($this->Email != null) {
                $string .= $this->Email . $separator;
            }

            if ($this->FirstName != null) {
                $string .= $this->FirstName . $separator;
            }

            if ($this->LastName != null) {
                $string .= $this->LastName . $separator;
            }

            if ($this->Language != null) {
                $string .= $this->Language . $separator;
            }

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
    public function convertToArray()
    {
        $ret = [];

        $ret['ApiVersion'] = $this->ApiVersion;
        $ret['Id'] = $this->Id;
        $ret['Mode'] = $this->Mode;

        if ($this->Description != null) {
            $ret['Description'] = $this->Description;
        }

        $ret['Products'] = [];

        foreach ($this->Products as $iterator => $product) {
            $ret['Products'][$iterator]['Code'] = $product->Code;

            if ($product->Amount != null) {
                $ret['Products'][$iterator]['Amount'] = $product->Amount;
            }

            if ($product->Price != null) {
                $ret['Products'][$iterator]['Price'] = $product->Price;
            }

            if ($product->Description != null) {
                $ret['Products'][$iterator]['Description'] = $product->Description;
            }

            if ($product->Taxcode != null) {
                $ret['Products'][$iterator]['Taxcode'] = $product->Taxcode;
            }
        }

        if ($this->Email != null) {
            $ret['Email'] = $this->Email;
        }

        if ($this->FirstName != null) {
            $ret['FirstName'] = $this->FirstName;
        }

        if ($this->LastName != null) {
            $ret['LastName'] = $this->LastName;
        }

        if ($this->Language != null) {
            $ret['Language'] = $this->Language;
        }

        $ret['ReturnAddress'] = $this->ReturnAddress;
        $ret['NotificationAddress'] = $this->NotificationAddress;

        return $ret;
    }
}
// @codingStandardsIgnoreEnd
