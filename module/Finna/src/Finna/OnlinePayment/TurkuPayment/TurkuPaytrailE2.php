<?php
/**
 * Turku Paytrail client
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2014-2018.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\OnlinePayment\TurkuPayment;

use Finna\OnlinePayment\Paytrail\PaytrailE2;

/**
 * Turku Paytrail client
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class TurkuPaytrailE2 extends PaytrailE2
{
    use \Finna\OnlinePayment\OnlinePaymentModuleTrait;

    /**
     * Name of the connecting application
     *
     * @var string
     */
    protected $applicationName;

    /**
     * Payers id data
     *
     * @var string
     */
    protected $oId;

    /**
     * Timestamp
     *
     * @var string
     */
    protected $timeStamp;

    /**
     * Request body waiting to be sent
     *
     * @var string
     */
    protected $requestBody;

    /**
     * Sapcode
     *
     * @var string
     */
    protected $sapCode;

    /**
     * Set oId
     *
     * @param string $oId user id
     *
     * @return void
     */
    public function setOid($oId)
    {
        $this->oId = $oId;
    }

    /**
     * Set application name
     *
     * @param string $applicationName username of contacting application
     *
     * @return void
     */
    public function setApplicationName($applicationName)
    {
        $this->applicationName = $applicationName;
    }

    /**
     * Set timestamp
     *
     * @param string $timeStamp required timestamp
     *
     * @return void
     */
    public function setTimeStamp($timeStamp)
    {
        $this->timeStamp = $timeStamp;
    }

    /**
     * Set sapcode
     *
     * @param string $sapCode sapcode of payment
     *
     * @return void
     */
    public function setSapCode($sapCode)
    {
        $this->sapCode = $sapCode;
    }

    /**
     * Generate proper headers for request
     *
     * @return array
     */
    public function generateHeaders()
    {
        return [
            'X-MERCHANT-ID' => $this->merchantId,
            'X-TURKU-SP' => $this->applicationName,
            'X-TURKU-TS' => $this->timeStamp,
            'X-TURKU-OID' => $this->oId,
            'Authorization' => $this->generateHash()
        ];
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
            '/[^\pL-0-9- "\',()\[\]{}*\/+\-_,.:&!?@#$£=*;~]+/u', ' ', $name
        );
        $this->products[] = [
            "title" => substr($name, 0, 255),
            "code" => $code,
            "sapCode" => $this->sapCode,
            "amount" => $quantity,
            "price" => number_format($unitPrice / 100, 2, '.', ''),
            "vat" => $vatPercent,
            "discount" => "0.00",
            "type" => '1'
        ];
    }

    /**
     * Generate request and process the response
     *
     * @param string $url to make request
     *
     * @return void
     */
    public function sendRequest($url)
    {
        $this->setTimeStamp(gmdate("Y-m-d\TH:i:s\Z"));
        $this->requestBody = json_encode($this->generateBody());
        $headers = $this->generateHeaders();
        $response = $this->postRequest($url, $this->requestBody, [], $headers);

        if (isset($response['httpCode']) && $response['httpCode'] === 200) {
            $responseArray = json_decode($response['response'], true);
            if (isset($responseArray['orderNumber'])
                && isset($responseArray['url'])
            ) {
                header('Location:' . $responseArray['url']);
                exit();
            }
        }
    }

    /**
     * Create request body
     *
     * @return array
     */
    public function generateBody()
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

        return [
            'orderNumber' => $this->orderNumber,
            'locale' => $this->locale,
            'currency' => 'EUR',
            'urlSet' => [
                'success' => $this->successUrl,
                'failure' => $this->cancelUrl,
                'pending' => '',
                'notification' => $this->notifyUrl
            ],
            'orderDetails' => [
                'includeVat' => '0',
                'products' => $this->products
            ]
        ];
    }

    /**
     * Generates required hashed string
     *
     * @return string
     */
    public function generateHash()
    {
        return hash(
            'sha256', $this->applicationName .
            $this->timeStamp . $this->requestBody . $this->secret
        );
    }

    /**
     * Validate payment return and notify requests.
     *
     * @param string $orderNumber Order number
     * @param string $paid        Payment signature
     * @param int    $timeStamp   Timestamp
     * @param string $method      Payment method
     * @param string $authCode    Returned authentication code
     *
     * @return bool
     */
    public function validateRequest($orderNumber, $paid, $timeStamp, $method,
        $authCode
    ) {
        $response = "$orderNumber|$timeStamp|$paid|$method|{$this->secret}";
        $hash = strtoupper(md5($response));
        return $authCode === $hash;
    }
}
