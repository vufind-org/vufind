<?php
// @codingStandardsIgnoreStart
namespace Finna\OnlinePayment;

/**
 * This class provides a helper class for using Paytrail service.
 * It is implemented in PHP but it should be quite easily portable to
 * other languages as well.
 *
 * @example
 *
 * require_once("Paytrail_Module_Rest.php");
 *
 * $urlset = new Paytrail_Module_Rest_Urlset(
 *      "https://www.demoshop.com/sv/success",  // success url
 *      "https://www.demoshop.com/sv/failure",  // failure url
 *      "https://www.demoshop.com/sv/notify",   // notify url
 *      "https://www.demoshop.com/sv/pending"   // pending url
 * );
 * $contact = new Paytrail_Module_Rest_Contact(
 *      "Test",                                 // first name
 *      "Person",                               // last name
 *      "test.person@democompany.com",          // email
 *      "Test street 1",                        // street address
 *      "12340",                                // postal code
 *      "Helsinki",                             // postal city
 *      "FI",                                   // country (ISO-3166)
 *      "040123456",                            // telephone number
 *      "",                                     // mobile number
 *      "Demo Company Ltd"                      // company name
 * );
 *
 * $orderNumber = "1";                          // Use unique order number
 * $payment = new Paytrail_Module_Rest_Payment($orderNumber, $contact, $urlset);
 * $payment->addProduct(
 *      "Test product"                          // product title
 *      "01234",                                // product number/code
 *      "1.00",                                 // number of these products
 *      "19.90",                                // Price (/one item)
 *      "23.00",                                // Tax percentage
 *      "0.00",                                 // Discount percentage
 *      Paytrail_Module_Rest_Product::TYPE_NORMAL   // Normal product row
 * );
 * // Add more product rows when necessary
 *
 * // Submit product to Paytrail
 * $module = new Paytrail_Module_Rest(13466, "");
 * try {
 *      $result = $module->processPayment($payment);
 * }
 * catch(Paytrail_Exception $e) {
 *      // handle error
 * }
 *
 * // Use payment url and token as you wish
 * header("Location: {$result->url}");
 *
 * @version 2.0, 2014-07-11
 */

/**
 * Paytrail exception is a normal PHP exception. Using an inherited
 * class allows catching only Paytrail exceptions with try-catch clause.
 */
class Paytrail_Exception extends \Exception
{
    public function __construct($message)
    {
        parent::__construct("Paytrail exception: " . $message);
    }
}

/**
 * Urlset object describes all return urls used with the service
 */
class Paytrail_Module_Rest_Urlset
{
    public $successUrl;
    public $failureUrl;
    public $notificationUrl;
    public $pendingUrl;

    public function __construct($successUrl, $failureUrl, $notificationUrl, $pendingUrl = null)
    {
        $this->successUrl = $successUrl;
        $this->failureUrl = $failureUrl;
        $this->notificationUrl = $notificationUrl;
        $this->pendingUrl = $pendingUrl;
    }
}

/**
 * Contact data structure holds information about payment actor.
 * This information is saved with the payment and is available
 * with the payment in merchant's panel.
 */
class Paytrail_Module_Rest_Contact
{
    public $firstName;
    public $lastName;
    public $email;
    public $addrStreet;
    public $addrPostalCode;
    public $addrPostalOffice;
    public $addrCountry;
    public $telNo;
    public $cellNo;

    /**
     * Contructor for Contact data structure. Contact holds information
     * about the user paying the payment.
     *
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $addrStreet
     * @param string $addrZip
     * @param string $addrCity
     * @param string $addrCountry
     * @param string $telNo
     * @param string $cellNo
     * @param string $company
     */
    public function __construct($firstName, $lastName, $email, $addrStreet, $addrPostalCode, $addrPostalOffice, $addrCountry, $telNo = "", $cellNo = "", $company = "")
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->addrStreet = $addrStreet;
        $this->addrPostalCode = $addrPostalCode;
        $this->addrPostalOffice = $addrPostalOffice;
        $this->addrCountry = $addrCountry;
        $this->telNo = $telNo;
        $this->cellNo = $cellNo;
        $this->company = $company;
    }
}

/**
 * Product object acts as a payment products. There is one product object
 * for each product row. Product objects are automatically generated when
 * payment function addProduct is called. You never need to directly work
 * with product objects.
 */
class Paytrail_Module_Rest_Product
{
    const TYPE_NORMAL = 1;
    const TYPE_POSTAL = 2;
    const TYPE_HANDLING = 3;

