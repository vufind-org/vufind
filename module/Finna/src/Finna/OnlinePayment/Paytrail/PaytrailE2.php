<?php
/**
 * Paytrail E2 Client
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
namespace Finna\OnlinePayment\Paytrail;

/**
 * Paytrail E2 client
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
class PaytrailE2
{
    use \Finna\OnlinePayment\OnlinePaymentModuleTrait;

    const TYPE_NORMAL = 1;
    const TYPE_SHIPPING = 2;
    const TYPE_HANDLING = 3;

    /**
     * Merchant ID
     *
     * @var string
     */
    protected $merchantId;

    /**
     * Merchant secret
     *
     * @var string;
     */
    protected $secret;

    /**
     * Success return URL
     *
     * @var string;
     */
    protected $successUrl;

    /**
     * Cancel/failure return URL
     *
     * @var string
     */
    protected $cancelUrl;

    /**
     * Notification URL
     *
     * @var string
     */
    protected $notifyUrl;

    /**
     * Currency. Only EUR is supported.
     *
     * @var string
     */
    protected $currency = 'EUR';

    /**
     * Locale. Supported values are fi_FI, sv_SE and en_US.
     *
     * @var string
     */
    protected $locale = 'fi_FI';

    /**
     * Order number
     *
     * @var string
     */
    protected $orderNumber;

    /**
     * Payment description
     *
     * @var string
     */
    protected $paymentDescription = '';

    /**
     * Merchant description
     *
     * @var string
     */
    protected $merchantDescription = '';

    /**
     * Payer's first name
     *
     * @var string
     */
    protected $firstName = '';

    /**
     * Payer's last name
     *
     * @var string
     */
    protected $lastName = '';

    /**
     * Payer's email address
     *
     * @var string
     */
    protected $email = '';

    /**
     * Total amount to pay in cents. Mutually exclusive with $products.
     *
     * @var int
     */
    protected $totalAmount = null;

    /**
     * Products. Mutually exclusive with $totalAmount.
     *
     * @var array
     */
    protected $products = [];

    /**
     * Constructor
     *
     * @param string $merchantId Merchant ID
     * @param string $secret     Merchant secret
     * @param string $locale     Locale
     */
    public function __construct($merchantId, $secret, $locale)
    {
        if (!in_array($locale, ['fi_FI', 'sv_SE', 'en_US'])) {
            throw new \Exception("Invalid locale: $locale");
        }

        $this->merchantId = $merchantId;
        $this->secret = $secret;
        $this->locale = $locale;
    }

    /**
     * Set URLs
     *
     * @param string $successUrl Success URL
     * @param string $cancelUrl  Cancel/failure URL
     * @param string $notifyUrl  Notification URL
     *
     * @return void
     */
    public function setUrls($successUrl, $cancelUrl, $notifyUrl)
    {
        $this->successUrl = $successUrl;
        $this->cancelUrl = $cancelUrl;
        $this->notifyUrl = $notifyUrl;
    }

    /**
     * Set order number
     *
     * @param string $orderNumber Order number
     *
     * @return void
     */
    public function setOrderNumber($orderNumber)
    {
        $this->orderNumber = $orderNumber;
    }

    /**
     * Set currency
     *
     * @param string $currency Currency
     *
     * @return void
     */
    public function setCurrency($currency)
    {
        if ('EUR' !== $currency) {
            throw new \Exception("Invalid currency: $currency");
        }
        $this->currency = $currency;
    }

    /**
     * Set payment description displayed to the user
     *
     * @param string $description Description
     *
     * @return void
     */
    public function setPaymentDescription($description)
    {
        $this->paymentDescription = preg_replace(
            '/[^\pL0-9 "\', ()\[\]{}*+\-_,.]+/u', ' ', $description
        );
    }

    /**
     * Set payment description displayed in the merchant UI
     *
     * @param string $description Description
     *
     * @return void
     */
    public function setMerchantDescription($description)
    {
        $this->merchantDescription = preg_replace(
            '/[^\pL0-9 "\', ()\[\]{}*+\-_,.]+/u', ' ', $description
        );
    }

    /**
     * Set payer's first name
     *
     * @param string $name First name
     *
     * @return void
     */
    public function setFirstName($name)
    {
        $this->firstName = mb_substr(
            preg_replace(
                '/[^\pL0-9 "\',()\[\]{}*\/+\-_,.:&!?@#$£=*;~]+/u', ' ', $name
            ),
            0, 64, 'UTF-8'
        );
    }

    /**
     * Set payer's last name
     *
     * @param string $name Last name
     *
     * @return void
     */
    public function setLastName($name)
    {
        $this->lastName = mb_substr(
            preg_replace(
                '/[^\pL0-9 "\',()\[\]{}*\/+\-_,.:&!?@#$£=*;~]+/u', ' ', $name
            ),
            0, 64, 'UTF-8'
        );
    }

    /**
     * Set payer's email address
     *
     * @param string $email Email address
     *
     * @return void
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * Set total amount to pay
     *
     * @param int $amount Amount in cents
     *
     * @return void
     */
    public function setTotalAmount($amount)
    {
        $this->totalAmount = $amount;
    }

    /**
     * Add a product
     *
     * @param string $name       Product name
     * @param string $code       Product code
     * @param int    $quantity   Number of items
     * @param int    $unitPrice  Unit price in cents
     * @param int    $vatPercent VAT percent
     * @param int    $type       Payment type (const TYPE_*)
     *
     * @return void
     */
    public function addProduct($name, $code, $quantity, $unitPrice, $vatPercent,
        $type
    ) {
        $index = count($this->products);
        // For some reason the E2 interface does not allow alphanumeric item codes
        if ($code) {
            $name = "$code $name";
        }
        $name = preg_replace(
            '/[^\pL0-9 "\',()\[\]{}*\/+\-_,.:&!?@#$£=*;~]+/u', ' ', $name
        );
        $this->products[] = [
            "ITEM_TITLE[$index]" => substr($name, 0, 255),
            "ITEM_QUANTITY[$index]" => $quantity,
            "ITEM_UNIT_PRICE[$index]" => number_format($unitPrice / 100, 2, '.', ''),
            "ITEM_VAT_PERCENT[$index]" => $vatPercent,
            "ITEM_TYPE[$index]" => $type
        ];
    }

    /**
     * Create payment form data
     *
     * @return array Form fields
     */
    public function createPaymentFormData()
    {
        if (null === $this->orderNumber) {
            throw new \Exception('Order number must be specified');
        }
        if (null === $this->totalAmount && empty($this->products)) {
            throw new \Exception(
                'Either total amount or products must be specified'
            );
        }
        if (null !== $this->totalAmount && !empty($this->products)) {
            throw new \Exception(
                'Total amount and products can not be used at the same time'
            );
        }

        $request = [
            'MERCHANT_ID' => $this->merchantId,
            'CURRENCY' => $this->currency,
            'ORDER_NUMBER' => $this->orderNumber,
            'URL_SUCCESS' => $this->successUrl,
            'URL_CANCEL' => $this->cancelUrl,
            'URL_NOTIFY' => $this->notifyUrl,
            'PARAMS_IN' => '', // filled later
            'PARAMS_OUT' => 'PAYMENT_ID,ORDER_NUMBER,TIMESTAMP,STATUS',
            'LOCALE' => $this->locale,
        ];

        if (!empty($this->paymentDescription)) {
            $request['MSG_UI_PAYMENT_METHOD'] = $this->paymentDescription;
        }

        if (!empty($this->merchantDescription)) {
            $request['MSG_UI_MERCHANT_PANEL'] = $this->merchantDescription;
        }

        if (!empty($this->firstName)) {
            $request['PAYER_PERSON_FIRSTNAME'] = $this->firstName;
        }

        if (!empty($this->lastName)) {
            $request['PAYER_PERSON_LASTNAME'] = $this->lastName;
        }

        if (!empty($this->email)) {
            $request['PAYER_PERSON_EMAIL'] = $this->email;
        }

        if (null !== $this->totalAmount) {
            $request['AMOUNT'] = number_format(
                $this->totalAmount / 100, 2, '.', ''
            );
        } else {
            foreach ($this->products as $product) {
                $request += $product;
            }
        }

        // Remove pipe from all fields
        $request = array_map(
            function ($s) {
                return str_replace('|', ' ', $s);
            },
            $request
        );

        // PARAMS_IN
        $request['PARAMS_IN'] = implode(',', array_keys($request));

        // AUTHCODE
        $authFields = array_values($request);
        array_unshift($authFields, $this->secret);
        $request['AUTHCODE'] = strtoupper(hash('sha256', implode('|', $authFields)));

        return $request;
    }

    /**
     * Validate payment return and notify requests.
     *
     * @param string $orderNumber Order number
     * @param string $paymentId   Payment signature
     * @param int    $timeStamp   Timestamp
     * @param string $status      Payment status
     * @param string $authCode    Returned authentication code
     *
     * @return bool
     */
    public function validateRequest($orderNumber, $paymentId, $timeStamp, $status,
        $authCode
    ) {
        $response = "$paymentId|$orderNumber|$timeStamp|$status|{$this->secret}";
        $hash = strtoupper(hash('sha256', $response));
        return $authCode === $hash;
    }
}
