<?php

/**
 * CPU Client
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

use VuFind\Log\LoggerAwareTrait;
use VuFindHttp\HttpServiceAwareTrait;

use function intval;
use function strlen;

/**
 * Client example of CPU Verkkomaksu API.
 * Handles validating and sending data to eCommerce service.
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   MB <asiakastuki@cpu.fi>
 * @license  https://unlicense.org The Unlicense
 * @link     https://www.cpu.fi/
 * @since    2015-05-19 MB, Version 1.0 created
 */
class Client
{
    use HttpServiceAwareTrait;
    use LoggerAwareTrait;

    /**
     * Url of eCommerce service where payment data will be sent.
     *
     * @var string
     */
    private $service_url;

    /**
     * Identification code of client service. This can be considered as an account number.
     * Granted by CPU.
     *
     * Max. length 40 chars
     *
     * @var string
     */
    private $source;

    /**
     * Secret Key of client service. This can be considered as an account password.
     * Granted by CPU.
     *
     * @var string
     */
    private $secret_key;

    /**
     * Constructor initializes object with client settings.
     *
     * @param string $service_url Url pointing to eCommerce payment checkout
     * @param string $source      Client account
     * @param string $secret_key  Client password
     */
    public function __construct($service_url, $source, $secret_key)
    {
        $this->service_url = self::sanitize($service_url);
        $this->source      = mb_substr(self::sanitize($source), 0, 40);
        $this->secret_key  = self::sanitize($secret_key);
    }

    /**
     * Sends new payment to eCommerce.
     * Do not send the secretkey as part of the message, it will make the request fail!
     *
     * Redirect customer to PaymentAddress after validating response data.
     *
     * @param Payment $payment Payment data
     *
     * @return mixed array containing an error message or JSON response from eCommerce
     */
    public function sendPayment(Payment $payment)
    {
        $valid = $payment->isValid();

        if ($valid !== true) {
            return ['error' => $valid];
        }

        if ($this->service_url && $this->source && $this->secret_key) {
            // Prepare data to be sent.
            $data = $payment->convertToArray();
            $data['Source'] = $this->source;
            $data['Hash']   = self::calculateHash($payment, $this->source, $this->secret_key);

            $json_data = json_encode($data);

            if (false === $json_data) {
                throw new \Exception(
                    'Could not convert request to JSON: ' . var_export($data, true)
                );
            }

            $options = ['maxredirects' => 1];
            $headers = [
               'Content-Type' => 'application/json; charset=utf-8',
            ];

            $client = $this->httpService->createClient($this->service_url, 'POST', 30);
            $client->setOptions($options);
            $headers = array_merge(
                [
                    'Content-Type' => 'application/json',
                    'Content-Length' => strlen($json_data),
                ],
                $headers
            );
            $client->setHeaders($headers);
            $client->setRawBody($json_data);
            $response = $client->send();

            $response = $this->httpService->post(
                $this->service_url,
                $json_data,
                $options,
                $headers
            );

            $status = $response->getStatusCode();
            $content = $response->getBody();

            if (!$response->isSuccess()) {
                throw new \Exception(
                    "Error posting request: invalid status code: $status"
                    . ", url: $this->service_url, body: $json_data, headers: " . var_export($headers, true)
                    . ", response: $content"
                );
                return ['error' => 'Failed to send payment'];
            }

            return $content;
        }

        return ['error' => 'Error with settings'];
    }

    /**
     * Calculates sha256 signature.
     * Only mandatory properties and properties with values are used in calculation.
     *
     * @param Payment $payment    Payment object
     * @param string  $source     Source identification given by CPU
     * @param string  $secret_key Secret Key identification given by CPU
     *
     * @return string sha256 hash signature
     */
    public static function calculateHash(Payment $payment, $source, $secret_key)
    {
        $source     = self::sanitize($source);
        $secret_key = self::sanitize($secret_key);
        $separator  = '&';
        $string     = '';

        if ($payment->isValid() === true && !empty($source) && !empty($secret_key)) {
            $string .= $payment->ApiVersion . $separator;
            $string .= $source . $separator;
            $string .= $payment->Id . $separator;
            $string .= $payment->Mode . $separator;

            if ($payment->Description != null) {
                $string .= str_replace(';', '', $payment->Description) . $separator;
            }

            foreach ($payment->Products as $product) {
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

            if ($payment->Email != null) {
                $string .= $payment->Email . $separator;
            }

            if ($payment->FirstName != null) {
                $string .= $payment->FirstName . $separator;
            }

            if ($payment->LastName != null) {
                $string .= $payment->LastName . $separator;
            }

            if ($payment->Language != null) {
                $string .= $payment->Language . $separator;
            }

            $string .= $payment->ReturnAddress . $separator;
            $string .= $payment->NotificationAddress . $separator;
            $string .= $secret_key;

            $string = hash('sha256', $string);
        }

        return $string;
    }

    /**
     * Simple sanitazion method.
     *
     * @param string $value Value to be sanitated
     *
     * @return string Clean value
     */
    public static function sanitize($value)
    {
        return str_replace(';', '', strip_tags(trim(filter_var($value, FILTER_SANITIZE_STRING))));
    }
}
// @codingStandardsIgnoreEnd