    public $title;
    public $code;
    public $amount;
    public $price;
    public $vat;
    public $discount;
    public $type;

    /**
     * @param string $title
     * @param string $code
     * @param float $amount
     * @param float $price
     * @param flaot $vat
     * @param float $discount
     * @param int $type
     */
    public function __construct($title, $code, $amount, $price, $vat, $discount, $type)
    {
        $this->title = $title;
        $this->code = $code;
        $this->amount = $amount;
        $this->price = $price;
        $this->vat = $vat;
        $this->discount = $discount;
        $this->type = $type;
    }
}

/**
 * This object is returned when a payment is processed to Paytrail
 * It allows you to query for token or URL
 */
class Paytrail_Module_Rest_Result
{
    private $_token;
    private $_url;

    public function __construct($token, $url)
    {
        $this->_token = $token;
        $this->_url = $url;
    }

    public function getToken()
    {
        return $this->_token;
    }

    public function getUrl()
    {
        return $this->_url;
    }
}

abstract class Paytrail_Module_Rest_Payment
{
    private $_orderNumber;
    private $_urlset;
    private $_referenceNumber = "";
    private $_description = "";
    private $_currency = "EUR";
    private $_locale = "fi_FI";

    public function __construct($orderNumber, $urlset)
    {
        $this->_orderNumber = $orderNumber;
        $this->_urlset = $urlset;
    }

    /**
     * @return string order number for this payment
     */
    public function getOrderNumber()
    {
        return $this->_orderNumber;
    }

    /**
     * @return Paytrail_Module_E1_Urlset payment return url object for this payment
     */
    public function getUrlset()
    {
        return $this->_urlset;
    }

    /**
     * You can set a reference number for a payment but it is *not* recommended.
     *
     * Reference number set using this function will only be used for interface payments.
     * Interface payment means a payment done with such a payment method that is used
     * with own contract (using Paytrail only as a technical API). If payment is made
     * with payment method that is used directly with Paytrail contract, this value
     * is not used - instead Paytrail uses auto generated reference number.
     *
     * Using custom reference number may be useful if you need to automatically confirm
     * payments paid directly to your own account with your own contract. With custom
     * reference number you can match payments with it.
     *
     * @param $referenceNumber Customer reference number
     */
    public function setCustomReferenceNumber($referenceNumber)
    {
        $this->_referenceNumber = $referenceNumber;
    }

    /**
     * @return string Custom reference number attached to this payment
     */
    public function getCustomReferenceNumber()
    {
        return $this->_referenceNumber;
    }

    /**
     * Change used locale. Locale affects language and number and date presentation formats.
     *
     * Paytrail supports currently three locales: Finnish (fi_FI), English (en_US)
     * and Swedish (sv_SE). Default locale is fi_FI.
     *
     * @param string $locale
     */
    public function setLocale($locale)
    {
        if (!in_array($locale, ["fi_FI", "en_US", "sv_SE"])) {
            throw new Paytrail_Exception("Given locale is unsupported.");
        }

        $this->_locale = $locale;
    }

    /**
     * @return string Locale attached to this payment
     */
    public function getLocale()
    {
        return $this->_locale;
    }

    /**
     * Set non-default currency. Currently the default currency (EUR) is only supported
     * value.
     *
     * @param $currency Currency in which product prices are given
     */
    public function setCurrency($currency)
    {
        if ($currency != "EUR" && $currency != "SEK") {
            throw new Paytrail_Exception("Currently EUR and SEK are the only supported currency.");
        }

        $this->_currency = $currency;
    }

    /**
     * @return string Currency attached to this payment
     */
    public function getCurrency()
    {
        return $this->_currency;
    }

    /**
     * You may optionally set description for the payment. This message
     * will only be visible in merchant's panel with the payment - nowhere else.
     * It allows you to save additional data with payment when necessary.
     *
     * @param string $description Private payment description
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * @return string Description attached to this payment
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * Get payment data as array
     *
     * @return array REST API compatible payment data
     * @throws Paytrail_Exception
     */
    public function getJsonData()
    {
        throw new Paytrail_Exception("Paytrail_Module_Rest_Payment is not meant to be used directly. Use E1 or S1 module instead.");
    }
}

class Paytrail_Module_Rest_Payment_S1 extends Paytrail_Module_Rest_Payment
{
    private $_price;

    public function __construct($orderNumber, $urlset, $price)
    {
        parent::__construct($orderNumber, $urlset);
        $this->_price = $price;
    }

    public function getPrice()
    {
        return $this->_price;
    }

    /**
     * Get payment data as array
     *
     * @return array REST API compatible payment data
     * @throws Paytrail_Exception
     */
    public function getJsonData()
    {
        $data = [
            "orderNumber" => $this->getOrderNumber(),
            "referenceNumber" => $this->getCustomReferenceNumber(),
            "description" => $this->getDescription(),
            "currency" => $this->getCurrency(),
            "locale" => $this->getLocale(),
            "urlSet" => [
                "success" => $this->getUrlset()->successUrl,
                "failure" => $this->getUrlset()->failureUrl,
                "pending" => $this->getUrlset()->pendingUrl,
                "notification" => $this->getUrlset()->notificationUrl,
            ],
            "price" => $this->getPrice(),
        ];

        return $data;
    }
}

/**
 * Payment object represents the actual payment to be transmitted
 * to Paytrail interface
 *
 * E1 references to Paytrail interface version E1, which
 * is extended and recommended version.
 */
class Paytrail_Module_Rest_Payment_E1 extends Paytrail_Module_Rest_Payment
{
    private $_contact;
    private $_products = [];
    private $_includeVat = 1;

    public function __construct($orderNumber, Paytrail_Module_Rest_Urlset $urlset, Paytrail_Module_Rest_Contact $contact)
    {
        parent::__construct($orderNumber, $urlset);

        $this->_orderNumber = $orderNumber;
        $this->_contact = $contact;
        $this->_urlset = $urlset;
    }

    /**
     * Use this function to add each order product to payment.
     *
     * Please group same products using $amount. Paytrail
     * supports up to 500 product rows in a single payment.
     *
     * @param string $title
     * @param string $no
     * @param float $amount
     * @param float $price
     * @param float $tax
     * @param flaot $discount
     * @param int $type
     */
    public function addProduct($title, $no, $amount, $price, $tax, $discount, $type = 1)
    {
        if (count($this->_products) >= 500) {
            throw new Paytrail_Exception("Paytrail can only handle up to 500 different product rows. Please group products using product amount.");
        }

        $this->_products[] = new Paytrail_Module_Rest_Product($title, $no, $amount, $price, $tax, $discount, $type);
    }

    /**
     * @return Paytrail_Module_E1_Contact contact data for this payment
     */
    public function getContact()
    {
        return $this->_contact;
    }

    /**
     * @return array List of Paytrail_Module_E1_Product objects for this payment
     */
    public function getProducts()
    {
        return $this->_products;
    }

    /**
     * You can decide whether you wish to use taxless prices (mode=0) or
     * prices which include taxes. Default mode is 1 (taxes are in prices).
     *
     * You should always use the same mode that your web shop uses - otherwise
     * you will get problems with rounding since SV supports prices with only
     * 2 decimals.
     *
     * @param int $mode
     */
    public function setVatMode($mode)
    {
        $this->_includeVat = $mode;
    }

    /**
     * @return int Vat mode attached to this payment
     */
    public function getVatMode()
    {
        return $this->_includeVat;
    }

    /**
     * Get payment data as array
     *
     * @return array REST API compatible payment data
     * @throws Paytrail_Exception
     */
    public function getJsonData()
    {
        $data = [
            "orderNumber" => $this->getOrderNumber(),
            "referenceNumber" => $this->getCustomReferenceNumber(),
            "description" => $this->getDescription(),
            "currency" => $this->getCurrency(),
            "locale" => $this->getLocale(),
            "urlSet" => [
                "success" => $this->getUrlset()->successUrl,
                "failure" => $this->getUrlset()->failureUrl,
                "pending" => $this->getUrlset()->pendingUrl,
                "notification" => $this->getUrlset()->notificationUrl,
            ],
            "orderDetails" => [
                "includeVat" => $this->getVatMode(),
                "contact" => [
                    "telephone" => $this->getContact()->telNo,
                    "mobile" => $this->getContact()->cellNo,
                    "email" => $this->getContact()->email,
                    "firstName" => $this->getContact()->firstName,
                    "lastName" => $this->getContact()->lastName,
                    "companyName" => $this->getContact()->company,
                    "address" => [
                        "street" => $this->getContact()->addrStreet,
                        "postalCode" => $this->getContact()->addrPostalCode,
                        "postalOffice" => $this->getContact()->addrPostalOffice,
                        "country" => $this->getContact()->addrCountry,
                    ],
                ],
                "products" => [],
            ],
        ];

        foreach($this->getProducts() as $product) {
            $data["orderDetails"]["products"][] = [
                "title" => $product->title,
                "code" => $product->code,
                "amount" => $product->amount,
                "price" => $product->price,
                "vat" => $product->vat,
                "discount" => $product->discount,
                "type" => $product->type
            ];
        }

        return $data;
    }
}

/**
 * Main module
 */
class Paytrail_Module_Rest
{
    private $_merchantId = "";
    private $_merchantSecret = "";
    private $_serviceUrl = "";

    /**
     * Initialize module with your own merchant id and merchant secret.
     *
     * While building and testing integration, you can use demo values
     * (merchantId = 13466, merchantSecret = ...)
     *
     * @param int $merchantId
     * @param string $merchantSecret
     */
    public function __construct($merchantId, $merchantSecret, $serviceUrl = "https://payment.paytrail.com")
    {
        $this->_merchantId = $merchantId;
        $this->_merchantSecret = $merchantSecret;
        $this->_serviceUrl = $serviceUrl;
    }

    /**
     * @return Module version as a string
     */
    public function getVersion()
    {
        return "1.0";
    }

    /**
     * Get url for payment
     *
     * @param Paytrail_Module_E1_Payment $payment
     * @throws Paytrail_Exception
     * @return Paytrail_Module_E1_Result
     */
    public function processPayment(Paytrail_Module_Rest_Payment &$payment)
    {
        $url = $this->_serviceUrl . "/token/json";

        $data = $payment->getJsonData();

        // Create data array
        $url = $this->_serviceUrl . "/api-payment/create";

        $result = $this->_postJsonRequest($url, json_encode($data));

        if ($result->httpCode != 201) {
            if ($result->contentType === "application/xml") {
                $xml = simplexml_load_string($result->response);

                throw new Paytrail_Exception($xml->errorMessage, $xml->errorCode);
            } elseif ($result->contentType == "application/json") {
                $json = json_decode($result->response);

                throw new Paytrail_Exception($json->errorMessage, $json->errorCode);
            }
        }

        $data = json_decode($result->response);

        if (!$data) {
            throw new Paytrail_Exception($result->response, "unknown-error");
        }

        return new Paytrail_Module_Rest_Result($data->token, $data->url);
    }

    /**
     * This function can be used to validate parameters returned by return and notify requests.
     * Parameters must be validated in order to avoid hacking of payment confirmation.
     * This function is usually used like:
     *
     * $module = new Paytrail_Module_E1($merchantId, $merchantSecret);
     * if ($module->validateNotifyParams($_GET["ORDER_NUMBER"], $_GET["TIMESTAMP"], $_GET["PAID"], $_GET["METHOD"], $_GET["AUTHCODE"])) {
     *   // Valid notification, confirm payment
     * } else {
     *   // Invalid notification, possibly someone is trying to hack it. Do nothing or create an alert.
     * }
     *
     * @param string $orderNumber
     * @param int $timeStamp
     * @param string $paid
     * @param int $method
     * @param string $authCode
     */
    public function confirmPayment($orderNumber, $timeStamp, $paid, $method, $authCode)
    {
        $base = "{$orderNumber}|{$timeStamp}|{$paid}|{$method}|{$this->_merchantSecret}";
        return $authCode == strtoupper(md5($base));
    }

    /**
     * This method submits given parameters to given url as a post request without
     * using curl extension. This should require minimum extensions
     *
     * @param $url
     * @param $params
     * @throws Paytrail_Exception
     */
    private function _postJsonRequest($url, $content)
    {
        // Check that curl is available
        if (!function_exists("curl_init")) {
            throw new Paytrail_Exception("Curl extension is not available. Paytrail_Module_Rest requires curl.");
        }

        // Set all the curl options
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json",
            "X-Verkkomaksut-Api-Version: 1"
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_merchantId . ":" . $this->_merchantSecret);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Read result, including http code
        $result = new \StdClass();
        $result->response = curl_exec($ch);
        $result->httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result->contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        // Got no status code?
        $curlError = $result->httpCode > 0 ? null : curl_error($ch) . ' (' . curl_errno($ch) . ')';

        curl_close($ch);

        // Connection failure
        if ($curlError) {
            throw new Paytrail_Exception("Connection failure. Please check that payment.paytrail.com is reachable from your environment ({$curlError})");
        }

        return $result;
    }
// @codingStandardsIgnoreEnd
}
